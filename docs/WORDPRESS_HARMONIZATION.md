# Handbook ↔ WordPress harmonization

How the Manual Institucional (Moodle `local_handbook`) and the public
WordPress website are kept consistent, and how the AI workflow for the
website mirrors the handbook's MCP workflow.

## 1. Problem

The same institutional facts live in two places:

- **Handbook (Moodle)** — internal source of truth: policies, procedures,
  role descriptions, escalation ladders, contacts. Being extended with
  audience-targeted content (staff, students, parents).
- **WordPress (public site)** — the outward face: admissions, fees,
  calendar, "who is who", how-to pages for parents.

When a position is renamed, a procedure changes, or a responsibility moves,
both must change. A parent must never read one thing on the website and be
told another by the handbook-derived material. Contradictions between the
two are an editorial defect exactly like contradictions *inside* the
handbook (spec §19) — this document extends the same finding/proposal
machinery across the site boundary.

## 2. Source-of-truth rule

**The handbook is canonical for institutional facts**: role names,
office holders, responsibilities, procedures, deadlines, escalation
routes. The website *derives* from it (simplified, audience-adapted,
translated), never the other way around.

Consequences for the agent:

- A confirmed conflict is resolved by proposing a **WordPress change** that
  matches the handbook — unless the handbook page is itself outdated, in
  which case the agent records a handbook **finding** (and may draft a
  handbook change set) instead of "fixing" the website to match stale
  content.
- When it is unclear which side is right (e.g. the website announces a
  newer state the handbook has not caught up with), the agent records a
  finding with `confidence: low|medium` and lets humans decide. It never
  guesses.
- Intentional differences are legitimate: the website simplifies, omits
  internal detail, or phrases things for parents. Only **factual**
  divergence (names, positions, numbers, dates, steps that contradict)
  counts as a conflict. Tone and depth do not.

## 3. Architecture

Same shape on both sides — read published content, stage drafts, humans
review and publish. One MCP endpoint can serve both toolsets so a single
agent session sees both sources:

```
Agent (Claude / ChatGPT)
   │  handbook_* tools                 │  wp_* tools
   ▼                                   ▼
mcp/lib/handbook.mjs              mcp/lib/wordpress.mjs
   │  Moodle REST + wstoken           │  WP REST + Application Password
   ▼                                   ▼
Moodle local_handbook API         WordPress core REST API (wp-json/wp/v2)
   drafts → review → publish          proposal drafts → pending → wp-admin
   (humans publish)                   (humans apply & publish)
```

- **Entry points:** `mcp/server.mjs` (handbook, stdio),
  `mcp/wordpress-server.mjs` (WordPress, stdio), `mcp/http-server.mjs`
  (remote HTTPS — serves handbook tools and, when the `WORDPRESS_*`
  secrets are configured, the WordPress tools on the same endpoint).
- **No plugin needed on WordPress.** The adapter uses only the core REST
  API with an Application Password.

### The WordPress no-publish guarantee (two layers)

1. **Adapter:** there is no publish/delete/media tool; write tools refuse
   any post whose status is not `draft`/`pending`; the only statuses ever
   sent are `draft` and `pending`.
2. **WordPress:** the service account holds the **Contributor** role, so
   WordPress itself rejects publishing, editing live content, and
   uploading files — even if the adapter had a bug.

Because a Contributor cannot edit existing published pages, a change to
live content is staged as a **proposal draft**: a new draft post titled
`[Propuesta] <page title>`, whose excerpt names the target URL/slug and
the change summary, and whose body is the full corrected content. Human
editors review the *Pending* list in wp-admin, apply the change to the
real page, publish, and trash the proposal. (If the site later installs a
pending-revisions plugin such as PublishPress Revisions, the adapter can
be upgraded to stage true in-place revisions; the tool surface stays the
same.)

## 4. The harmonization workflow

Triggered on a schedule (weekly), after notable handbook publications, or
on demand ("we renamed the Jefatura de Estudios — check the website").

1. **Delta:** call `handbook_list_changes` and `wp_list_changes` with the
   stored cursors; on first run, or for a full audit, load
   `handbook_get_context_index` and `wp_get_context_index` instead.
