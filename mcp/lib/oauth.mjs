/**
 * OAuth 2.1 authorization server for the Handbook MCP adapter.
 *
 * Lets clients that only speak MCP authorization (claude.ai and Claude
 * Desktop custom connectors, MCP Inspector, Claude Code with OAuth) connect
 * without a static header, while ChatGPT keeps presenting the shared key as
 * a plain Bearer token. The authority model does not change: whoever is
 * authorised acts through the same `handbook-ai` Moodle service account, and
 * there is still no approve or publish tool.
 *
 * Who may authorise a client? Anyone who knows the connector key
 * (HANDBOOK_MCP_AUTH_TOKEN): the consent page asks for it once, then the
 * client keeps a refresh token. So the key is the single secret to rotate.
 *
 * Design:
 *  - Dynamic client registration (RFC 7591), authorization code + PKCE S256,
 *    refresh-token rotation and revocation, all served by the SDK router.
 *  - Tokens are stateless, HMAC-signed blobs, so a restart or redeploy does
 *    not log every client out. Revocations and registered clients persist in
 *    a small JSON file next to the secrets (survives Git redeploys).
 *  - The signing key is HANDBOOK_MCP_OAUTH_SECRET, or derived from the
 *    connector key with HKDF when that variable is absent. Rotating the
 *    connector key therefore also invalidates every issued token.
 */

import { createHmac, hkdfSync, randomBytes, randomUUID, timingSafeEqual } from "node:crypto";
import { existsSync, readFileSync, renameSync, writeFileSync } from "node:fs";
import {
  InvalidClientMetadataError,
  InvalidGrantError,
  InvalidRequestError,
  InvalidTokenError,
} from "@modelcontextprotocol/sdk/server/auth/errors.js";

export const SCOPE = "handbook";

const ACCESS_TTL = 60 * 60;             // 1 hour
const REFRESH_TTL = 30 * 24 * 60 * 60;  // 30 days
const CODE_TTL = 10 * 60;               // authorization code lifetime
const REQUEST_TTL = 15 * 60;            // consent page validity
const TOKEN_PREFIX = "hbmcp1";

const now = () => Math.floor(Date.now() / 1000);
const b64u = (buf) => Buffer.from(buf).toString("base64url");

/** Signing key: explicit secret, else HKDF from the connector key. */
export function deriveSigningKey(connectorKey, explicitSecret) {
  if (explicitSecret) {
    return Buffer.from(explicitSecret, "utf8");
  }
  return Buffer.from(hkdfSync("sha256", connectorKey, "handbook-mcp", "oauth-signing-key", 32));
}

function constantTimeEqual(a, b) {
  const x = Buffer.from(String(a));
  const y = Buffer.from(String(b));
  return x.length === y.length && timingSafeEqual(x, y);
}

