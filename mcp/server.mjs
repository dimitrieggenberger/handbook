#!/usr/bin/env node
/**
 * MCP adapter for the EuropaSchule Manual Institucional (local_handbook).
 *
 * Translates MCP tool calls (spec section 18.2) into the plugin's Moodle
 * External Services REST API (docs/API.md). The adapter holds the token;
 * the Moodle capability model decides what the token may do. There is no
 * publish tool: the API's authority ends at submitting drafts for human
 * review (spec 17.3, 18.3).
 *
 * Configuration (environment):
 *   HANDBOOK_BASE_URL  e.g. https://learn.europaschule.eu
 *   HANDBOOK_WSTOKEN   token of the authorised service account
 */

import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { z } from "zod";

const BASE_URL = (process.env.HANDBOOK_BASE_URL || "").replace(/\/+$/, "");
const WSTOKEN = process.env.HANDBOOK_WSTOKEN || "";

if (!BASE_URL || !WSTOKEN) {
  console.error("handbook-mcp: set HANDBOOK_BASE_URL and HANDBOOK_WSTOKEN.");
  process.exit(1);
}

/** Flatten nested params into Moodle's REST form encoding (a[0][b]=c). */
function flatten(value, body, prefix) {
  if (Array.isArray(value)) {
    value.forEach((item, index) => flatten(item, body, `${prefix}[${index}]`));
  } else if (value !== null && typeof value === "object") {
    for (const [key, item] of Object.entries(value)) {
      flatten(item, body, prefix ? `${prefix}[${key}]` : key);
    }
  } else if (value !== undefined) {
    body.set(prefix, typeof value === "boolean" ? (value ? "1" : "0") : String(value));
  }
}

/** Call one Moodle external function; Moodle errors become exceptions. */
async function ws(wsfunction, params = {}) {
  const body = new URLSearchParams();
  body.set("wstoken", WSTOKEN);
  body.set("wsfunction", wsfunction);
  body.set("moodlewsrestformat", "json");
  flatten(params, body, "");

  const response = await fetch(`${BASE_URL}/webservice/rest/server.php`, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: body.toString(),
  });
  if (!response.ok) {
    throw new Error(`HTTP ${response.status} from ${BASE_URL}`);
  }
  const data = await response.json();
  if (data && typeof data === "object" && !Array.isArray(data) && data.exception) {
    throw new Error(`${data.errorcode || data.exception}: ${data.message}`);
  }
  return data;
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

const server = new McpServer({ name: "handbook", version: "0.1.0" });

// ---- Read tools (spec 17.2) ------------------------------------------------

server.tool(
  "handbook_search",
  "Search published handbook pages by title, summary and text. Returns page summaries with slugs.",
  {
    query: z.string().describe("Search text (min 2 characters)"),
    contenttype: z.string().optional().describe("Filter: policy, procedure, standard, guideline, quickguide, template, example, roledescription"),
    categoryid: z.number().int().optional().describe("Filter by category id"),
  },
  handler(({ query, contenttype, categoryid }) =>
    ws("local_handbook_search_pages", {
      query,
      contenttype: contenttype ?? "",
      categoryid: categoryid ?? 0,
    }))
);

server.tool(
  "handbook_get_page",
  "Get one handbook page with its published content (HTML + plain text), metadata, version and content hash. Read this before drafting changes.",
  { identifier: z.string().describe("Page slug or numeric id") },
  handler(({ identifier }) => ws("local_handbook_get_page", { identifier }))
);

server.tool(
  "handbook_list_categories",
  "List the handbook's category tree (id, parentid, slug, name).",
  {},
  handler(() => ws("local_handbook_list_categories"))
);

server.tool(
  "handbook_list_pages",
  "List handbook pages (summaries without content). Optionally filter by category.",
  {
    categoryid: z.number().int().optional().describe("Category id (omit for all)"),
    includearchived: z.boolean().optional(),
    page: z.number().int().optional().describe("Zero-based page number"),
    perpage: z.number().int().optional().describe("Page size, max 200"),
  },
  handler((args) =>
    ws("local_handbook_list_pages", {
      categoryid: args.categoryid ?? 0,
      includearchived: args.includearchived ?? false,
      page: args.page ?? 0,
      perpage: args.perpage ?? 50,
    }))
);

server.tool(
  "handbook_list_changes",
  "Pages modified since a Unix timestamp, for incremental sync. Store the returned servertime and pass it back as 'since' next time.",
  { since: z.number().int().describe("Unix timestamp cursor") },
  handler(({ since }) => ws("local_handbook_list_changes", { since }))
);

server.tool(
  "handbook_get_related_pages",
  "Typed relations of a page (implements, quickguidefor, exceptionto, ...), both directions.",
  { identifier: z.string().describe("Page slug or numeric id") },
  handler(({ identifier }) => ws("local_handbook_list_relations", { identifier }))
);

server.tool(
  "handbook_list_revisions",
  "List a page's revision history (metadata only): versions, statuses, change summaries, hashes.",
  { identifier: z.string().describe("Page slug or numeric id") },
  handler(({ identifier }) => ws("local_handbook_list_page_revisions", { identifier }))
);

