# Deploying the Handbook MCP server (remote, for ChatGPT)

This documents the production deployment of `http-server.mjs` — the remote
Streamable-HTTP transport that a ChatGPT (Enterprise/Edu) custom connector
talks to. It reflects the live setup on **Infomaniak Node.js hosting**; the
same shape applies to any managed Node host or a small VPS.

The local stdio adapter (`server.mjs`, for Claude Desktop/Code) needs none of
this — see `README.md`.

## Overview

```
ChatGPT (Enterprise custom connector, Bearer token)
      │  HTTPS  https://mcp.europaschule.eu/mcp
      ▼
Node app (http-server.mjs)  ── holds the Moodle token, never exposes it
      │  REST + wstoken
      ▼
Moodle web services (local_handbook_* functions)
```

Two independent secrets, never confused:

| Secret | What it is | Where it lives |
|---|---|---|
| `HANDBOOK_WSTOKEN` | Moodle web-service token of the `handbook-ai` account | server-side only (`.env`), never sent to ChatGPT |
| `HANDBOOK_MCP_AUTH_TOKEN` | random Bearer key the connector must present | server `.env` **and** pasted into the ChatGPT connector |

The Moodle account must hold only `apiaccess`, `view`, `viewhistory`, `edit`
— never `review`/`approve`/`publish`/`manage`. There is no approve/publish
tool, by design.

## 1. Moodle side (once)

See `../docs/API.md`. Enable web services + REST, create the `handbook-ai`
account with the four capabilities above, authorise it on the "Institutional
Handbook API" service, and create a token → that is `HANDBOOK_WSTOKEN`.

## 2. Host the Node app (Infomaniak Node.js site)

1. Create a **Node.js site** on a subdomain, e.g. `mcp.example.eu` (Node 18+;
   we run 24). Point the subdomain's DNS at **that site's own IP** (Advanced →
   General shows the IPv4/IPv6 — do not reuse another site's IP).
2. Configure it as a **Custom → Git** deployment (Reset the site if it was
   created with the sample app):
   - **Repository:** the public Git URL of this repo.
   - **Branch:** `main`.
   - **Build command:** `cd mcp && npm install`
   - **Run command:** `cd mcp && npm start` (→ `node http-server.mjs`)
   - **Listening port:** `3000` (the platform also injects `PORT`; the server
     honours it).
   The builder deploys the `mcp/` folder's contents to the site root.
3. Install a free **Let's Encrypt** certificate for the subdomain (after DNS
   has propagated — `nslookup mcp.example.eu` should return the site's IP).

## 3. Secrets (`.env`)

The server reads secrets from, in order: real environment variables → a local
`.env` next to the app → **`~/.handbook-mcp.env`** in the home directory.

Because a Git redeploy can overwrite the deployed folder, put the durable copy
in your home directory (it survives every redeploy). Over SSH:

```
cat > ~/.handbook-mcp.env <<'EOF'
HANDBOOK_BASE_URL=https://learn.example.eu
HANDBOOK_WSTOKEN=<moodle service-account token>
HANDBOOK_MCP_AUTH_TOKEN=<openssl rand -hex 32>
HANDBOOK_MCP_MODE=readwrite-drafts
EOF
chmod 600 ~/.handbook-mcp.env
```

Generate the auth token with `openssl rand -hex 32`. Then **Restart** the app.

Verify: `https://mcp.example.eu/health` → `{"status":"ok","mode":"readwrite-drafts"}`.
The startup log shows `{"event":"listening",...}`; a bad/absent connector token
logs `{"event":"auth_rejected",...}`.

## 4. ChatGPT connector (Enterprise/Edu)

Settings → Connectors → create a custom connector / app:

- **Server URL:** `https://mcp.example.eu/mcp`
- **Authentication:** Access token / API key · **Header scheme:** Bearer
- The token is entered in the **per-chat "Connect" dialog** (the create form
  has no token field) — paste `HANDBOOK_MCP_AUTH_TOKEN` there.

Attach the connector to a ChatGPT **Project** and paste the agent operating
rules (read before drafting, dry-run, explicit permission before writing,
never approve/publish, group into change sets, stop on conflicts).

## 5. Updating

Push to `main`, then in the Infomaniak panel click **Build** (it runs
`git pull` + `cd mcp && npm install` and restarts). `~/.handbook-mcp.env`
persists across updates. If you ever change tool schemas, use the connector's
"refresh actions" in ChatGPT.

## Rollback / troubleshooting

- **App won't start, `set HANDBOOK_BASE_URL…` in the log** → secrets not found;
  check `~/.handbook-mcp.env` exists, is readable, and has the three values.
- **`/health` shows a maintenance page** → the app is crash-looping (see log)
  or website maintenance mode is on (Manage menu).
- **ChatGPT shows "no actions"** → the connector token wasn't entered in the
  Connect dialog, or it doesn't match `HANDBOOK_MCP_AUTH_TOKEN`
  (`auth_rejected` in the log).
- **SSL fails** → the subdomain points at the wrong IP; fix DNS, wait for
  propagation, retry Let's Encrypt.
- **Roll back code** → in the panel, redeploy a previous commit (or revert on
  `main` and Build). To take the service offline, **Stop** the app or enable
  website maintenance. Secrets in `~/.handbook-mcp.env` are unaffected.
