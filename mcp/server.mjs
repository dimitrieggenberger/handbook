#!/usr/bin/env node
/**
 * Local stdio entry point for the Handbook MCP adapter.
 *
 * All tool logic lives in lib/handbook.mjs so this transport and any future
 * remote HTTP transport advertise identical tools (spec 18, 36.7). The adapter
 * holds the Moodle token; the plugin's capability model decides what it may do.
 * There is no publish tool: authority ends at submitting drafts for review.
 *
 * Configuration (environment):
 *   HANDBOOK_BASE_URL   e.g. https://learn.europaschule.eu
 *   HANDBOOK_WSTOKEN    token of the authorised service account
 *   HANDBOOK_MCP_MODE   readwrite-drafts (default) or readonly
 */

import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { buildHandbookServer } from "./lib/handbook.mjs";

const baseUrl = (process.env.HANDBOOK_BASE_URL || "").replace(/\/+$/, "");
const token = process.env.HANDBOOK_WSTOKEN || "";
const mode = process.env.HANDBOOK_MCP_MODE === "readonly" ? "readonly" : "readwrite-drafts";

if (!baseUrl || !token) {
  console.error("handbook-mcp: set HANDBOOK_BASE_URL and HANDBOOK_WSTOKEN.");
  process.exit(1);
}

const server = buildHandbookServer({ baseUrl, token, mode });
const transport = new StdioServerTransport();
await server.connect(transport);
console.error(`handbook-mcp connected to ${baseUrl} (mode: ${mode})`);
