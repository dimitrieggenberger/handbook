#!/usr/bin/env node
/**
 * Remote Streamable-HTTP entry point for the Handbook MCP adapter.
 *
 * Exposes the same tools as the local stdio server (lib/handbook.mjs) over an
 * authenticated HTTPS endpoint so remote MCP clients can reach them
 * (spec 36.7). There is no publish tool, by design.
 *
 * Two ways in, one authority:
 *   - Shared key: "Authorization: Bearer <HANDBOOK_MCP_AUTH_TOKEN>". This is
 *     what a ChatGPT custom connector sends.
 *   - OAuth 2.1 (lib/oauth.mjs): clients that discover authorization through
 *     the MCP spec (claude.ai and Claude Desktop connectors, MCP Inspector)
 *     register themselves, send a person to the consent page, where the same
 *     shared key is typed once, and then hold their own tokens.
 *   Either way the tools run through the single Moodle service account.
 *
 * Secrets, never confused:
 *   - HANDBOOK_WSTOKEN         Moodle token; stays server-side, never sent to
 *                             the client, never logged.
 *   - HANDBOOK_MCP_AUTH_TOKEN  the shared connector key (Bearer, and the
 *                             consent page); also seeds the OAuth signing key.
 *
 * Configuration (environment):
 *   HANDBOOK_BASE_URL          e.g. https://learn.europaschule.eu
 *   HANDBOOK_WSTOKEN           authorised service-account token
 *   HANDBOOK_MCP_AUTH_TOKEN    long random string (>= 24 chars)
 *   HANDBOOK_MCP_PUBLIC_URL    public https origin of this app, e.g.
 *                             https://mcp.europaschule.eu — enables OAuth.
 *                             Without it only the shared key works.
 *   HANDBOOK_MCP_OAUTH_SECRET  optional separate token-signing secret
 *   HANDBOOK_MCP_OAUTH_STATE   optional path of the OAuth state file
 *                             (default ~/.handbook-mcp-oauth.json)
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
import { mcpAuthRouter, getOAuthProtectedResourceMetadataUrl } from "@modelcontextprotocol/sdk/server/auth/router.js";
import { requireBearerAuth } from "@modelcontextprotocol/sdk/server/auth/middleware/bearerAuth.js";
import { InvalidTokenError } from "@modelcontextprotocol/sdk/server/auth/errors.js";
import { buildHandbookServer } from "./lib/handbook.mjs";
import { HandbookOAuthProvider, SCOPE, deriveSigningKey } from "./lib/oauth.mjs";

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
const publicUrl = (process.env.HANDBOOK_MCP_PUBLIC_URL || "").replace(/\/+$/, "");
const mode = process.env.HANDBOOK_MCP_MODE === "readonly" ? "readonly" : "readwrite-drafts";
// Infomaniak (and most managed Node hosts) inject the port via PORT.
const port = Number(process.env.PORT || process.env.HANDBOOK_MCP_PORT || 3000);
const mcpPath = process.env.HANDBOOK_MCP_PATH || "/mcp";
const approvePath = "/oauth/approve";

if (!baseUrl || !wstoken) {
  console.error("handbook-mcp: set HANDBOOK_BASE_URL and HANDBOOK_WSTOKEN.");
  process.exit(1);
}
if (!authToken || authToken.length < 24) {
  console.error("handbook-mcp: set HANDBOOK_MCP_AUTH_TOKEN to a long random string (>= 24 chars).");
  process.exit(1);
}

/** Minimal structured log line — never includes tokens or content bodies. */
function log(fields) {
  console.error(JSON.stringify({ ts: new Date().toISOString(), ...fields }));
}

/** Constant-time comparison with the shared connector key. */
function isSharedKey(token) {
  const provided = Buffer.from(token);
  const expected = Buffer.from(authToken);
  return provided.length === expected.length && timingSafeEqual(provided, expected);
}

const app = express();
// One reverse proxy (Passenger / the host's front end) sits in front of us;
// the SDK's rate limiters key on the client IP it forwards.
app.set("trust proxy", 1);
app.use(express.json({ limit: "4mb" }));

