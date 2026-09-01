/**
 * Shared WordPress MCP implementation: REST client + tool registration.
 *
 * Mirrors lib/handbook.mjs for the public WordPress site so the same agent
 * session can read both sources and keep them harmonized (see
 * ../../docs/WORDPRESS_HARMONIZATION.md). The same workflow guarantee holds:
 * agents READ published content and stage PROPOSAL DRAFTS (WordPress status
 * draft/pending) — there is no publish tool, no delete tool, and published
 * content is never modified through this adapter. Pair the adapter with a
 * WordPress service account holding the CONTRIBUTOR role so WordPress itself
 * enforces the same boundary server-side.
 */

import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod";

/** Reduce rendered HTML to comparable plain text. */
export function stripHtml(html) {
  return String(html || "")
    .replace(/<style[\s\S]*?<\/style>/gi, " ")
    .replace(/<script[\s\S]*?<\/script>/gi, " ")
    .replace(/<\/(p|div|li|h[1-6]|tr|blockquote)>/gi, "\n")
    .replace(/<br\s*\/?>/gi, "\n")
    .replace(/<[^>]+>/g, " ")
    .replace(/&nbsp;/g, " ")
    .replace(/&amp;/g, "&")
    .replace(/&lt;/g, "<")
    .replace(/&gt;/g, ">")
    .replace(/&quot;/g, '"')
    .replace(/&#0?39;/g, "'")
    .replace(/[ \t]+/g, " ")
    .replace(/\s*\n\s*/g, "\n")
    .trim();
}

/**
 * Build a WordPress REST caller (Application Passwords over HTTPS). The
 * credentials stay server-side; they are never placed in tool results or
 * logged. Returns { data, headers } so callers can read pagination headers.
 */
export function makeWp({ baseUrl, user, appPassword }) {
  const base = String(baseUrl || "").replace(/\/+$/, "");
  const auth = "Basic " + Buffer.from(`${user}:${appPassword}`).toString("base64");
  return async function wp(path, { method = "GET", query, body } = {}) {
    const url = new URL(`${base}/wp-json${path}`);
    for (const [key, value] of Object.entries(query || {})) {
      if (value !== undefined && value !== null && value !== "") {
        url.searchParams.set(key, String(value));
      }
    }
    const response = await fetch(url, {
      method,
      headers: {
        Authorization: auth,
        Accept: "application/json",
        ...(body ? { "Content-Type": "application/json" } : {}),
      },
      body: body ? JSON.stringify(body) : undefined,
    });
    const text = await response.text();
    let data = null;
    try {
      data = text ? JSON.parse(text) : null;
    } catch {
      // Non-JSON body (e.g. an HTML error page); fall through to status check.
    }
    if (!response.ok) {
      const code = (data && data.code) || `http_${response.status}`;
      const message = (data && data.message) || `HTTP ${response.status} from ${base}`;
      throw new Error(`${code}: ${message}`);
    }
    return { data, headers: response.headers };
  };
}

/** Wrap a handler so results and errors become MCP tool responses. */
function handler(fn) {
  return async (args) => {
    try {
      const result = await fn(args ?? {});
      return { content: [{ type: "text", text: JSON.stringify(result, null, 2) }] };
    } catch (error) {
      return {
        isError: true,
        content: [{ type: "text", text: `Error: ${error.message}` }],
      };
    }
  };
}

const CONTENT_TYPES = ["pages", "posts"];
const SUMMARY_FIELDS =
  "id,slug,type,status,link,parent,modified_gmt,title,excerpt,categories,tags";

/** Fetch every result page of a collection endpoint. */
async function collectAll(wp, path, query = {}) {
  const items = [];
  let page = 1;
  let totalPages = 1;
  do {
    const { data, headers } = await wp(path, {
      query: { ...query, per_page: 100, page },
    });
    items.push(...(Array.isArray(data) ? data : []));
    totalPages = Number(headers.get("x-wp-totalpages") || 1);
    page += 1;
  } while (page <= totalPages);
  return items;
}

/** Load id → name maps for categories and tags (best effort). */
async function loadTermMaps(wp) {
  const maps = { categories: {}, tags: {} };
  for (const taxonomy of ["categories", "tags"]) {
    try {
      const terms = await collectAll(wp, `/wp/v2/${taxonomy}`, {
        _fields: "id,name,slug",
        hide_empty: false,
      });
      for (const term of terms) {
        maps[taxonomy][term.id] = term.name;
      }
    } catch {
      // Taxonomy unavailable (e.g. disabled): leave ids unresolved.
    }
  }
  return maps;
}

/** Compact, comparable summary of a post/page object. */
function summarize(item, termMaps) {
  return {
    id: item.id,
    type: item.type,
    slug: item.slug,
    title: stripHtml(item.title?.rendered ?? item.title?.raw ?? ""),
    url: item.link,
    status: item.status,
    parent: item.parent || 0,
    modified: item.modified_gmt ? `${item.modified_gmt}Z` : "",
    excerpt: stripHtml(item.excerpt?.rendered ?? ""),
    categories: (item.categories || []).map((id) => termMaps?.categories?.[id] ?? id),
    tags: (item.tags || []).map((id) => termMaps?.tags?.[id] ?? id),
  };
}

/** Resolve an identifier (numeric id or slug) to one published page/post. */
async function findContent(wp, identifier, type) {
  const types = type ? [type] : CONTENT_TYPES;
  const isId = /^\d+$/.test(String(identifier).trim());
  for (const t of types) {
    try {
      if (isId) {
        const { data } = await wp(`/wp/v2/${t}/${identifier}`);
        if (data && data.id) {
          return data;
        }
      } else {
        const { data } = await wp(`/wp/v2/${t}`, {
          query: { slug: identifier, status: "publish" },
        });
        if (Array.isArray(data) && data.length > 0) {
          return data[0];
        }
      }
    } catch {
      // Not found under this type; try the next one.
    }
  }
  throw new Error(`No published page or post matches "${identifier}".`);
}

/**
 * Fetch a proposal post in edit context and refuse anything that is not a
 * draft/pending proposal. This is the adapter-side write guard: published,
 * scheduled, private or trashed content is never modified here.
 */
async function getEditableProposal(wp, postid) {
  const { data } = await wp(`/wp/v2/posts/${postid}`, { query: { context: "edit" } });
  if (!data || !data.id) {
    throw new Error(`Post ${postid} not found or not readable by this account.`);
  }
  if (!["draft", "pending"].includes(data.status)) {
    throw new Error(
      `Post ${postid} has status "${data.status}". Only draft/pending proposals may be ` +
      "modified through this adapter — published content is applied by humans in wp-admin."
    );
  }
  return data;
}

const PROPOSAL_PREFIX = "[Propuesta]";

/**
 * Register every WordPress tool on an McpServer.
 *
 * @param {McpServer} server
 * @param {(path: string, init?: object) => Promise<{data: any, headers: Headers}>} wp
 * @param {{ mode?: "readwrite-drafts" | "readonly" }} options
 */
export function registerWordPressTools(server, wp, { mode = "readwrite-drafts" } = {}) {
  const writable = mode !== "readonly";

  // ---- Read tools ---------------------------------------------------------.

  server.tool(
    "wp_get_context_index",
    "Compact index of ALL published WordPress pages and posts: id, type, slug, title, URL, parent, last-modified, category/tag names and a plain-text excerpt — but NOT full content. Load this first for any site-wide or handbook-vs-website analysis, then fetch full content only for the items you decide are relevant.",
    {},
    handler(async () => {
      const termMaps = await loadTermMaps(wp);
      const items = [];
      for (const type of CONTENT_TYPES) {
        const rows = await collectAll(wp, `/wp/v2/${type}`, {
          status: "publish",
          _fields: SUMMARY_FIELDS,
        });
        items.push(...rows.map((row) => summarize({ ...row, type: row.type ?? type.replace(/s$/, "") }, termMaps)));
      }
      return { generatedat: new Date().toISOString(), count: items.length, items };
    })
  );

  server.tool(
    "wp_search",
    "Full-text search across the published WordPress site. Returns id, type, title and URL per hit; follow up with wp_get_content for the full text.",
    {
      query: z.string().describe("Search text"),
      subtype: z.enum(["page", "post", "any"]).optional().describe("Restrict to pages or posts (default any)"),
      perpage: z.number().int().optional().describe("Max results, default 20"),
    },
    handler(async ({ query, subtype, perpage }) => {
      const { data } = await wp("/wp/v2/search", {
        query: {
          search: query,
          subtype: subtype && subtype !== "any" ? subtype : "",
          per_page: perpage ?? 20,
          _fields: "id,type,subtype,title,url",
        },
      });
      return (data || []).map((hit) => ({
        id: hit.id,
        type: hit.subtype || hit.type,
        title: stripHtml(hit.title),
        url: hit.url,
      }));
    })
  );

  server.tool(
    "wp_get_content",
    "Get one published WordPress page or post (by numeric id or slug) with metadata, rendered HTML and a plain-text version. Read this before comparing against the handbook or drafting a proposal.",
    {
      identifier: z.string().describe("Numeric id or slug"),
      type: z.enum(["pages", "posts"]).optional().describe("Content type (omit to try pages, then posts)"),
    },
    handler(async ({ identifier, type }) => {
      const item = await findContent(wp, identifier, type);
      const termMaps = await loadTermMaps(wp);
      return {
        ...summarize(item, termMaps),
        contenthtml: item.content?.rendered ?? "",
        contenttext: stripHtml(item.content?.rendered ?? ""),
      };
    })
  );

  server.tool(
    "wp_list_pages",
    "List published WordPress pages (summaries without content). Supports search and pagination.",
    {
      search: z.string().optional(),
      page: z.number().int().optional().describe("One-based page number"),
      perpage: z.number().int().optional().describe("Page size, max 100"),
    },
    handler(async ({ search, page, perpage }) => {
      const { data, headers } = await wp("/wp/v2/pages", {
        query: {
          status: "publish",
          search: search ?? "",
          page: page ?? 1,
          per_page: Math.min(perpage ?? 50, 100),
          _fields: SUMMARY_FIELDS,
        },
      });
      return {
        total: Number(headers.get("x-wp-total") || 0),
        items: (data || []).map((row) => summarize({ ...row, type: row.type ?? "page" }, null)),
      };
    })
  );

  server.tool(
    "wp_list_posts",
    "List published WordPress posts (summaries without content). Supports search, category filter and pagination.",
    {
      search: z.string().optional(),
      categoryid: z.number().int().optional(),
      page: z.number().int().optional().describe("One-based page number"),
      perpage: z.number().int().optional().describe("Page size, max 100"),
    },
    handler(async ({ search, categoryid, page, perpage }) => {
      const { data, headers } = await wp("/wp/v2/posts", {
        query: {
          status: "publish",
          search: search ?? "",
          categories: categoryid ?? "",
          page: page ?? 1,
          per_page: Math.min(perpage ?? 50, 100),
          _fields: SUMMARY_FIELDS,
        },
      });
      const termMaps = await loadTermMaps(wp);
      return {
        total: Number(headers.get("x-wp-total") || 0),
        items: (data || []).map((row) => summarize({ ...row, type: row.type ?? "post" }, termMaps)),
      };
    })
  );

  server.tool(
    "wp_list_categories",
    "List the site's post categories (id, slug, name, parent, count).",
    {},
    handler(async () => {
      const terms = await collectAll(wp, "/wp/v2/categories", {
        _fields: "id,slug,name,parent,count",
        hide_empty: false,
      });
      return terms;
    })
  );

  server.tool(
    "wp_list_changes",
    "WordPress pages and posts modified since a Unix timestamp, for incremental handbook↔website sync. Store the returned servertime and pass it back as 'since' next time — the counterpart of handbook_list_changes.",
    { since: z.number().int().describe("Unix timestamp cursor") },
    handler(async ({ since }) => {
      const modifiedAfter = new Date(since * 1000).toISOString();
      const items = [];
      for (const type of CONTENT_TYPES) {
        const rows = await collectAll(wp, `/wp/v2/${type}`, {
          status: "publish",
          modified_after: modifiedAfter,
          _fields: SUMMARY_FIELDS,
        });
        items.push(...rows.map((row) => summarize({ ...row, type: row.type ?? type.replace(/s$/, "") }, null)));
      }
      return { servertime: Math.floor(Date.now() / 1000), count: items.length, items };
    })
  );

  server.tool(
    "wp_list_proposals",
    "List this adapter's own proposal drafts on the WordPress site (status draft or pending). Check this before creating a new proposal so the same change is not proposed twice.",
    {
      status: z.enum(["draft", "pending", "any"]).optional().describe("Default any (draft + pending)"),
    },
    handler(async ({ status }) => {
      const statuses = status && status !== "any" ? status : "draft,pending";
      const { data } = await wp("/wp/v2/posts", {
        query: {
          status: statuses,
          context: "edit",
          per_page: 100,
          _fields: "id,slug,status,modified_gmt,title,excerpt,link",
        },
      });
      return (data || []).map((row) => ({
        id: row.id,
        status: row.status,
        modified: row.modified_gmt ? `${row.modified_gmt}Z` : "",
        title: stripHtml(row.title?.raw ?? row.title?.rendered ?? ""),
        note: stripHtml(row.excerpt?.raw ?? row.excerpt?.rendered ?? ""),
        url: row.link,
      }));
    })
  );

  if (!writable) {
    return; // Read-only mode: no proposal write tools.
  }

  // ---- Proposal tools: drafts only, humans apply and publish --------------.

  server.tool(
    "wp_create_proposal_draft",
    "Create a PROPOSAL as a WordPress draft post (never published, never touching live content). For a change to an existing page/post, pass its URL or slug as target and put the FULL corrected content in content — a human editor compares in wp-admin, applies it to the live page and discards the proposal. For brand-new public content, omit target. The title is prefixed \"[Propuesta]\" automatically; target and change summary land in the post excerpt so the review list is self-explanatory.",
    {
      title: z.string().describe("Title of the affected (or new) page/post, without prefix"),
      content: z.string().describe("Full proposed HTML for the page/post"),
      changesummary: z.string().describe("What changes and why — cite the handbook page/slug that motivated it"),
      target: z.string().optional().describe("URL or slug of the existing page/post this proposal corrects (omit for new content)"),
      submitforreview: z.boolean().optional().describe("Also set status to pending review (default false: plain draft)"),
    },
    handler(async ({ title, content, changesummary, target, submitforreview }) => {
      const cleanTitle = title.startsWith(PROPOSAL_PREFIX)
        ? title
        : `${PROPOSAL_PREFIX} ${title}`;
      const note = target
        ? `Propuesta para: ${target} — ${changesummary}`
        : `Propuesta de contenido nuevo — ${changesummary}`;
      const { data } = await wp("/wp/v2/posts", {
        method: "POST",
        body: {
          title: cleanTitle,
          content,
          excerpt: note,
          status: submitforreview ? "pending" : "draft",
        },
      });
      return {
        id: data.id,
        status: data.status,
        modified: data.modified_gmt ? `${data.modified_gmt}Z` : "",
        title: stripHtml(data.title?.raw ?? data.title?.rendered ?? ""),
      };
    })
  );

  server.tool(
    "wp_update_proposal_draft",
    "Update one of THIS adapter's proposal drafts (status draft/pending only — anything else is refused). Pass expectedmodified from when you read/created it so a concurrent human edit is detected instead of overwritten.",
    {
      postid: z.number().int(),
      content: z.string().optional().describe("Replacement HTML"),
      title: z.string().optional(),
      changesummary: z.string().optional().describe("Updated summary for the post excerpt"),
      expectedmodified: z.string().optional().describe("modified value from the previous read/create result"),
    },
    handler(async ({ postid, content, title, changesummary, expectedmodified }) => {
      const current = await getEditableProposal(wp, postid);
      const currentModified = current.modified_gmt ? `${current.modified_gmt}Z` : "";
      if (expectedmodified && expectedmodified !== currentModified) {
        throw new Error(
          `Concurrent edit detected on post ${postid}: it was modified at ${currentModified}, ` +
          `you based your work on ${expectedmodified}. Re-read it before updating.`
        );
      }
      const body = {};
      if (content !== undefined) {
        body.content = content;
      }
      if (title !== undefined) {
        body.title = title.startsWith(PROPOSAL_PREFIX) ? title : `${PROPOSAL_PREFIX} ${title}`;
      }
      if (changesummary !== undefined) {
        body.excerpt = changesummary;
      }
      const { data } = await wp(`/wp/v2/posts/${postid}`, { method: "POST", body });
      return {
        id: data.id,
        status: data.status,
        modified: data.modified_gmt ? `${data.modified_gmt}Z` : "",
      };
    })
  );

  server.tool(
    "wp_submit_proposal_for_review",
    "Move a proposal draft to WordPress status 'pending' (pending review). This is the adapter's last step: a human editor reviews the proposal in wp-admin, applies it to the live content and publishes. There is no publish tool, by design.",
    { postid: z.number().int() },
    handler(async ({ postid }) => {
      await getEditableProposal(wp, postid);
      const { data } = await wp(`/wp/v2/posts/${postid}`, {
        method: "POST",
        body: { status: "pending" },
      });
      return { id: data.id, status: data.status };
    })
  );
}

/**
 * Build a fully-wired WordPress MCP server (transport-agnostic).
 *
 * @param {{ baseUrl: string, user: string, appPassword: string, mode?: string, name?: string, version?: string }} config
 * @returns {McpServer}
 */
export function buildWordPressServer({ baseUrl, user, appPassword, mode = "readwrite-drafts", name = "wordpress", version = "0.1.0" }) {
  const wp = makeWp({ baseUrl, user, appPassword });
  const server = new McpServer({ name, version });
  registerWordPressTools(server, wp, { mode });
  return server;
}
