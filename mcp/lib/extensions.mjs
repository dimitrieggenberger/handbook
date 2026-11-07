/**
 * Optional tool sets from other repositories, served on the same endpoint.
 *
 * The school may run only one Node site at mcp.europaschule.eu, so other
 * read-only connectors (first: coursedocs-mcp in the platform repository,
 * decision 0008 there) join this server instead of running on their own.
 *
 * HANDBOOK_MCP_EXTENSIONS is a comma-separated list of absolute paths to ES
 * modules. Each module exports
 *   name                          a short label for the logs
 *   prepare({ log }) -> { summary, registerTools(server) }
 * prepare() runs once at start and must throw on a bad configuration, so a
 * mistake stops the server instead of half-working. Each extension reads its
 * own secrets and talks to Moodle with its own account; this server only
 * does the authorisation in front of it.
 *
 * Without the variable nothing changes: no module is loaded.
 */

import { isAbsolute } from "node:path";
import { pathToFileURL } from "node:url";

export async function loadExtensions(spec, log = () => {}) {
  const paths = String(spec || "").split(",").map((item) => item.trim()).filter(Boolean);
  const extensions = [];
  for (const path of paths) {
    if (!isAbsolute(path)) {
      throw new Error(`HANDBOOK_MCP_EXTENSIONS: not an absolute path: ${path}`);
    }
    const module = await import(pathToFileURL(path).href);
    if (typeof module.prepare !== "function") {
      throw new Error(`HANDBOOK_MCP_EXTENSIONS: ${path} does not export prepare()`);
    }
    const name = String(module.name || path);
    const prepared = await module.prepare({ log: (fields) => log({ extension: name, ...fields }) });
    extensions.push({ name, ...prepared });
    log({ event: "extension_loaded", extension: name, ...(prepared.summary || {}) });
  }
  return extensions;
}