// Health check: reveals nothing sensitive, needs no auth.
app.get("/health", (_req, res) => {
  res.json({ status: "ok", mode, oauth: Boolean(publicUrl) });
});

// ---- authorization -------------------------------------------------------

let oauth = null;
let resourceMetadataUrl;
if (publicUrl) {
  oauth = new HandbookOAuthProvider({
    signingKey: deriveSigningKey(authToken, process.env.HANDBOOK_MCP_OAUTH_SECRET || ""),
    connectorKey: authToken,
    statePath: process.env.HANDBOOK_MCP_OAUTH_STATE || join(homedir(), ".handbook-mcp-oauth.json"),
    approvePath,
    log,
  });
  const issuerUrl = new URL(publicUrl);
  const resourceServerUrl = new URL(mcpPath, publicUrl);
  resourceMetadataUrl = getOAuthProtectedResourceMetadataUrl(resourceServerUrl);
  // /.well-known/oauth-authorization-server, /.well-known/oauth-protected-resource<mcpPath>,
  // /authorize, /token, /register, /revoke.
  app.use(mcpAuthRouter({
    provider: oauth,
    issuerUrl,
    resourceServerUrl,
    scopesSupported: [SCOPE],
    resourceName: "Manual Institucional EuropaSchule",
  }));
  // Some clients look for the protected-resource document at the root path.
  app.get("/.well-known/oauth-protected-resource", (_req, res) => {
    res.redirect(307, resourceMetadataUrl);
  });
  app.post(approvePath, express.urlencoded({ extended: false }), oauth.approveHandler());
} else {
  log({ event: "oauth_disabled", reason: "HANDBOOK_MCP_PUBLIC_URL not set; shared key only" });
}

/** Shared key first, then OAuth access tokens. Rejections are logged. */
const verifier = {
  async verifyAccessToken(token) {
    if (isSharedKey(token)) {
      return { token, clientId: "shared-key", scopes: [SCOPE], expiresAt: Math.floor(Date.now() / 1000) + 3600, extra: { auth: "shared-key" } };
    }
    if (oauth) {
      try {
        return await oauth.verifyAccessToken(token);
      } catch (error) {
        log({ event: "auth_rejected", path: mcpPath, reason: error.message });
        throw error;
      }
    }
    log({ event: "auth_rejected", path: mcpPath });
    throw new InvalidTokenError("Unauthorized");
  },
};
const authenticate = requireBearerAuth({ verifier, resourceMetadataUrl });

// ---- MCP transport -------------------------------------------------------

// Active transports keyed by MCP session id.
const transports = {};

app.post(mcpPath, authenticate, async (req, res) => {
  const sessionId = req.headers["mcp-session-id"];
  let transport;

  if (sessionId && transports[sessionId]) {
    transport = transports[sessionId];
  } else if (!sessionId && isInitializeRequest(req.body)) {
    transport = new StreamableHTTPServerTransport({
      sessionIdGenerator: () => randomUUID(),
      onsessioninitialized: (sid) => {
        transports[sid] = transport;
        log({ event: "session_open", session: sid, auth: req.auth?.extra?.auth || "unknown", client: req.auth?.clientId || "" });
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
  const sessionId = req.headers["mcp-session-id"];
  if (!sessionId || !transports[sessionId]) {
    res.status(400).send("Invalid or missing session id");
    return;
  }
  await transports[sessionId].handleRequest(req, res);
};
app.get(mcpPath, authenticate, sessionRequest);
app.delete(mcpPath, authenticate, sessionRequest);

/** JSON-RPC-shaped error body. */
function rpcError(message) {
  return { jsonrpc: "2.0", error: { code: -32000, message }, id: null };
}

const httpServer = createServer(app);
httpServer.listen(port, () => {
  log({ event: "listening", port, path: mcpPath, mode, base: baseUrl, oauth: Boolean(publicUrl), issuer: publicUrl || null });
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
