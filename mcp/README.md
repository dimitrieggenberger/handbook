# handbook-mcp — MCP adapter for the Manual Institucional

A small Model Context Protocol server that exposes the `local_handbook`
external API (see `../docs/API.md`) as tools for Claude, ChatGPT and other
MCP clients (spec §18). It is a separate deliverable: not part of the Moodle
plugin ZIP, deployed wherever the AI client runs.

The adapter can only do what its Moodle token allows. Use the restricted
"Institutional Handbook API" service with a service account that has
`apiaccess` + `view` + `edit` and **never** `publish` — there is no publish
tool, by design (§17.3): agents draft and file findings, humans review and
publish.

## Setup

Requirements: Node.js 18+.

```bash
cd mcp
npm install
```

Configuration via environment variables:

- `HANDBOOK_BASE_URL` — e.g. `https://learn.europaschule.eu`
- `HANDBOOK_WSTOKEN` — the service-account token (never commit it)

## Claude Code

```bash
claude mcp add handbook \
  -e HANDBOOK_BASE_URL=https://learn.europaschule.eu \
  -e HANDBOOK_WSTOKEN=YOUR_TOKEN \
  -- node "E:/Codex Moodle Plugins/Handbook/mcp/server.mjs"
```

## Claude Desktop

Add to `claude_desktop_config.json` (Settings → Developer → Edit config):

```json
{
  "mcpServers": {
    "handbook": {
      "command": "node",
      "args": ["E:/Codex Moodle Plugins/Handbook/mcp/server.mjs"],
      "env": {
        "HANDBOOK_BASE_URL": "https://learn.europaschule.eu",
        "HANDBOOK_WSTOKEN": "YOUR_TOKEN"
      }
    }
  }
}
```

## ChatGPT and claude.ai (web)

Both need the server reachable over HTTPS (remote MCP) rather than stdio.
Options: host this adapter behind an HTTP↔stdio bridge (e.g. `supergateway`)
on a small VPS or the Moodle host, or add an HTTP transport later. For
ChatGPT, a Custom GPT with Actions pointing directly at the REST API
(`../docs/API.md`) is the simpler route today.

## Tools

Read: `handbook_search`, `handbook_get_page`, `handbook_list_categories`,
`handbook_list_pages`, `handbook_list_changes`, `handbook_get_related_pages`,
`handbook_list_revisions`, `handbook_get_revision`.

Draft (workflow-safe): `handbook_create_page_draft`, `handbook_create_draft`
(with expected-base check), `handbook_update_draft` (mandatory concurrency
token), `handbook_submit_for_review`.

Findings (advisory): `handbook_record_finding`, `handbook_list_open_findings`.

Agent operating rules (spec §18.3) worth putting in your system prompt or
project instructions: read the current published page before proposing
changes; treat metadata, scope, authority and dates as part of the meaning;
always provide a change summary; cite pages and sections in findings;
distinguish confirmed from possible contradictions; respect intentional
modality differences.
