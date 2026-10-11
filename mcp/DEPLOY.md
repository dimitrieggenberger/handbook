# Deploying the Handbook MCP server (remote, for ChatGPT and Claude)

This documents the production deployment of `http-server.mjs` — the remote
Streamable-HTTP transport that ChatGPT (Enterprise/Edu) custom connectors and
claude.ai / Claude Desktop custom connectors talk to. It reflects the live
setup on **Infomaniak Node.js hosting**; the same shape applies to any managed
Node host or a small VPS.

The local stdio adapter (`server.mjs`, for Claude Desktop/Code on one machine)
needs none of this — see `README.md`.

## Overview

```
ChatGPT (custom connector, shared key as Bearer token)
claude.ai / Claude Desktop (custom connector, OAuth 2.1)
      │  HTTPS  https://mcp.europaschule.eu/mcp
      ▼
Node app (http-server.mjs)  ── holds the Moodle token, never exposes it
      │  REST + wstoken
      ▼
Moodle web services (local_handbook_* functions)
```

Two ways in, one authority. A client either presents the shared key directly
(`Authorization: Bearer <HANDBOOK_MCP_AUTH_TOKEN>`, what ChatGPT does) or goes
through the built-in OAuth 2.1 server (`lib/oauth.mjs`): it registers itself,
sends the person to a consent page where that same shared key is typed once,
and from then on holds its own short-lived tokens. Both paths run the tools
through the single `handbook-ai` Moodle account; neither can approve or
publish.

Secrets, never confused:

| Secret | What it is | Where it lives |
|---|---|---|
| `HANDBOOK_WSTOKEN` | Moodle web-service token of the `handbook-ai` account | server-side only (`.env`), never sent to any client |
| `HANDBOOK_MCP_AUTH_TOKEN` | the shared connector key: Bearer for ChatGPT, typed on the OAuth consent page for Claude; also seeds the OAuth signing key | server `.env`, pasted into the ChatGPT connector, given to the people who may authorise Claude |
| `HANDBOOK_MCP_OAUTH_SECRET` (optional) | separate secret for signing OAuth tokens; without it the signing key is derived from the connector key | server `.env` |

OAuth state (registered clients and revoked tokens) lives in
`~/.handbook-mcp-oauth.json` (override with `HANDBOOK_MCP_OAUTH_STATE`),
next to the secrets, so it survives redeploys. Tokens themselves are signed
and stateless: a restart does not disconnect anyone. Rotating
`HANDBOOK_MCP_AUTH_TOKEN` invalidates every OAuth token at once (unless a
separate `HANDBOOK_MCP_OAUTH_SECRET` is set), so it is the one knob for
revoking all access.

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
HANDBOOK_MCP_PUBLIC_URL=https://mcp.example.eu
HANDBOOK_MCP_MODE=readwrite-drafts
EOF
chmod 600 ~/.handbook-mcp.env
```

Generate the auth token with `openssl rand -hex 32`. `HANDBOOK_MCP_PUBLIC_URL`
is the public https origin of the app (no path): it becomes the OAuth issuer
and enables the OAuth endpoints. Leave it out and only the shared key works.
Then **Restart** the app.

Verify: `https://mcp.example.eu/health` →
`{"status":"ok","mode":"readwrite-drafts","oauth":true}`, and
`https://mcp.example.eu/.well-known/oauth-authorization-server` returns JSON.
The startup log shows `{"event":"listening",...,"oauth":true}`; a bad/absent
connector token logs `{"event":"auth_rejected",...}`.

## 4. ChatGPT connector (Enterprise/Edu)

Settings → Connectors → create a custom connector / app:

- **Server URL:** `https://mcp.example.eu/mcp`
- **Authentication:** Access token / API key · **Header scheme:** Bearer
- The token is entered in the **per-chat "Connect" dialog** (the create form
  has no token field) — paste `HANDBOOK_MCP_AUTH_TOKEN` there.

Attach the connector to a ChatGPT **Project** and paste the agent operating
rules (read before drafting, dry-run, explicit permission before writing,
never approve/publish, group into change sets, stop on conflicts).

## 4b. claude.ai and Claude Desktop connector (OAuth)

claude.ai custom connectors do not take a static header; they use the MCP
authorization flow, which this server now provides itself.

- Settings → Connectors → **Add custom connector**: name "Manual
  Institucional", **Remote MCP server URL** `https://mcp.example.eu/mcp`.
  Leave the OAuth client ID and secret empty: the server supports dynamic
  client registration and Claude registers itself.
- Click **Connect**. Claude opens the consent page of this server; the person
  types the connector key (`HANDBOOK_MCP_AUTH_TOKEN`) once and presses
  **Autorizar**. From then on Claude holds a refresh token (30 days, rotated
  on every use) and one-hour access tokens; the key is not stored in Claude.
- Claude Code on a machine works the same way (`claude mcp add --transport
  http handbook https://mcp.example.eu/mcp`, then `/mcp` to authenticate),
  or with the shared key as a header (`--header "Authorization: Bearer …"`).

Who may authorise is whoever knows the key. To cut everyone off, change
`HANDBOOK_MCP_AUTH_TOKEN` and restart. To cut one client off, delete its
entry from `~/.handbook-mcp-oauth.json` (its `client_name` names it) and
restart; its next refresh fails and Claude asks to reconnect.

