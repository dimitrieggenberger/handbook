#!/usr/bin/env node
/**
 * Remote Streamable-HTTP entry point for the Handbook MCP adapter.
 *
 * Exposes the same tools as the local stdio server (lib/handbook.mjs) over an
 * authenticated HTTPS endpoint so a ChatGPT (Enterprise/Edu) custom connector
 * can reach them (spec 36.7). There is no publish tool, by design.
 *
 * Two separate secrets, never confused:
 *   - HANDBOOK_WSTOKEN         Moodle token; stays server-side, never sent to
 *                             the client, never logged.
 *   - HANDBOOK_MCP_AUTH_TOKEN  bearer token the connector must present to us.
 *
 * Configuration (environment):
 *   HANDBOOK_BASE_URL          e.g. https://learn.europaschule.eu
 *   HANDBOOK_WSTOKEN           authorised service-account token
 *   HANDBOOK_MCP_AUTH_TOKEN    long random string the connector sends as
 *                             "Authorization: Bearer <token>"
 *   HANDBOOK_MCP_MODE          readwrite-drafts (default) or readonly
 *   HANDBOOK_MCP_PORT          listen port (default 3000)
 *   HANDBOOK_MCP_PATH          MCP route (default /mcp)
 */

import { createServer } from "node:http";
import { randomUUID, timingSafeEqual } from "node:crypto";
import { readFileSync, existsSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";
import { homedir } from "node:os";
import express from "express";
import { StreamableHTTPServerTransport } from "@modelcontextprotocol/sdk/server/streamableHttp.js";
import { isInitializeRequest } from "@modelcontextprotocol/sdk/types.js";
import { buildHandbookServer } from "./lib/handbook.mjs";

// Load KEY=VALUE lines from a file into process.env, without adding a
// dependency. Only fills variables that are not already set, so real
// environment variables and earlier files win.
function loadEnvFile(filepath) {
  if (!existsSync(filepath)) {
    return;
  }
  for (const line of readFileSync(filepath, "utf8").split(/\r?\n/)) {
    if (line.trimStart().startsWith("#")) {
      continue;
    }
    const match = line.match(/^\s*([A-Za-z0-9_]+)\s*=\s*(.*)\s*$/);
    if (!match) {
      continue;
    }
    let value = match[2].trim();
    if ((value.startsWith('"') && value.endsWith('"'))
        || (value.startsWith("'") && value.endsWith("'"))) {
      value = value.slice(1, -1);
    }
    if (process.env[match[1]] === undefined) {
      process.env[match[1]] = value;
    }
  }
}

// Secrets are read from, in order of precedence: real environment variables,
// then a local .env in the deployed folder (dev/manual), then a persistent
// ~/.handbook-mcp.env in the home directory that survives Git redeploys.
const scriptdir = dirname(fileURLToPath(import.meta.url));
loadEnvFile(join(scriptdir, ".env"));
loadEnvFile(join(homedir(), ".handbook-mcp.env"));

const baseUrl = (process.env.HANDBOOK_BASE_URL || "").replace(/\/+$/, "");
const wstoken = process.env.HANDBOOK_WSTOKEN || "";
const authToken = process.env.HANDBOOK_MCP_AUTH_TOKEN || "";
const mode = process.env.HANDBOOK_MCP_MODE === "readonly" ? "readonly" : "readwrite-drafts";
// Infomaniak (and most managed Node hosts) inject the port via PORT.
const port = Number(process.env.PORT || process.env.HANDBOOK_MCP_PORT || 3000);
const mcpPath = process.env.HANDBOOK_MCP_PATH || "/mcp";

if (!baseUrl || !wstoken) {
  console.error("handbook-mcp: set HANDBOOK_BASE_URL and HANDBOOK_WSTOKEN.");
  process.exit(1);
}
if (!authToken || authToken.length < 24) {
  console.error("handbook-mcp: set HANDBOOK_MCP_AUTH_TOKEN to a long random string (>= 24 chars).");
  process.exit(1);
}

/** Constant-time bearer-token check; never logs the token. */
function authorized(req) {
  const header = req.headers.authorization || "";
  const match = header.match(/^Bearer\s+(.+)$/i);
  if (!match) {
    return false;
  }
  const provided = Buffer.from(match[1]);
  const expected = Buffer.from(authToken);
  return provided.length === expected.length && timingSafeEqual(provided, expected);
}

/** Minimal structured log line — never includes tokens or content bodies. */
function log(fields) {
  console.error(JSON.stringify({ ts: new Date().toISOString(), ...fields }));
}

const app = express();
app.use(express.json({ limit: "4mb" }));

// Health check: reveals nothing sensitive, needs no auth.
app.get("/health", (_req, res) => {
  res.json({ status: "ok", mode });
});

// Active transports keyed by MCP session id.
const transports = {};

app.post(mcpPath, async (req, res) => {
  if (!authorized(req)) {
    log({ event: "auth_rejected", path: mcpPath });
    res.status(401).json(rpcError("Unauthorized"));
    return;
  }

  const sessionId = req.headers["mcp-session-id"];
  let transport;

  if (sessionId && transports[sessionId]) {
    transport = transports[sessionId];
  } else if (!sessionId && isInitializeRequest(req.body)) {
    transport = new StreamableHTTPServerTransport({
      sessionIdGenerator: () => randomUUID(),
      onsessioninitialized: (sid) => {
        transports[sid] = transport;
        log({ event: "session_open", session: sid });
      },
    });
    transport.onclose = () => {
      if (transport.sessionId) {
        delete transports[transport.sessionId];
        log({ event: "session_close", session: transport.sessionId });
      }
    };
    const server = buildHandbookServer({ baseUrl, token: wstoken, mode });
    await server.connect(transport);
  } else {
    res.status(400).json(rpcError("Bad Request: no valid session"));
    return;
  }

  await transport.handleRequest(req, res, req.body);
});

// SSE stream + session teardown reuse one authenticated handler.
const sessionRequest = async (req, res) => {
  if (!authorized(req)) {
    res.status(401).json(rpcError("Unauthorized"));
    return;
  }
  const sessionId = req.headers["mcp-session-id"];
  if (!sessionId || !transports[sessionId]) {
    res.status(400).send("Invalid or missing session id");
    return;
  }
  await transports[sessionId].handleRequest(req, res);
};
app.get(mcpPath, sessionRequest);
app.delete(mcpPath, sessionRequest);

/** JSON-RPC-shaped error body. */
function rpcError(message) {
  return { jsonrpc: "2.0", error: { code: -32000, message }, id: null };
}

const httpServer = createServer(app);
httpServer.listen(port, () => {
  log({ event: "listening", port, path: mcpPath, mode, base: baseUrl });
});

// Graceful shutdown.
function shutdown(signal) {
  log({ event: "shutdown", signal });
  for (const sid of Object.keys(transports)) {
    try {
      transports[sid].close();
    } catch {
      // Ignore teardown errors.
    }
  }
  httpServer.close(() => process.exit(0));
  // Force-exit if connections linger.
  setTimeout(() => process.exit(0), 5000).unref();
}
process.on("SIGINT", () => shutdown("SIGINT"));
process.on("SIGTERM", () => shutdown("SIGTERM"));