server.tool(
  "handbook_get_revision",
  "Get one revision including its content when permitted. Use two calls to compare versions or pages.",
  { revisionid: z.number().int() },
  handler(({ revisionid }) => ws("local_handbook_get_revision", { revisionid }))
);

// ---- Draft tools (spec 17.3): drafts only, humans publish -------------------

server.tool(
  "handbook_create_page_draft",
  "Create a NEW handbook page with its first draft revision (unpublished; enters the human review workflow). Content HTML must start headings at h2.",
  {
    title: z.string(),
    category: z.string().describe("Category slug or id"),
    contenttype: z.string().describe("policy, procedure, standard, guideline, quickguide, template, example, roledescription"),
    summary: z.string(),
    content: z.string().describe("Clean HTML, headings starting at h2"),
    slug: z.string().optional(),
    authoritylevel: z.number().int().min(1).max(6).optional(),
    criticality: z.string().optional().describe("reference, operational, mandatory, safetycritical"),
    requiredreading: z.boolean().optional(),
    responsiblearea: z.string().optional(),
    language: z.string().optional(),
  },
  handler((args) =>
    ws("local_handbook_create_page_draft", {
      title: args.title,
      category: args.category,
      contenttype: args.contenttype,
      summary: args.summary,
      content: args.content,
      slug: args.slug ?? "",
      authoritylevel: args.authoritylevel ?? 4,
      criticality: args.criticality ?? "operational",
      requiredreading: args.requiredreading ?? false,
      responsiblearea: args.responsiblearea ?? "",
      language: args.language ?? "es",
    }))
);

server.tool(
  "handbook_create_draft",
  "Create a draft revision of an EXISTING page, based on its published revision. Pass the published revision id you just read so a stale base fails clearly.",
  {
    identifier: z.string().describe("Page slug or numeric id"),
    expectedpublishedrevisionid: z.number().int().optional()
      .describe("Published revision id you based your work on (recommended)"),
  },
  handler(({ identifier, expectedpublishedrevisionid }) =>
    ws("local_handbook_create_revision_draft", {
      identifier,
      expectedpublishedrevisionid: expectedpublishedrevisionid ?? 0,
    }))
);

server.tool(
  "handbook_update_draft",
  "Update a draft revision's content. expectedtimemodified is REQUIRED: pass the timemodified from when you fetched/created the draft; a mismatch means someone else edited meanwhile.",
  {
    revisionid: z.number().int(),
    content: z.string().describe("Clean HTML, headings starting at h2"),
    expectedtimemodified: z.number().int(),
    changesummary: z.string().optional(),
  },
  handler(({ revisionid, content, expectedtimemodified, changesummary }) =>
    ws("local_handbook_update_draft", {
      revisionid,
      content,
      expectedtimemodified,
      changesummary: changesummary ?? "",
    }))
);

server.tool(
  "handbook_submit_for_review",
  "Submit a draft revision to the human review queue with a change summary. This is the API's last step: review, approval and publication are human actions.",
  {
    revisionid: z.number().int(),
    changesummary: z.string().describe("What changed and why (required)"),
  },
  handler(({ revisionid, changesummary }) =>
    ws("local_handbook_submit_draft_for_review", { revisionid, changesummary }))
);

// ---- Finding tools (spec 19): advisory only ---------------------------------

server.tool(
  "handbook_record_finding",
  "Record an ADVISORY quality finding (contradiction, outdated reference, broken link, ...) citing the affected pages. Never changes content. Distinguish confirmed from possible issues via confidence.",
  {
    findingtype: z.string().describe("contradiction, duplicate, ambiguous_responsibility, missing_escalation, missing_record, outdated_reference, incorrect_content, inconsistent_terminology, broken_link, missing_owner, review_overdue, procedure_without_policy, policy_without_procedure, modality_difference, assessment_outdated, accessibility, other"),
    summary: z.string().describe("One-line summary"),
    explanation: z.string().optional(),
    recommendation: z.string().optional(),
    severity: z.enum(["low", "medium", "high"]).optional(),
    confidence: z.enum(["low", "medium", "high"]).optional()
      .describe("high = confirmed, low/medium = possible"),
    pages: z.array(z.object({
      identifier: z.string().describe("Page slug or id"),
      anchor: z.string().optional().describe("Heading or section"),
      excerpt: z.string().optional().describe("Relevant quoted text"),
    })).min(1),
  },
  handler((args) =>
    ws("local_handbook_create_finding", {
      findingtype: args.findingtype,
      summary: args.summary,
      explanation: args.explanation ?? "",
      recommendation: args.recommendation ?? "",
      severity: args.severity ?? "medium",
      confidence: args.confidence ?? "medium",
      source: "mcp",
      pages: args.pages.map((page) => ({
        identifier: page.identifier,
        anchor: page.anchor ?? "",
        excerpt: page.excerpt ?? "",
      })),
    }))
);

server.tool(
  "handbook_list_open_findings",
  "List quality findings (default: open and under review) with their affected pages.",
  {
    status: z.string().optional()
      .describe("open, under_review, accepted, dismissed, resolved, intentional_difference"),
  },
  handler(({ status }) =>
    ws("local_handbook_list_findings", { status: status ?? "" }))
);

// ---- Start ------------------------------------------------------------------

const transport = new StdioServerTransport();
await server.connect(transport);
console.error(`handbook-mcp connected to ${BASE_URL}`);
