#!/usr/bin/env node
/**
 * End-to-end smoke test of the remote transport's two ways in: the shared
 * key and the OAuth 2.1 flow (discovery, dynamic registration, consent page,
 * PKCE code exchange, refresh rotation, revocation, persistence across a
 * restart). Starts http-server.mjs on a free port with fake Moodle settings;
 * no Moodle call is made (tools are only listed, never invoked).
 *
 *   npm run test:oauth
 */

import { spawn } from "node:child_process";
import { createHash, randomBytes } from "node:crypto";
import { mkdtempSync, rmSync } from "node:fs";
import { tmpdir } from "node:os";
import { join, dirname } from "node:path";
import { fileURLToPath } from "node:url";

const here = dirname(fileURLToPath(import.meta.url));
const port = 3300 + Math.floor(Math.random() * 500);
const origin = `http://127.0.0.1:${port}`;
const key = "smoke-test-connector-key-0123456789abcdef";
const stateDir = mkdtempSync(join(tmpdir(), "handbook-mcp-"));
const env = {
  ...process.env,
  HANDBOOK_BASE_URL: "https://moodle.invalid",
  HANDBOOK_WSTOKEN: "not-used-by-this-test",
  HANDBOOK_MCP_AUTH_TOKEN: key,
  HANDBOOK_MCP_PUBLIC_URL: origin,
  HANDBOOK_MCP_OAUTH_STATE: join(stateDir, "oauth.json"),
  PORT: String(port),
};

let failures = 0;
function check(label, ok, detail = "") {
  console.log(`${ok ? "ok  " : "FAIL"} ${label}${ok || !detail ? "" : ` — ${detail}`}`);
  if (!ok) {
    failures++;
  }
}

let child;
async function start() {
  child = spawn(process.execPath, [join(here, "..", "http-server.mjs")], { env, stdio: ["ignore", "ignore", "pipe"] });
  const logs = [];
  child.stderr.on("data", (d) => logs.push(String(d)));
  for (let i = 0; i < 50; i++) {
    try {
      const r = await fetch(`${origin}/health`);
      if (r.ok) {
        return logs;
      }
    } catch {
      // not up yet
    }
    await new Promise((r) => setTimeout(r, 100));
  }
  throw new Error(`server did not start:\n${logs.join("")}`);
}
async function stop() {
  if (!child) {
    return;
  }
  child.kill("SIGTERM");
  await new Promise((r) => child.on("exit", r));
  child = null;
}

const initBody = { jsonrpc: "2.0", id: 1, method: "initialize", params: { protocolVersion: "2025-06-18", capabilities: {}, clientInfo: { name: "smoke", version: "0" } } };
async function mcp(token, body, session) {
  const headers = { "Content-Type": "application/json", Accept: "application/json, text/event-stream" };
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }
  if (session) {
    headers["mcp-session-id"] = session;
  }
  const r = await fetch(`${origin}/mcp`, { method: "POST", headers, body: JSON.stringify(body) });
  return { status: r.status, session: r.headers.get("mcp-session-id"), www: r.headers.get("www-authenticate"), text: await r.text() };
}
const form = (o) => new URLSearchParams(o).toString();