2. **Map:** for each changed item, find its counterparts on the other side
   — `wp_search` / `handbook_search` on the key entities (role names,
   person names, procedure names, fees, dates), plus the index metadata
   (slugs, categories, titles). Parent-facing overlap gets priority:
   admissions, fees, calendar, contacts, "who is who", complaint routes.
3. **Compare:** fetch full text only for mapped pairs
   (`handbook_get_page`, `wp_get_content`) and compare the *facts*:
   names and office holders, responsibilities, steps and their order,
   deadlines, amounts, contact channels. Ignore differences of tone,
   depth and audience adaptation (§2).
4. **Report every conflict** as a handbook finding
   (`handbook_record_finding`): `findingtype: contradiction` (or
   `outdated_reference`, `inconsistent_terminology`), citing the handbook
   page(s) with excerpts, and naming the WordPress URL + quoted text in
   the explanation. `confidence: high` only for confirmed factual
   contradiction. The finding is the audit trail even when a fix is also
   proposed.
5. **Propose the fix** (only with the user's go-ahead, per the standing
   agent rules):
   - Website wrong → `wp_create_proposal_draft` with the full corrected
     content, `target` = the live URL, `changesummary` citing the handbook
     slug that motivated it; then `wp_submit_proposal_for_review`.
   - Handbook wrong → a handbook change set
     (`handbook_create_change_set` + `handbook_upsert_change_set_draft`).
   - Unclear → the finding alone; humans decide.
6. **Cursor:** store both returned `servertime` values for the next run.

### Agent operating rules (additions to spec §18.3)

- Read the live published version on BOTH sides before claiming a
  conflict; never compare from memory or from the index alone.
- One proposal per website page; check `wp_list_proposals` first and
  update the existing proposal instead of stacking duplicates.
- Always cross-cite: a WordPress proposal names the handbook slug; a
  handbook finding names the WordPress URL. Reviewers must be able to
  verify in one click.
- Respect audiences: content the handbook restricts (staff-only, internal
  escalation details, anything about identifiable people beyond their
  public role) must never be pushed into a public website proposal. When
  handbook audience filtering lands (students/teachers/parents), only
  content cleared for the **parents/public** audience is comparison
  material for the public site.
- Language: compare within the same language where both exist; a missing
  translation is a finding (`findingtype: other`), not a conflict.

## 5. WordPress setup (once)

1. **Service account:** create a WordPress user, e.g. `handbook-ai`,
   with role **Contributor** — not Author, not Editor. This is the
   server-side enforcement of the no-publish rule.
2. **Application Password:** in that user's profile (Users → Profile →
   Application Passwords) create one named e.g. `handbook-mcp`. Copy the
   generated password — that is `WORDPRESS_APP_PASSWORD` (spaces may be
   kept or stripped; both work).
3. **HTTPS only.** Application Passwords are Basic auth; the site already
   runs HTTPS.
4. Configure the adapter (see `mcp/README.md` for stdio clients and
   `mcp/DEPLOY.md` for the remote endpoint):

   ```
   WORDPRESS_BASE_URL=https://www.europaschule.eu
   WORDPRESS_APP_USER=handbook-ai
   WORDPRESS_APP_PASSWORD=xxxx xxxx xxxx xxxx xxxx xxxx
   WORDPRESS_MCP_MODE=readwrite-drafts   # or readonly
   ```

5. Verify: `wp_get_context_index` returns the site's pages/posts;
   `wp_create_proposal_draft` creates a draft visible under Posts →
   Drafts; trying to publish from that account in wp-admin is impossible.

## 6. Roles in the loop

| Actor | Handbook | WordPress |
|---|---|---|
| Agent | search/read, drafts, change sets, findings | search/read, proposal drafts, submit pending |
| Human editor | review queue, approve, publish | review *Pending* list, apply to live page, publish, trash proposal |
| Enforcement | no publish external function; capability model | no publish tool; Contributor role |

## 7. Out of scope (for now)

- Automatic publication on either side — excluded by design, not pending.
- Editing WordPress menus, widgets, media, theme or settings.
- Custom post types beyond `page`/`post` (extend `CONTENT_TYPES` in
  `mcp/lib/wordpress.mjs` when needed).
- True in-place pending revisions on WordPress (needs a plugin; see §3).
- Structured storage of findings on the WordPress side — findings live in
  the handbook's finding system, which is the single editorial-quality
  ledger for both sources.
