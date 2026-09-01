#!/usr/bin/env node
/**
 * Local stdio entry point for the WordPress MCP adapter.
 *
 * All tool logic lives in lib/wordpress.mjs so this transport and the remote
 * HTTP transport (http-server.mjs) advertise identical tools. The adapter
 * holds the WordPress credentials; use a service account with the CONTRIBUTOR
 * role so WordPress itself blocks publishing server-side. There is no publish
 * tool: authority ends at submitting proposal drafts for review.
 *
 * Configuration (environment):
 *   WORDPRESS_BASE_URL      e.g. https://www.europaschule.eu
 *   WORDPRESS_APP_USER      WordPress username of the service account
 *   WORDPRESS_APP_PASSWORD  an Application Password of that account
 *   WORDPRESS_MCP_MODE      readwrite-drafts (default) or readonly
 */

import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { buildWordPressServer } from "./lib/wordpress.mjs";

const baseUrl = (process.env.WORDPRESS_BASE_URL || "").replace(/\/+$/, "");
const user = process.env.WORDPRESS_APP_USER || "";
const appPassword = process.env.WORDPRESS_APP_PASSWORD || "";
const mode = process.env.WORDPRESS_MCP_MODE === "readonly" ? "readonly" : "readwrite-drafts";

if (!baseUrl || !user || !appPassword) {
  console.error("wordpress-mcp: set WORDPRESS_BASE_URL, WORDPRESS_APP_USER and WORDPRESS_APP_PASSWORD.");
  process.exit(1);
}

const server = buildWordPressServer({ baseUrl, user, appPassword, mode });
const transport = new StdioServerTransport();
await server.connect(transport);
console.error(`wordpress-mcp connected to ${baseUrl} (mode: ${mode})`);