Endpoints the server adds when `HANDBOOK_MCP_PUBLIC_URL` is set:
`/.well-known/oauth-authorization-server`,
`/.well-known/oauth-protected-resource/mcp`, `/authorize`, `/oauth/approve`
(the consent form), `/token`, `/register`, `/revoke`. All are rate limited.
`npm run test:oauth` exercises the whole flow locally.

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
- **Claude says the server does not support authentication / cannot
  connect** → `HANDBOOK_MCP_PUBLIC_URL` is missing or is not the exact public
  https origin (`/health` shows `"oauth":false`), or the metadata URL is
  blocked by the host. `curl https://mcp.example.eu/.well-known/oauth-authorization-server`
  must return JSON.
- **The consent page says the key does not match** → same cause as the
  ChatGPT case: the person typed something other than
  `HANDBOOK_MCP_AUTH_TOKEN`.
- **SSL fails** → the subdomain points at the wrong IP; fix DNS, wait for
  propagation, retry Let's Encrypt.
- **Roll back code** → in the panel, redeploy a previous commit (or revert on
  `main` and Build). To take the service offline, **Stop** the app or enable
  website maintenance. Secrets in `~/.handbook-mcp.env` are unaffected.

## Keeping the deployment up to date (auto-deploy)

The panel's **Build** button re-runs `npm install` and restarts, but it does
**not** pull new commits from Git. So after you push to `main`, update the
deployed checkout explicitly.

### Deploy (canonical, command-based)

Push, then update the deployed checkout over SSH:

```
git push origin main          # from your machine

# then, in the Infomaniak SSH console:
cd ~/sites/mcp.europaschule.eu && git fetch origin && git reset --hard origin/main
```

That one line fetches and hard-resets the deployed files to `origin/main`.
Once this commit is deployed the repo ships a wrapper that does the same:

```
bash ~/sites/mcp.europaschule.eu/mcp/redeploy.sh    # fetch + reset to origin/main
```

**Then restart the process** so it loads the new code — a plain pull leaves the
running app on the old files:

- **Panel:** click **Restart** in the Node.js control panel (always works).
- **Command (try first, to skip the panel):**
  `touch ~/sites/mcp.europaschule.eu/tmp/restart.txt` — restarts the app if it
  runs under Passenger; harmless otherwise. If the log shows no fresh
  `{"event":"listening",...}`, use the panel Restart.

Verify: `grep -c upsert_changeset_metadata mcp/lib/handbook.mjs` (prints `1`
once that tool is deployed), then `GET /health` →
`{"status":"ok","mode":"readwrite-drafts"}` with a fresh `listening` log line.

### Optional: one-command deploy via a webhook

If the site's deployment settings expose a **deploy webhook URL**, trigger a
deploy with a single command instead of the SSH steps:

```
curl -fsS -X POST "<INFOMANIAK_DEPLOY_WEBHOOK_URL>"
```

To make it fully automatic, add that URL as a GitHub webhook (**Settings →
Webhooks → Add webhook**, Content-type `application/json`, *Just the push
event*) so a push to `main` pulls, builds and restarts on its own.

### After any deploy: refresh the connector

The MCP client (ChatGPT custom connector) caches the tool list. After the
server restarts with new tools, remove and re-add the connector (same
`/mcp` URL + Bearer token) and start a new chat so it re-reads them.

## Granting the editorial propose capabilities

Beyond `apiaccess`, `view`, `viewhistory`, `edit`, the `handbook-ai` account's
role must also hold the propose capabilities for the editorial tools to work:

| Capability | Enables |
|---|---|
| `local/handbook:apiproposemetadata` | metadata/fiche patches + slug rename |
| `local/handbook:apiproposerelations` | page relation edits |

New pages use `edit` (already granted). These are **propose-only** — they never
grant review, approval, application or publication; a human still approves and
applies every change in Moodle. Grant them in **Site administration → Users →
Permissions → Define roles →** the service-account role → set each to **Allow**.
They ship with no default assignment, so they are off until you add them.

## Consultation instances (read-only Q&A connectors)

Phase 3 of the multi-audience handbook adds a second KIND of connector:
consultation ("+Manual") instances that answer questions and can change
nothing. Each instance is one extra Node app (or one extra port/path on the
same host) with three differences from the editorial deployment:

1. **Mode**: `HANDBOOK_MCP_MODE=consult` — registers only six lookup tools
   (search, get page, context index, categories, page lists, relations) and
   ships answer-structure instructions in Spanish (direct answer → detail →
   source citation; "not covered" answers; no write offers).
2. **Moodle account**: a dedicated `handbook-consulta-*` account authorised
   on the **"Institutional Handbook Consultation (read-only)"** service
   (`local_handbook_readonly`), NOT on the editorial service. Grant it:
   - Staff consultation (teachers): `apiaccess` + `view` → it reads the
     whole handbook (aiaccess rules still apply per page).
   - Per-audience consultation (e.g. families): `apiaccess` ONLY — no
     `view` — and set the audience's profile-field value (e.g. `city`)
     on the account itself. The plugin then filters every function to
     that audience's tagged, published, non-under-review pages,
     server-side. The account IS the audience boundary.
3. **Secrets**: its own `HANDBOOK_WSTOKEN` (token for that account on the
   readonly service) and its own `HANDBOOK_MCP_AUTH_TOKEN`.

Example `.env` for a teachers' consultation instance:

```
HANDBOOK_BASE_URL=https://moodle.europaschule.eu
HANDBOOK_WSTOKEN=<token of handbook-consulta on local_handbook_readonly>
HANDBOOK_MCP_AUTH_TOKEN=<long random string, distinct from the editorial one>
HANDBOOK_MCP_MODE=consult
HANDBOOK_MCP_PATH=/consulta
```

In ChatGPT, add it as a separate connector ("+Manual") pointing at the
consult path with its own Bearer token. The editorial connector is
unchanged; both can run side by side from the same Git checkout.
