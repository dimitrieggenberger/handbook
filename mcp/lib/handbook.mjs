/**
 * Shared Handbook MCP implementation: REST client + tool registration.
 *
 * Both the local stdio entry point (server.mjs) and any remote HTTP transport
 * consume this module, so they advertise identical tools and schemas
 * (spec 18, 36.6-36.7). There is deliberately no approve or publish tool.
 */

import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod";

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

/**
 * Build a Moodle web-service caller. The token stays server-side; it is never
 * placed in tool results or logged.
 */
export function makeWs({ baseUrl, token }) {
  const base = String(baseUrl || "").replace(/\/+$/, "");
  return async function ws(wsfunction, params = {}) {
    const body = new URLSearchParams();
    body.set("wstoken", token);
    body.set("wsfunction", wsfunction);
    body.set("moodlewsrestformat", "json");
    flatten(params, body, "");

    const response = await fetch(`${base}/webservice/rest/server.php`, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body.toString(),
    });
    if (!response.ok) {
      throw new Error(`HTTP ${response.status} from ${base}`);
    }
    const data = await response.json();
    if (data && typeof data === "object" && !Array.isArray(data) && data.exception) {
      throw new Error(`${data.errorcode || data.exception}: ${data.message}`);
    }
    return data;
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

/**
 * Register every Handbook tool on an McpServer.
 *
 * @param {McpServer} server
 * @param {(fn: string, params?: object) => Promise<any>} ws
 * @param {{ mode?: "readwrite-drafts" | "readonly" }} options
 */
export function registerHandbookTools(server, ws, { mode = "readwrite-drafts" } = {}) {
  const writable = mode !== "readonly";

  // ---- Read tools (spec 17.2, 36.6) ---------------------------------------.

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
    "handbook_get_context_index",
    "Compact index of all AI-permitted handbook pages: metadata, category path, authority, criticality, dates, published content hash, whether an editable working draft exists, and typed relations in both directions — but NOT full content. Load this first for any cross-handbook analysis, then fetch full content only for the pages you decide are relevant.",
    { includearchived: z.boolean().optional() },
    handler(({ includearchived }) =>
      ws("local_handbook_get_context_index", { includearchived: includearchived ?? false }))
  );

  server.tool(
    "handbook_get_page",
    "Get one handbook page with its published content (HTML + plain text), metadata, version and content hash. Read this before drafting changes.",
    { identifier: z.string().describe("Page slug or numeric id") },
    handler(({ identifier }) => ws("local_handbook_get_page", { identifier }))
  );

  server.tool(
    "handbook_get_working_page",
    "Read a page's current working draft (content when permitted), its workflow status, and which change set owns it, if any. Does not change state. Use it to inspect an in-progress draft before deciding whether to update it.",
    { identifier: z.string().describe("Page slug or numeric id") },
    handler(({ identifier }) => ws("local_handbook_get_working_page", { identifier }))
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

  server.tool(
    "handbook_list_open_findings",
    "List quality findings (default: open and under review) with their affected pages.",
    {
      status: z.string().optional()
        .describe("open, under_review, accepted, dismissed, resolved, intentional_difference"),
    },
    handler(({ status }) => ws("local_handbook_list_findings", { status: status ?? "" }))
  );

  // ---- Change-set reads (spec 36.4) ---------------------------------------.

  server.tool(
    "handbook_get_change_set",
    "Get one change set with all its page items and their statuses (draft, conflict, in_review, approved, published, rejected).",
    { changesetid: z.number().int() },
    handler(({ changesetid }) => ws("local_handbook_get_changeset", { changesetid }))
  );

  server.tool(
    "handbook_list_change_sets",
    "List change sets, optionally filtered by status or source (human/ai).",
    {
      status: z.string().optional(),
      source: z.string().optional(),
    },
    handler((args) =>
      ws("local_handbook_list_changesets", {
        status: args.status ?? "",
        source: args.source ?? "",
        page: 0,
        perpage: 50,
      }))
  );

  server.tool(
    "handbook_list_areas",
    "List the controlled vocabulary of responsible areas (key + display name). Use a returned key or name for the responsiblearea field of a metadata or new-page proposal so it references the governed vocabulary.",
    {},
    handler(() => ws("local_handbook_list_areas", {}))
  );

  server.tool(
    "handbook_get_style_guide",
    "The handbook's content style guide: the house 'hb-*' formatting patterns (multi-step procedures, callouts, org charts with teams, roles, escalation ladders, do/don't, RACI matrices, timelines, contacts, definitions, figures, fact sheets, checklists, plus communication examples: emails, WhatsApp-style chats, conversation scripts, agendas/actas, formal letters and written-feedback fields — communication examples must use invented names only) with example HTML to adapt. Call this ONCE before writing or updating page content, then reuse the patterns so every article looks uniform. Formatting guidance only — it changes nothing.",
    {},
    handler(() => ws("local_handbook_get_style_guide", {}))
  );

  server.tool(
    "handbook_get_question_guide",
    "Authoring guide for end-of-article reading-comprehension questions: the institutional rules (2-6 questions per article, multichoice with exactly one correct answer and mandatory per-option feedback, ordering questions listing procedure steps in the correct sequence - only steps whose order is mandatory), a copy-adapt Moodle XML template, and the list of published pages that already have questions. WORKFLOW: read the article with handbook_get_page, write the XML per this guide, and hand the XML to the human editor - they import it on the page's question form in Moodle. There is NO tool to import, modify or delete questions: the reading-accreditation gate is human-only, like approval and publication. Passing the test with 100% is what marks an article as read for pages that have questions.",
    {},
    handler(() => ws("local_handbook_get_question_guide", {}))
  );

  server.tool(
    "handbook_get_archive_impact",
    "Before proposing to archive a page, check its impact: how many other pages relate TO it, how many active reading paths include it, and whether it is required reading. Use this to decide whether to set a replacement page and redirect.",
    { identifier: z.string().describe("Page slug or numeric id") },
    handler(({ identifier }) => ws("local_handbook_get_archive_impact", { identifier }))
  );

  server.tool(
    "handbook_validate_change_set",
    "Validate every item of a change set against the current published state without applying anything. Returns per-item ok/error. Use it before asking a human to apply the whole set. Approval and application remain human actions in Moodle.",
    { changesetid: z.number().int() },
    handler(({ changesetid }) => ws("local_handbook_validate_changeset", { changesetid }))
  );

  server.tool(
    "handbook_list_reading_paths",
    "List reading paths (onboarding, calendar-phase, role-based, ...) with their item counts. Use this to discover an existing path before proposing an edit, or to check whether one already covers a topic before proposing a new one.",
    { activeonly: z.boolean().optional().describe("Only active paths") },
    handler(({ activeonly }) =>
      ws("local_handbook_list_reading_paths", { activeonly: activeonly ?? false }))
  );

  server.tool(
    "handbook_get_reading_path",
    "Get a reading path's full snapshot: header fields plus its ordered sections and pages. Read this before proposing an edit, then echo the sections back (changed as needed) to handbook_upsert_change_set_reading_path, passing timemodified as expectedtimemodified so a concurrent edit is detected.",
    { pathid: z.number().int() },
    handler(({ pathid }) => ws("local_handbook_get_reading_path", { pathid }))
  );

  server.tool(
    "handbook_get_reading_path_coverage",
    "Aggregate reading-path coverage: how many published pages sit in at least one active path, how many are orphaned, required-reading coverage, overlap, and per-path item counts. No individual staff completion data. Use it to spot gaps before recommending paths.",
    {},
    handler(() => ws("local_handbook_get_reading_path_coverage", {}))
  );

  server.tool(
    "handbook_audit_reading_paths",
    "Handbook-wide reading-path audit: deterministic advisory findings — required pages in no active path, paths past their review date, paths with no required item, oversized paths. Read-only; changes nothing.",
    {},
    handler(() => ws("local_handbook_audit_reading_paths", {}))
  );

  server.tool(
    "handbook_recommend_paths_for_page",
    "Which active reading paths a page is a good candidate for, from typed relations (a quickguidefor/templatefor/implements target already in a path is a strong signal) and shared category. Read-only — nothing is recorded. To record one for human triage, use handbook_create_path_recommendation.",
    { identifier: z.string().describe("Page slug or numeric id") },
    handler(({ identifier }) => ws("local_handbook_recommend_paths_for_page", { identifier }))
  );

  server.tool(
    "handbook_list_path_recommendations",
    "List advisory reading-path recommendations (default: open). Advisory records only; no completion data.",
    {
      status: z.string().optional().describe("open, accepted, dismissed, deferred, already_covered, intentional_omission, resolved (empty = all)"),
      pathid: z.number().int().optional(),
      pageid: z.number().int().optional(),
    },
    handler((args) =>
      ws("local_handbook_list_path_recommendations", {
        status: args.status ?? "open",
        pathid: args.pathid ?? 0,
        pageid: args.pageid ?? 0,
      }))
  );

  server.tool(
    "handbook_get_path_recommendation",
    "Get one advisory reading-path recommendation by id.",
    { recommendationid: z.number().int() },
    handler(({ recommendationid }) =>
      ws("local_handbook_get_path_recommendation", { recommendationid }))
  );

  if (!writable) {
    return; // Read-only mode: no draft or change-set write tools.
  }

  // ---- Draft tools (spec 17.3): drafts only, humans publish ---------------.

  server.tool(
    "handbook_create_page_draft",
    "Create a NEW handbook page with its first draft revision (unpublished; enters the human review workflow). Content HTML must start headings at h2.",
    {
      title: z.string(),
      category: z.string().describe("Category slug or id"),
      contenttype: z.string().describe("policy, procedure, standard, guideline, quickguide, template, example, roledescription"),
      summary: z.string(),
      content: z.string().describe("Clean HTML, headings starting at h2. For procedures, structures and callouts use the handbook house patterns (call handbook_get_style_guide once for the catalogue): hb-steps, hb-note/hb-tip/hb-warning/hb-important, hb-org, hb-roles, hb-escalation, hb-dodont, hb-matrix, etc."),
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
    "Create a draft revision of an EXISTING page, based on its published revision. Pass the published revision id you just read so a stale base fails clearly. For coordinated multi-page changes, prefer a change set instead.",
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
      content: z.string().describe("Clean HTML, headings starting at h2. For procedures, structures and callouts use the handbook house patterns (call handbook_get_style_guide once for the catalogue): hb-steps, hb-note/hb-tip/hb-warning/hb-important, hb-org, hb-roles, hb-escalation, hb-dodont, hb-matrix, etc."),
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
    "Submit a single draft revision to the human review queue with a change summary. This is the API's last step for a standalone draft: review, approval and publication are human actions.",
    {
      revisionid: z.number().int(),
      changesummary: z.string().describe("What changed and why (required)"),
    },
    handler(({ revisionid, changesummary }) =>
      ws("local_handbook_submit_draft_for_review", { revisionid, changesummary }))
  );

  // ---- Change-set writes (spec 36.4): grouped proposals, never publish ----.

  server.tool(
    "handbook_create_change_set",
    "Create a change set that groups coordinated draft proposals across multiple pages (like a pull request). Do this ONLY after the user explicitly tells you to create the drafts. Marked as an AI-sourced proposal.",
    {
      title: z.string(),
      instructionsummary: z.string().optional().describe("Concise approved instruction, not a full transcript"),
      externalreference: z.string().optional().describe("Optional conversation/task id; never a secret"),
    },
    handler((args) =>
      ws("local_handbook_create_changeset", {
        title: args.title,
        instructionsummary: args.instructionsummary ?? "",
        externalreference: args.externalreference ?? "",
      }))
  );

  server.tool(
    "handbook_upsert_change_set_draft",
    "Create or update THIS change set's draft for ONE page. On repeat calls for the same page it reuses the same editable draft (pass expectedtimemodified from the previous result) — it never creates a second draft. It returns a structured conflict instead of overwriting a human draft, another change set's draft, an in-review revision, a stale published base, or a concurrent edit. Never publishes. When first drafting a page, pass expectedpublishedrevisionid from the page you read.",
    {
      changesetid: z.number().int(),
      identifier: z.string().describe("Page slug or numeric id"),
      content: z.string().describe("Clean HTML, headings starting at h2. For procedures, structures and callouts use the handbook house patterns (call handbook_get_style_guide once for the catalogue): hb-steps, hb-note/hb-tip/hb-warning/hb-important, hb-org, hb-roles, hb-escalation, hb-dodont, hb-matrix, etc."),
      changesummary: z.string().optional(),
      expectedpublishedrevisionid: z.number().int().optional(),
      expectedtimemodified: z.number().int().optional(),
      requiresreack: z.boolean().optional().describe("Publishing should demand renewed acknowledgements"),
    },
    handler((args) =>
      ws("local_handbook_upsert_changeset_draft", {
        changesetid: args.changesetid,
        identifier: args.identifier,
        content: args.content,
        changesummary: args.changesummary ?? "",
        expectedpublishedrevisionid: args.expectedpublishedrevisionid ?? 0,
        expectedtimemodified: args.expectedtimemodified ?? 0,
        requiresreack: args.requiresreack ?? false,
      }))
  );

  server.tool(
    "handbook_upsert_change_set_metadata",
    "Propose a partial metadata (fiche) patch for ONE page inside THIS change set: include only the fields to change; omitted fields keep their published value. Staged as a draft — a human reviews, approves and applies it in Moodle. Never applies. Pass expectedtimemodified from the page you read to guard against a concurrent fiche edit.",
    {
      changesetid: z.number().int(),
      identifier: z.string().describe("Page slug or numeric id"),
      title: z.string().optional(),
      slug: z.string().optional().describe("New slug; the old slug keeps resolving"),
      summary: z.string().optional(),
      contenttype: z.string().optional()
        .describe("policy, procedure, standard, guideline, quickguide, template, example, roledescription"),
      authoritylevel: z.number().int().min(1).max(5).optional(),
      criticality: z.string().optional()
        .describe("reference, operational, mandatory, safetycritical"),
      responsiblearea: z.string().optional(),
      reviewdate: z.number().int().optional().describe("Unix time"),
      requiredreading: z.boolean().optional(),
      expectedtimemodified: z.number().int().optional(),
    },
    handler((args) => {
      const metadata = {};
      for (const key of ["title", "slug", "summary", "contenttype", "authoritylevel",
        "criticality", "responsiblearea", "reviewdate", "requiredreading"]) {
        if (args[key] !== undefined) {
          metadata[key] = args[key];
        }
      }
      return ws("local_handbook_upsert_changeset_metadata", {
        changesetid: args.changesetid,
        identifier: args.identifier,
        metadata,
        expectedtimemodified: args.expectedtimemodified ?? 0,
      });
    })
  );

  server.tool(
    "handbook_upsert_change_set_new_page",
    "Propose a BRAND-NEW page inside THIS change set. Identify it by a stable tempkey (e.g. \"newpage:direccion-oficial\") so later relation proposals can point at it before it exists. Staged as a draft — a human reviews, approves and publishes it in Moodle. Never publishes. A category is required.",
    {
      changesetid: z.number().int(),
      tempkey: z.string().describe("Stable id within this change set, e.g. newpage:slug"),
      title: z.string(),
      categoryid: z.number().int().describe("Existing category id"),
      content: z.string().describe("Clean HTML, headings starting at h2. For procedures, structures and callouts use the handbook house patterns (call handbook_get_style_guide once for the catalogue): hb-steps, hb-note/hb-tip/hb-warning/hb-important, hb-org, hb-roles, hb-escalation, hb-dodont, hb-matrix, etc."),
      slug: z.string().optional(),
      summary: z.string().optional(),
      contenttype: z.string().optional()
        .describe("policy, procedure, standard, guideline, quickguide, template, example, roledescription"),
      authoritylevel: z.number().int().min(1).max(5).optional(),
      criticality: z.string().optional().describe("reference, operational, mandatory, safetycritical"),
      responsiblearea: z.string().optional(),
      reviewdate: z.number().int().optional(),
      requiredreading: z.boolean().optional(),
      language: z.string().optional(),
    },
    handler((args) => {
      const page = { title: args.title, categoryid: args.categoryid, content: args.content };
      for (const key of ["slug", "summary", "contenttype", "authoritylevel", "criticality",
        "responsiblearea", "reviewdate", "requiredreading", "language"]) {
        if (args[key] !== undefined) {
          page[key] = args[key];
        }
      }
      return ws("local_handbook_upsert_changeset_new_page", {
        changesetid: args.changesetid,
        tempkey: args.tempkey,
        page,
      });
    })
  );

  server.tool(
    "handbook_upsert_change_set_relations",
    "Propose edits to a page's outgoing typed relations inside THIS change set. Each operation is create or remove, with a relationtype and a target — an existing page (target = slug/id) or a new page proposed in this same change set (targettempkey). Replaces this page's relation proposal in the set. Staged as a draft; a human applies it in Moodle.",
    {
      changesetid: z.number().int(),
      identifier: z.string().describe("Source page slug or id"),
      operations: z.array(z.object({
        op: z.enum(["create", "remove"]),
        relationtype: z.string()
          .describe("relatedto, dependson, implements, replaces, supersedes, exceptionto, procedurefor, quickguidefor, templatefor, assessmentfor, translationof"),
        target: z.string().optional().describe("Existing target page slug or id"),
        targettempkey: z.string().optional().describe("Tempkey of a new page in this change set"),
      })).min(1),
    },
    handler((args) =>
      ws("local_handbook_upsert_changeset_relations", {
        changesetid: args.changesetid,
        identifier: args.identifier,
        operations: args.operations,
      }))
  );

  server.tool(
    "handbook_upsert_change_set_archive",
    "Propose ARCHIVING a page inside THIS change set (retire it from navigation, search and active paths). Give a structured reason and, when it is superseded, a replacement page + redirect mode so old links still lead somewhere. Consider handbook_get_archive_impact first. Staged as a draft — a human applies it in Moodle. Never archives directly.",
    {
      changesetid: z.number().int(),
      identifier: z.string().describe("Page slug or id to archive"),
      reason: z.string()
        .describe("obsolete, superseded, duplicate, merged, temporary_content_expired, role_no_longer_exists, procedure_no_longer_used, incorrect_legacy_import, other"),
      replacement: z.string().optional().describe("Replacement page slug or id (required for a redirecting mode)"),
      redirectmode: z.string().optional()
        .describe("notice_only (default), redirect_with_notice, automatic_redirect, no_redirect"),
      note: z.string().optional().describe("Explanation (required when reason is other)"),
    },
    handler((args) =>
      ws("local_handbook_upsert_changeset_archive", {
        changesetid: args.changesetid,
        identifier: args.identifier,
        reason: args.reason,
        replacement: args.replacement ?? "",
        redirectmode: args.redirectmode ?? "notice_only",
        note: args.note ?? "",
      }))
  );

  server.tool(
    "handbook_upsert_change_set_restore",
    "Propose RESTORING an archived page inside THIS change set (make it visible again and clear its redirect). Staged as a draft — a human applies it in Moodle.",
    {
      changesetid: z.number().int(),
      identifier: z.string().describe("Archived page slug or id to restore"),
      note: z.string().optional(),
    },
    handler((args) =>
      ws("local_handbook_upsert_changeset_restore", {
        changesetid: args.changesetid,
        identifier: args.identifier,
        note: args.note ?? "",
      }))
  );

  server.tool(
    "handbook_upsert_change_set_page_move",
    "Propose moving ONE page to another category inside THIS change set. Preserves the page's id, slug, revisions, acknowledgements and relations — only its category changes. Pass expectedcategoryid and expectedpagetimemodified from the page you read for a safe (conflict-detecting) move. Staged as a draft; a human applies it in Moodle.",
    {
      changesetid: z.number().int(),
      identifier: z.string().describe("Page slug or id to move"),
      targetcategoryid: z.number().int().optional().describe("Destination category id"),
      targetcategorytempkey: z.string().optional()
        .describe("Tempkey of a category created in this same change set (instead of targetcategoryid)"),
      expectedcategoryid: z.number().int().optional(),
      expectedpagetimemodified: z.number().int().optional(),
      changesummary: z.string().optional(),
    },
    handler((args) =>
      ws("local_handbook_upsert_changeset_page_move", {
        changesetid: args.changesetid,
        identifier: args.identifier,
        targetcategoryid: args.targetcategoryid ?? 0,
        targetcategorytempkey: args.targetcategorytempkey ?? "",
        expectedcategoryid: args.expectedcategoryid ?? 0,
        expectedpagetimemodified: args.expectedpagetimemodified ?? 0,
        changesummary: args.changesummary ?? "",
      }))
  );

  server.tool(
    "handbook_upsert_change_set_category",
    "Propose a category change inside THIS change set: op = create (name, optional parentid), update (categoryid + fields to change), move (categoryid + newparentid), merge (sourceid + targetid: moves the source's pages and subcategories into the target, then deletes the source), or delete_empty (categoryid: dissolve a category that has no pages and no subcategories). Cycles and merges into a descendant are rejected. Staged as a draft; a human applies it in Moodle.",
    {
      changesetid: z.number().int(),
      op: z.enum(["create", "update", "move", "merge", "delete_empty"]),
      tempkey: z.string().optional().describe("Stable id for a new category (create)"),
      name: z.string().optional(),
      slug: z.string().optional().describe("Slug for create/update; the old slug keeps resolving"),
      parentid: z.number().int().optional().describe("Parent id (create)"),
      parenttempkey: z.string().optional().describe("Tempkey of a parent created in this set (create)"),
      newparenttempkey: z.string().optional().describe("Tempkey of a new parent created in this set (move)"),
      description: z.string().optional(),
      icon: z.string().optional().describe("Font Awesome class, e.g. fa-folder-open"),
      visible: z.boolean().optional(),
      sortorder: z.number().int().optional(),
      categoryid: z.number().int().optional().describe("Category id (update/move/delete_empty)"),
      newparentid: z.number().int().optional().describe("New parent id (move; 0 = top level)"),
      sourceid: z.number().int().optional().describe("Source category id (merge)"),
      targetid: z.number().int().optional().describe("Target category id (merge)"),
    },
    handler((args) => {
      const operation = { op: args.op };
      for (const key of ["tempkey", "name", "slug", "parentid", "parenttempkey", "newparenttempkey",
        "description", "icon", "visible", "sortorder", "categoryid", "newparentid", "sourceid", "targetid"]) {
        if (args[key] !== undefined) {
          operation[key] = args[key];
        }
      }
      return ws("local_handbook_upsert_changeset_category", {
        changesetid: args.changesetid,
        operation,
      });
    })
  );

  server.tool(
    "handbook_upsert_change_set_reading_path",
    "Propose a WHOLE reading path (create or update) inside THIS change set. Submit a COMPLETE snapshot: applying it makes the path match exactly (sections, page order, required flags). Omit pathid to create; pass it (with expectedtimemodified from handbook_get_reading_path) to edit. Each item targets an existing page (pageid) or a page proposed in this same set (pagetempkey). Staged as a draft; a human applies it in Moodle.",
    {
      changesetid: z.number().int(),
      pathid: z.number().int().optional().describe("Existing path id to edit (omit to create)"),
      name: z.string(),
      slug: z.string().optional().describe("Slug (omit to derive from name); the old slug keeps resolving"),
      description: z.string().optional(),
      pathtype: z.enum(["onboarding", "calendar_phase", "role_based", "situational", "refresher", "compliance"])
        .optional(),
      schoolyear: z.string().optional().describe("e.g. 2025-2026 (omit for evergreen)"),
      active: z.boolean().optional(),
      reviewdate: z.number().int().optional().describe("Unix timestamp of the next review (0 = unset)"),
      estimatedminutes: z.number().int().optional(),
      audiencecohorts: z.array(z.number().int()).optional().describe("Cohort ids (omit = everyone)"),
      audienceroles: z.array(z.number().int()).optional().describe("System role ids (omit = everyone)"),
      expectedtimemodified: z.number().int().optional()
        .describe("Path timemodified from handbook_get_reading_path (edit only)"),
      sections: z.array(z.object({
        name: z.string().optional().describe("Section heading (omit for a single default section)"),
        items: z.array(z.object({
          pageid: z.number().int().optional().describe("Existing page id"),
          pagetempkey: z.string().optional().describe("Tempkey of a page proposed in this set (instead of pageid)"),
          required: z.boolean().optional(),
          rationale: z.string().optional().describe("Why this page belongs in the path"),
          quizcmid: z.number().int().optional(),
        })).min(1),
      })).min(1),
    },
    handler((args) =>
      ws("local_handbook_upsert_changeset_reading_path", {
        changesetid: args.changesetid,
        pathid: args.pathid ?? 0,
        name: args.name,
        slug: args.slug ?? "",
        description: args.description ?? "",
        pathtype: args.pathtype ?? "",
        schoolyear: args.schoolyear ?? "",
        active: args.active ?? true,
        reviewdate: args.reviewdate ?? 0,
        estimatedminutes: args.estimatedminutes ?? 0,
        audiencecohorts: args.audiencecohorts ?? [],
        audienceroles: args.audienceroles ?? [],
        expectedtimemodified: args.expectedtimemodified ?? 0,
        sections: args.sections.map((section) => ({
          name: section.name ?? "",
          items: section.items.map((it) => ({
            pageid: it.pageid ?? 0,
            pagetempkey: it.pagetempkey ?? "",
            required: it.required ?? true,
            rationale: it.rationale ?? "",
            quizcmid: it.quizcmid ?? 0,
          })),
        })),
      }))
  );

  server.tool(
    "handbook_create_path_recommendation",
    "Record an ADVISORY recommendation to change a reading path (e.g. add a new quick guide beside the canonical procedure already in the path). It never edits the active path — it creates a pending record a human triages. Prefer this over editing a path directly when you spot a relevant page.",
    {
      pathid: z.number().int().describe("Target path id"),
      identifier: z.string().optional().describe("Article slug or id (omit for a path-level recommendation)"),
      rectype: z.enum(["add", "remove", "reorder", "replace", "split_path", "merge_paths", "update_required_status"]).optional(),
      confidence: z.enum(["low", "medium", "high"]).optional(),
      rationale: z.string().optional().describe("Why this is recommended"),
      suggestedsection: z.string().optional(),
      suggestedrequired: z.boolean().optional(),
      suggestedafterpageid: z.number().int().optional().describe("Place after this page (0 = end)"),
    },
    handler((args) =>
      ws("local_handbook_create_path_recommendation", {
        pathid: args.pathid,
        identifier: args.identifier ?? "",
        rectype: args.rectype ?? "add",
        confidence: args.confidence ?? "medium",
        rationale: args.rationale ?? "",
        suggestedsection: args.suggestedsection ?? "",
        suggestedrequired: args.suggestedrequired ?? true,
        suggestedafterpageid: args.suggestedafterpageid ?? 0,
      }))
  );

  server.tool(
    "handbook_accept_path_recommendation",
    "Accept a recommendation into a change set as a DRAFT reading-path revision. The active path is not touched: the recommendation is applied to a copy and staged as a path proposal for a human to review, approve and publish. Do this only when the user asks to act on a recommendation.",
    {
      recommendationid: z.number().int(),
      changesetid: z.number().int().describe("Change set to draft the revision into"),
    },
    handler(({ recommendationid, changesetid }) =>
      ws("local_handbook_accept_path_recommendation", { recommendationid, changesetid }))
  );

  server.tool(
    "handbook_submit_change_set_for_review",
    "Submit a change set's eligible drafts for human review. Do this ONLY after the user explicitly asks. Returns a per-page result; conflicts are skipped, not forced. Review, approval and publication remain human actions in Moodle.",
    { changesetid: z.number().int() },
    handler(({ changesetid }) =>
      ws("local_handbook_submit_changeset_for_review", { changesetid }))
  );

  // ---- Findings (spec 19): advisory only ----------------------------------.

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
}

/**
 * Build a fully-wired Handbook MCP server (transport-agnostic).
 *
 * @param {{ baseUrl: string, token: string, mode?: string, name?: string, version?: string }} config
 * @returns {McpServer}
 */
export function buildHandbookServer({ baseUrl, token, mode = "readwrite-drafts", name = "handbook", version = "0.2.0" }) {
  const ws = makeWs({ baseUrl, token });
  const server = new McpServer({ name, version });
  registerHandbookTools(server, ws, { mode });
  return server;
}