try {
  await start();

  // 1. Unauthenticated → 401 pointing at the resource metadata.
  const anon = await mcp(null, initBody);
  check("anonymous request is 401", anon.status === 401, `status ${anon.status}`);
  check("401 carries resource_metadata", /resource_metadata="[^"]+oauth-protected-resource\/mcp"/.test(anon.www || ""), anon.www || "no header");

  // 2. Discovery.
  const prm = await (await fetch(`${origin}/.well-known/oauth-protected-resource/mcp`)).json();
  check("protected resource metadata names the issuer", prm.authorization_servers?.[0] === `${origin}/` || prm.authorization_servers?.[0] === origin, JSON.stringify(prm));
  const rootPrm = await fetch(`${origin}/.well-known/oauth-protected-resource`, { redirect: "manual" });
  check("root protected-resource path redirects", rootPrm.status === 307);
  const as = await (await fetch(`${origin}/.well-known/oauth-authorization-server`)).json();
  check("authorization server metadata has endpoints", as.authorization_endpoint && as.token_endpoint && as.registration_endpoint && as.revocation_endpoint, JSON.stringify(as));
  check("PKCE S256 advertised", (as.code_challenge_methods_supported || []).includes("S256"));

  // 3. Dynamic client registration.
  const redirectUri = "http://127.0.0.1:1/callback";
  const reg = await fetch(as.registration_endpoint, { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ client_name: "Smoke client", redirect_uris: [redirectUri], token_endpoint_auth_method: "none", grant_types: ["authorization_code", "refresh_token"], response_types: ["code"] }) });
  const client = await reg.json();
  check("client registration returns a client_id", reg.status === 201 && client.client_id, JSON.stringify(client));
  const badReg = await fetch(as.registration_endpoint, { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ client_name: "Bad", redirect_uris: ["http://evil.example/cb"], token_endpoint_auth_method: "none" }) });
  check("plain-http remote redirect_uri is refused", badReg.status === 400);

  // 4. Consent page.
  const verifier = randomBytes(32).toString("base64url");
  const challenge = createHash("sha256").update(verifier).digest("base64url");
  const authUrl = `${as.authorization_endpoint}?${form({ response_type: "code", client_id: client.client_id, redirect_uri: redirectUri, code_challenge: challenge, code_challenge_method: "S256", state: "xyz", scope: "handbook", resource: `${origin}/mcp` })}`;
  const page = await fetch(authUrl);
  const html = await page.text();
  const request = (html.match(/name="request" value="([^"]+)"/) || [])[1];
  check("consent page renders with a signed request", page.status === 200 && Boolean(request) && html.includes("Smoke client"));

  // 5. Wrong key, then right key.
  const wrong = await fetch(`${origin}/oauth/approve`, { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: form({ request, key: "wrong", decision: "allow" }), redirect: "manual" });
  check("wrong key is refused", wrong.status === 401);
  const denied = await fetch(`${origin}/oauth/approve`, { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: form({ request, key: "", decision: "deny" }), redirect: "manual" });
  check("cancel redirects with access_denied", denied.status === 302 && (denied.headers.get("location") || "").includes("error=access_denied"));
  const approved = await fetch(`${origin}/oauth/approve`, { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: form({ request, key, decision: "allow" }), redirect: "manual" });
  const location = new URL(approved.headers.get("location") || "http://x/");
  const code = location.searchParams.get("code");
  check("right key redirects with code and state", approved.status === 302 && Boolean(code) && location.searchParams.get("state") === "xyz", approved.headers.get("location") || "");

  // 6. Token exchange with PKCE.
  const badTok = await fetch(as.token_endpoint, { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: form({ grant_type: "authorization_code", client_id: client.client_id, code, code_verifier: "not-the-verifier", redirect_uri: redirectUri }) });
  check("wrong code_verifier is refused", badTok.status === 400);
  const tok = await fetch(as.token_endpoint, { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: form({ grant_type: "authorization_code", client_id: client.client_id, code, code_verifier: verifier, redirect_uri: redirectUri, resource: `${origin}/mcp` }) });
  const tokens = await tok.json();
  check("code exchanges for access and refresh tokens", tok.status === 200 && tokens.access_token && tokens.refresh_token && tokens.token_type === "bearer", JSON.stringify(tokens));
  const reuse = await fetch(as.token_endpoint, { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: form({ grant_type: "authorization_code", client_id: client.client_id, code, code_verifier: verifier, redirect_uri: redirectUri }) });
  check("authorization code is single-use", reuse.status === 400);

  // 7. Access token opens an MCP session and lists tools.
  const init = await mcp(tokens.access_token, initBody);
  check("OAuth access token initializes a session", init.status === 200 && Boolean(init.session) && init.text.includes("serverInfo"), `status ${init.status}`);
  const tools = await mcp(tokens.access_token, { jsonrpc: "2.0", id: 2, method: "tools/list", params: {} }, init.session);
  check("tools/list works and has no publish tool", tools.status === 200 && tools.text.includes("handbook_get_page") && !/handbook_publish|handbook_approve/.test(tools.text));

  // 8. Refresh rotation.
  const ref = await fetch(as.token_endpoint, { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: form({ grant_type: "refresh_token", client_id: client.client_id, refresh_token: tokens.refresh_token }) });
  const tokens2 = await ref.json();
  check("refresh token yields a new pair", ref.status === 200 && tokens2.access_token && tokens2.refresh_token && tokens2.refresh_token !== tokens.refresh_token);
  const refAgain = await fetch(as.token_endpoint, { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: form({ grant_type: "refresh_token", client_id: client.client_id, refresh_token: tokens.refresh_token }) });
  check("used refresh token is rejected (rotation)", refAgain.status === 400);

  // 9. Revocation.
  const rev = await fetch(as.revocation_endpoint, { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: form({ client_id: client.client_id, token: tokens2.access_token }) });
  const afterRev = await mcp(tokens2.access_token, initBody);
  check("revoked access token is refused", rev.status === 200 && afterRev.status === 401, `revoke ${rev.status}, mcp ${afterRev.status}`);

  // 10. Shared key still works (ChatGPT path).
  const shared = await mcp(key, initBody);
  check("shared key still initializes a session", shared.status === 200 && Boolean(shared.session));
  const forged = await mcp("hbmcp1.eyJ0IjoiYWNjZXNzIn0.AAAA", initBody);
  check("forged token is refused", forged.status === 401);

  // 11. Restart: registered client and tokens survive.
  await stop();
  await start();
  const again = await fetch(authUrl);
  check("registered client survives a restart", again.status === 200);
  const survive = await mcp(tokens2.refresh_token ? (await (await fetch(as.token_endpoint, { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: form({ grant_type: "refresh_token", client_id: client.client_id, refresh_token: tokens2.refresh_token }) })).json()).access_token : "", initBody);
  check("refresh token survives a restart", survive.status === 200);
  const stillRevoked = await mcp(tokens2.access_token, initBody);
  check("revocation survives a restart", stillRevoked.status === 401);
} catch (error) {
  failures++;
  console.error("FAIL", error);
} finally {
  await stop();
  rmSync(stateDir, { recursive: true, force: true });
}

console.log(failures ? `\n${failures} failure(s)` : "\nall checks passed");
process.exit(failures ? 1 : 0);
