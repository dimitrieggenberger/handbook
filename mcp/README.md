# handbook-mcp — MCP adapter for the Manual Institucional

A small Model Context Protocol server that exposes the `local_handbook`
external API (see `../docs/API.md`) as tools for Claude, ChatGPT and other
MCP clients (spec §18). It is a separate deliverable: not part of the Moodle
plugin ZIP, deployed wherever the AI client runs.

It also ships a sibling adapter for the public **WordPress** site
(`lib/wordpress.mjs`, stdio entry `wordpress-server.mjs`) with the same
workflow guarantee, so one agent session can keep the website and the
handbook harmonized — see the WordPress section below and
`../docs/WORDPRESS_HARMONIZATION.md` for the cross-checking workflow.

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
- `HANDBOOK_MCP_MODE` — `readwrite-drafts` (default) or `readonly`. In
  `readonly` mode no draft or change-set write tools are registered.

All tool logic lives in `lib/handbook.mjs` (the REST client and tool
registration); `server.mjs` is just the local stdio entry point that consumes
it, so a future remote HTTP transport advertises identical tools.

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
`handbook_list_revisions`, `handbook_get_revision`,
`handbook_get_context_index` (compact whole-handbook index, no content),
`handbook_get_working_page` (a page's current working draft).

Draft (workflow-safe): `handbook_create_page_draft`, `handbook_create_draft`
(with expected-base check), `handbook_update_draft` (mandatory concurrency
token), `handbook_submit_for_review`.

Change sets — grouped multi-page proposals (workflow-safe):
`handbook_create_change_set`, `handbook_get_change_set`,
`handbook_list_change_sets`, `handbook_upsert_change_set_draft` (conservative;
reuses the same editable draft, returns conflicts instead of overwriting),
`handbook_submit_change_set_for_review`.

Findings (advisory): `handbook_record_finding`, `handbook_list_open_findings`.

There is no approve or publish tool, by design — humans review and publish.

Agent operating rules (spec §18.3) worth putting in your system prompt or
project instructions: read the current published page before proposing
changes; treat metadata, scope, authority and dates as part of the meaning;
always provide a change summary; cite pages and sections in findings;
distinguish confirmed from possible contradictions; respect intentional
modality differences.

## WordPress adapter

Same workflow, applied to the public website via the core WordPress REST
API (no WordPress plugin needed): agents read published content and stage
**proposal drafts**; human editors apply and publish in wp-admin. Two
enforcement layers: the adapter has no publish/delete tool and refuses to
touch anything that is not a draft/pending proposal, and the service
account holds the WordPress **Contributor** role, which cannot publish or
edit live content even in principle. Setup (service account, Application
Password) is in `../docs/WORDPRESS_HARMONIZATION.md` §5.

Configuration via environment variables:

- `WORDPRESS_BASE_URL` — e.g. `https://www.europaschule.eu`
- `WORDPRESS_APP_USER` — the service account's username
- `WORDPRESS_APP_PASSWORD` — an Application Password of that account
- `WORDPRESS_MCP_MODE` — `readwrite-drafts` (default) or `readonly`

Claude Code:

```bash
claude mcp add wordpress \
  -e WORDPRESS_BASE_URL=https://www.europaschule.eu \
  -e WORDPRESS_APP_USER=handbook-ai \
  -e WORDPRESS_APP_PASSWORD=YOUR_APP_PASSWORD \
  -- node "E:/Codex Moodle Plugins/Handbook/mcp/wordpress-server.mjs"
```

For Claude Desktop add a second `wordpress` entry beside `handbook` in
`claude_desktop_config.json` pointing at `wordpress-server.mjs` with the
three variables above. The remote HTTP endpoint (`http-server.mjs`)
serves the `wp_*` tools automatically on the same URL and Bearer token as
soon as the `WORDPRESS_*` secrets are configured (see `DEPLOY.md`), so
ChatGPT needs no second connector.

Tools — read: `wp_get_context_index`, `wp_search`, `wp_get_content`,
`wp_list_pages`, `wp_list_posts`, `wp_list_categories`, `wp_list_changes`
(incremental-sync counterpart of `handbook_list_changes`),
`wp_list_proposals`. Proposal (workflow-safe): `wp_create_proposal_draft`,
`wp_update_proposal_draft` (concurrency-checked), and
`wp_submit_proposal_for_review`. There is no publish tool, by design.