function escapeHtml(text) {
  return String(text ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
}

/** Only https redirect URIs, or loopback http for local tools (RFC 8252). */
function acceptableRedirectUri(uri) {
  let url;
  try {
    url = new URL(uri);
  } catch {
    return false;
  }
  if (url.protocol === "https:") {
    return true;
  }
  return url.protocol === "http:" && ["localhost", "127.0.0.1", "[::1]"].includes(url.hostname);
}

export class HandbookOAuthProvider {
  /**
   * @param {object} options
   * @param {Buffer} options.signingKey     HMAC key for tokens and consent requests.
   * @param {string} options.connectorKey   The shared key typed on the consent page.
   * @param {string} options.statePath      JSON file for clients and revocations.
   * @param {string} options.approvePath    Route that receives the consent form.
   * @param {(fields: object) => void} [options.log]
   */
  constructor({ signingKey, connectorKey, statePath, approvePath, log = () => {} }) {
    this.key = signingKey;
    this.connectorKey = connectorKey;
    this.statePath = statePath;
    this.approvePath = approvePath;
    this.log = log;
    this.codes = new Map();
    this.state = { clients: {}, revoked: {} };
    this.load();

    const self = this;
    this.clientsStore = {
      getClient(clientId) {
        return self.state.clients[clientId];
      },
      registerClient(client) {
        const uris = Array.isArray(client.redirect_uris) ? client.redirect_uris : [];
        if (!uris.length || !uris.every(acceptableRedirectUri)) {
          throw new InvalidClientMetadataError("redirect_uris must be https URLs (or http on localhost)");
        }
        const info = {
          ...client,
          client_id: client.client_id || randomUUID(),
          client_id_issued_at: client.client_id_issued_at || now(),
          scope: SCOPE,
        };
        self.state.clients[info.client_id] = info;
        self.save();
        self.log({ event: "oauth_client_registered", client: info.client_id, name: info.client_name || "" });
        return info;
      },
    };
  }

  // ---- persistence -------------------------------------------------------

  load() {
    if (!existsSync(this.statePath)) {
      return;
    }
    try {
      const parsed = JSON.parse(readFileSync(this.statePath, "utf8"));
      this.state.clients = parsed.clients && typeof parsed.clients === "object" ? parsed.clients : {};
      this.state.revoked = parsed.revoked && typeof parsed.revoked === "object" ? parsed.revoked : {};
    } catch (error) {
      this.log({ event: "oauth_state_unreadable", path: this.statePath, error: error.message });
    }
  }

  save() {
    const t = now();
    for (const [jti, exp] of Object.entries(this.state.revoked)) {
      if (exp < t) {
        delete this.state.revoked[jti];
      }
    }
    const tmp = `${this.statePath}.${process.pid}.tmp`;
    writeFileSync(tmp, JSON.stringify(this.state, null, 2), { mode: 0o600 });
    renameSync(tmp, this.statePath);
  }

  // ---- signed blobs ------------------------------------------------------

  sign(payload) {
    const body = b64u(JSON.stringify(payload));
    const mac = b64u(createHmac("sha256", this.key).update(body).digest());
    return `${TOKEN_PREFIX}.${body}.${mac}`;
  }

  /** Returns the payload, or null when the signature or shape is wrong. */
  verify(blob) {
    if (typeof blob !== "string") {
      return null;
    }
    const parts = blob.split(".");
    if (parts.length !== 3 || parts[0] !== TOKEN_PREFIX) {
      return null;
    }
    const expected = createHmac("sha256", this.key).update(parts[1]).digest();
    let given;
    try {
      given = Buffer.from(parts[2], "base64url");
    } catch {
      return null;
    }
    if (given.length !== expected.length || !timingSafeEqual(given, expected)) {
      return null;
    }
    try {
      return JSON.parse(Buffer.from(parts[1], "base64url").toString("utf8"));
    } catch {
      return null;
    }
  }

  issueTokens(clientId, scopes, resource) {
    const t = now();
    const sc = scopes && scopes.length ? scopes : [SCOPE];
    const access = this.sign({ t: "access", jti: randomUUID(), cid: clientId, sc, rs: resource || null, exp: t + ACCESS_TTL });
    const refresh = this.sign({ t: "refresh", jti: randomUUID(), cid: clientId, sc, rs: resource || null, exp: t + REFRESH_TTL });
    return { access_token: access, token_type: "bearer", expires_in: ACCESS_TTL, refresh_token: refresh, scope: sc.join(" ") };
  }

  // ---- consent page ------------------------------------------------------

  async authorize(client, params, res) {
    const request = this.sign({
      t: "request",
      cid: client.client_id,
      ru: params.redirectUri,
      st: params.state || null,
      cc: params.codeChallenge,
      sc: params.scopes && params.scopes.length ? params.scopes : [SCOPE],
      rs: params.resource ? params.resource.href : null,
      exp: now() + REQUEST_TTL,
    });
    res.setHeader("Cache-Control", "no-store");
    res.status(200).type("html").send(this.renderConsent({ client, request }));
  }

  renderConsent({ client, request, error }) {
    const name = escapeHtml(client.client_name || client.client_id);
    return `<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manual Institucional · Autorizar acceso</title>
<style>
  body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #f7f8fa; color: #1b2a38; }
  main { max-width: 440px; margin: 48px auto; padding: 32px; background: #fff; border: 1px solid #e2e1e3; border-radius: 10px; }
  h1 { font-size: 20px; margin: 0 0 8px; color: #003f5c; }
  p { font-size: 14px; line-height: 1.6; margin: 0 0 16px; }
  label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
  input[type=password] { width: 100%; box-sizing: border-box; font-size: 16px; padding: 10px 12px; border: 1px solid #c6c9ce; border-radius: 8px; }
  .row { display: flex; gap: 12px; margin-top: 20px; }
  button { font: inherit; font-weight: 600; padding: 10px 20px; border-radius: 8px; border: 1px solid transparent; cursor: pointer; }
  .ok { background: #0078c3; color: #fff; } .no { background: #fff; color: #005a92; border-color: #c6c9ce; }
  .err { background: #fdecec; color: #b01818; padding: 10px 12px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
  .muted { color: #5f6b78; font-size: 13px; }
</style></head><body><main>
<h1>Autorizar acceso al Manual Institucional</h1>
<p><strong>${name}</strong> solicita acceso a las herramientas del Manual Institucional de EuropaSchule (lectura y borradores; nunca aprobación ni publicación).</p>
${error ? `<div class="err">${escapeHtml(error)}</div>` : ""}
<form method="post" action="${escapeHtml(this.approvePath)}">
  <input type="hidden" name="request" value="${escapeHtml(request)}">
  <label for="key">Clave de acceso del conector</label>
  <input type="password" id="key" name="key" autocomplete="off" autofocus required>
  <p class="muted">Es la clave que entrega la administración a las personas autorizadas. No se guarda en el cliente: el acceso queda ligado a esta autorización y puede revocarse cambiando la clave.</p>
  <div class="row"><button class="ok" type="submit" name="decision" value="allow">Autorizar</button><button class="no" type="submit" name="decision" value="deny">Cancelar</button></div>
</form>
</main></body></html>`;
  }

  /** Express handler for the consent form (POST, urlencoded). */
  approveHandler() {
    return (req, res) => {
      res.setHeader("Cache-Control", "no-store");
      const body = req.body || {};
      const request = this.verify(body.request);
      if (!request || request.t !== "request" || request.exp < now()) {
        res.status(400).type("html").send("<p>La solicitud de autorización caducó o no es válida. Vuelva a iniciar la conexión desde su cliente.</p>");
        return;
      }
      const client = this.state.clients[request.cid];
      if (!client) {
        res.status(400).type("html").send("<p>Cliente desconocido. Vuelva a iniciar la conexión desde su cliente.</p>");
        return;
      }
      const redirect = new URL(request.ru);
      if (request.st) {
        redirect.searchParams.set("state", request.st);
      }
      if (body.decision === "deny") {
        redirect.searchParams.set("error", "access_denied");
        redirect.searchParams.set("error_description", "The user cancelled the authorization");
        res.redirect(302, redirect.href);
        return;
      }
      if (!body.key || !constantTimeEqual(body.key, this.connectorKey)) {
        this.log({ event: "auth_rejected", path: this.approvePath, client: request.cid });
        res.status(401).type("html").send(this.renderConsent({ client, request: body.request, error: "La clave no coincide. Revise con la administración." }));
        return;
      }
      const code = b64u(randomBytes(32));
      this.codes.set(code, { cid: request.cid, cc: request.cc, ru: request.ru, rs: request.rs, sc: request.sc, exp: now() + CODE_TTL });
      this.sweepCodes();
      redirect.searchParams.set("code", code);
      this.log({ event: "oauth_authorized", client: request.cid });
      res.redirect(302, redirect.href);
    };
  }

  sweepCodes() {
    const t = now();
    for (const [code, entry] of this.codes) {
      if (entry.exp < t) {
        this.codes.delete(code);
      }
    }
  }

  // ---- OAuthServerProvider -------------------------------------------------

  takeCode(client, code, consume) {
    const entry = this.codes.get(code);
    if (!entry || entry.exp < now() || entry.cid !== client.client_id) {
      throw new InvalidGrantError("Unknown, expired or foreign authorization code");
    }
    if (consume) {
      this.codes.delete(code);
    }
    return entry;
  }

  async challengeForAuthorizationCode(client, code) {
    return this.takeCode(client, code, false).cc;
  }

  async exchangeAuthorizationCode(client, code, _codeVerifier, redirectUri, resource) {
    const entry = this.takeCode(client, code, true);
    if (redirectUri && redirectUri !== entry.ru) {
      throw new InvalidGrantError("redirect_uri does not match the authorization request");
    }
    const rs = resource ? resource.href : entry.rs;
    return this.issueTokens(client.client_id, entry.sc, rs);
  }

  async exchangeRefreshToken(client, refreshToken, scopes, resource) {
    const p = this.verify(refreshToken);
    if (!p || p.t !== "refresh" || p.cid !== client.client_id || p.exp < now() || this.state.revoked[p.jti]) {
      throw new InvalidGrantError("Invalid refresh token");
    }
    if (scopes && scopes.some((s) => !p.sc.includes(s))) {
      throw new InvalidRequestError("Requested scope exceeds the original grant");
    }
    // Rotation: the presented refresh token dies with this exchange.
    this.state.revoked[p.jti] = p.exp;
    this.save();
    return this.issueTokens(client.client_id, scopes && scopes.length ? scopes : p.sc, resource ? resource.href : p.rs);
  }

  async verifyAccessToken(token) {
    const p = this.verify(token);
    if (!p || p.t !== "access") {
      throw new InvalidTokenError("Invalid access token");
    }
    if (p.exp < now()) {
      throw new InvalidTokenError("Token has expired");
    }
    if (this.state.revoked[p.jti]) {
      throw new InvalidTokenError("Token has been revoked");
    }
    return {
      token,
      clientId: p.cid,
      scopes: p.sc,
      expiresAt: p.exp,
      resource: p.rs ? new URL(p.rs) : undefined,
      extra: { auth: "oauth" },
    };
  }

  async revokeToken(client, request) {
    const p = this.verify(request.token);
    if (!p || (p.t !== "access" && p.t !== "refresh") || p.cid !== client.client_id || p.exp < now()) {
      return;
    }
    this.state.revoked[p.jti] = p.exp;
    this.save();
    this.log({ event: "oauth_token_revoked", client: client.client_id, kind: p.t });
  }
}
