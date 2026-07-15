# Review: Handbook AI Editorial Expansion — architecture fit & phased plan

**Status:** engineering review of the two next-phase proposals
**Component:** `local_handbook`
**Reviews:** `docs/HANDBOOK_AI_EDITORIAL_CAPABILITIES.md`, `docs/HANDBOOK_GLOSSARY_AND_CONTENT_LIFECYCLE.md`
**Against:** current code on `feature/handbook-ai-editorial-expansion` (plugin v`2026071501`, 0.14.0)

---

## 1. Headline verdict

The two proposals are **directionally consistent with the plugin's existing design** and, crucially, they **restate rather than weaken the authority boundary**: every AI operation stays a draft proposal inside a change set, and both documents repeat the absolute prohibition on approve / publish / archive / restore / delete (`CAPABILITIES.md` §2.2, §5; `GLOSSARY.md` §2.2, §16, §29). That matches the in-code invariant verbatim — `docs/SPECIFICATION.md:1517`: *"There is deliberately no external function and no MCP tool for approval or publication, and none may be added."*

So the risk is **not** a conflict of intent. The risk is **implementation surface**: the proposals assume machinery the plugin does not have yet. Two load-bearing assumptions are false against today's code:

1. **A change set can carry heterogeneous item kinds** (metadata patch, new page, move, archive, relation, category, glossary…). Today a change item is *strictly one page + one working content revision* — no item-kind column, `pageid` NOT NULL, unique `(changesetid, pageid)`.
2. **Page metadata is versioned and applied atomically at publish.** Today metadata lives on the `local_handbook_page` row and is **mutated in place**; only the HTML body is versioned in `local_handbook_revision`.

Everything else (glossary, aliases, archive lifecycle, catalogs) is net-new but self-contained. Nothing in the proposals requires relaxing the authority boundary; several items require *extending the staging layer so the boundary can hold for more entity types*.

**Recommendation:** insert a **Phase 0 foundations** step (polymorphic change items + transactional apply-at-publish + granular propose capabilities) before any feature phase. It de-risks all four proposed phases and is where the authority guarantee is either preserved or lost.

---

## 2. The authority boundary today — and why it must be extended, not bypassed

The boundary is enforced in **three independent layers** (all confirmed in code):

1. **No function/tool exists.** `db/services.php` registers 21 functions; the highest-authority verbs are `submit_draft_for_review` and `submit_changeset_for_review`, which only hand a draft to the human review queue. `mcp/lib/handbook.mjs` exposes no approve/publish tool in either `readonly` or `readwrite-drafts` mode.
2. **Capability floor.** Every write function requires at most `local/handbook:edit` (via `helper::require_write`, `classes/external/helper.php:52-61`). Approve/publish/archive are gated by `review`/`approve`/`publish`/`managechangesets`/`manage`, which the `handbook-ai` service account never holds (`DEPLOY.md`; `apiaccess` has empty archetypes and the service is `restrictedusers => 1`).
3. **Content confinement.** `aiaccess ∈ {full, metadata_only, excluded}` is enforced in `helper::assert_not_excluded / assert_writable / content_allowed` plus `aiaccess <> 'excluded'` SQL filters.

**Design rule for the whole expansion (non-negotiable):** every new *apply* action — apply metadata patch, apply move, flip `archived`, restore, publish a glossary term — must execute **only inside the human publish path, gated on a human capability** (`publish` / `managechangesets`, or a new `managelifecycle`), inside a DB transaction. The external/MCP layer may gain only **propose** (staging) functions. Concretely: the AI writes a *proposed payload* onto a change item; a human's publish action reads that payload and applies it. `changeset_service::submit()` (callable by the AI) must never apply anything — it only moves items into review, exactly as today.

This is the single most important thing to hold across every phase below.

---

## 3. Conflicts & tensions with the current architecture

Ordered by how much they block the proposals. Each is grounded in current code.

### 3.1 Change-item model is page-revision-only — **blocker for everything multi-entity**
`local_handbook_changeitem` (`db/install.xml:287-311`) has: `pageid` NOT NULL (FK to page), `revisionid`, `itemstatus`, and a **UNIQUE `(changesetid, pageid)`** index. There is **no `kind`/`itemtype` column**. Consequences vs the proposals:
- **New pages can't be items** — they have no `pageid` yet (proposals want `newpage:*` temp IDs, `CAPABILITIES.md` §13.1 / `GLOSSARY.md` §19).
- **Category-only / relation-only / glossary-only items can't exist** — they aren't tied to a page.
- **A page can't appear twice** (e.g. a content edit *and* a relation change) — the unique index forbids it.
- `changeset_service` (`upsert_draft`, `submit`, `sync_item_for_revision`, `item_status_for_revision`, `recompute_status`) is written entirely around `revisionid` + `page_service` workflow states.
- The observer sync seam (`db/events.php` → `observer::revision_workflow_changed`) is keyed on **revision workflow events**. Non-revision item kinds have no such event source and need a different (or no) sync trigger.
- **No transactions.** `changeset_service` performs multi-row writes with individual `insert/update_record` calls and no `start_delegated_transaction`. Atomic multi-entity publish (a hard requirement, `CAPABILITIES.md` §3) cannot be built on that as-is.

**Required:** add an item-kind column, a payload representation for non-revision changes, and a temp-ID space for new entities; relax the unique index; wrap applies in transactions. This is Phase 0.

### 3.2 Page metadata is not versioned — **fundamental to the "integral versioning" principle**
`CAPABILITIES.md` §3 demands that title, summary, category, dates, audience, relations, and location be versioned and diffable, then applied atomically. Today (`local_handbook_page`, `db/install.xml`): all of these are **columns on the page row, edited in place** by `edit.php` and `manage/categories.php` under human `edit`/`managecategories`. `local_handbook_revision` versions the **body only**. There is no "proposed metadata patch" concept, no metadata diff surface, and no atomic apply.

**Required:** a proposed-metadata patch payload on the change item (partial patch semantics per §6), a before/after metadata diff in the review UI, and a transactional apply-at-publish that writes the page row only when a human publishes. The existing human in-place edit path stays; the AI path stages a patch.

### 3.3 Direct-mutation services vs draft-only authority (categories, relations, archive)
The entities the proposals want the AI to *propose* are today mutated **directly**:
- **Categories:** `manage/categories.php` edits `local_handbook_category` in place (`managecategories`). No merge; "move" = editing `parentid`; the UI only offers 2 levels though the schema (`parentid`) allows deeper.
- **Relations:** `local_handbook_relation` exists and is read-only over the API — but it is **only ever written by `import_service`**. There is **no relation-editing UI or service method** at all.
- **Archive:** `page_service::set_archived()` flips the boolean directly from `view.php` under `publish`.

None of these has a staging representation. To let the AI propose them **without** gaining direct mutation, each needs: (a) a change-item kind that stores the proposed change, and (b) a shared apply method invoked only by the human publish path. Watch for **two divergent code paths** (human direct edit vs staged apply) — they must converge on the same validated service method so staged applies can't skip validation.

### 3.4 Archive is a boolean hide, not a versioned lifecycle
`archived` is a pure hide flag (404 for readers; excluded from search/index/category/paths-progress). There is **no** `replacementpageid`, `redirectmode`, `archivenote`, or reason — confirmed by full field-name grep. Everything in `GLOSSARY.md` §21-26 (archive reasons, replacement page, redirect modes, `page_archive`/`page_restore` change items, archive-impact analysis, restore flow, redirect rendering in `view.php`) is net-new schema + service + reader logic.

### 3.5 Glossary + view-time highlighting + JS are entirely net-new (largest single surface)
No glossary table, no glossary code, no Moodle **filter** plugin (no `db/filter.php`, no filter classes), and **the plugin ships zero JavaScript** — no `amd/`, no build pipeline. The good news: the render pipeline has a single choke point — `local_handbook_render_revision_content` (`locallib.php:412-428`), which runs `format_text` (core filters) then `demote_headings` then `toc_service::add_anchors`. A view-time highlighter slots in there (or as a `filter_*` subplugin), which satisfies the proposal's rule that stored HTML must not be modified (`GLOSSARY.md` §2.1). But the glossary also needs its **own versioned entity + workflow + change-item kinds**, an **accessible tooltip/popover** (the plugin's *first* AMD module + `amd/build` pipeline), and a glossary browse page. Treat glossary as its own project-sized phase.

### 3.6 Slug rename & aliases
`CAPABILITIES.md` §7.3 wants a slug change to keep old URLs resolvable, register aliases, and update internal links. Today slugs are unique, resolved by id-or-slug in `view.php` (no alias table, no redirect). Net-new `local_handbook_pagealias` table + a resolution change in `view.php` + internal-link rewriting. (Slug is metadata, so this rides on 3.2.)

### 3.7 Controlled vocabularies vs free-text metadata
`CAPABILITIES.md` §9 wants stable keys for responsible areas, authorities, modalities, etc. Today `responsiblearea` is free `char(255)`; `category.audiencekey` exists but is always written `''`; no catalog tables. Net-new reference tables + validation so the AI proposes a *key*, not free text. Needed early because metadata proposals (3.2) should validate against catalogs.

### 3.8 Concurrency & atomicity breadth
Optimistic concurrency exists for draft **content** (`revision.timemodified`) and change-set upsert (`expectedpublishedrevisionid`, `expectedtimemodified`). `CAPABILITIES.md` §17 additionally wants tokens for metadata, the category-tree version, and relations. These are net-new, and — see 3.1 — `changeset_service` has no transactions to make a multi-entity apply atomic.

### 3.9 Capability model: granular propose capabilities
`CAPABILITIES.md` §5 proposes `apiproposemetadata / taxonomy / relations / lifecycle / sensitive`. This is **compatible and strengthening** — it refines today's single `edit` write gate. Implement additively: new caps default to empty archetypes (like `apiaccess`), and `helper::require_write` becomes per-operation. Sensitive fields (audience, `aiaccess`, owner/approver, archive, effective date) must be flagged for the reviewer (`CAPABILITIES.md` §5, §16).

### 3.10 Review UI and the review queue assume revisions
`manage/changeset.php` renders each item as page + revision + before/after **content** word-diff, with per-item approve/publish/reject. `review.php` (and the "Approve all" button just added) operate on **revisions** only. New item kinds each need their own diff/preview (metadata diff, relation delta, category-move preview, archive impact, glossary diff) and their own apply affordance. This is real UI work in every feature phase, not an afterthought.

### 3.11 Pre-existing gaps the expansion would expose
- **`path.php` archived leak:** `path.php` selects `p.archived` but never filters/uses it, so an archived page still renders as a normal path item (progress math already excludes it). The lifecycle phase must fix this.
- **Search is not audience-aware:** `search.php` gates only `archived = 0 AND publishedrevisionid > 0` — no cohort/role restriction (paths have `path_service::is_visible`; page search does not). Glossary term surfacing and archive redirects must not leak restricted content, so audience-aware filtering is a **prerequisite hardening**, not a later nicety.

---

## 4. Phased implementation plan

Each phase is independently shippable, bumps `version.php`, adds `db/install.xml` + a `db/upgrade.php` savepoint, ships EN/ES/DE strings, and passes `php -l` + PHPUnit. The authority invariant from §2 is a standing acceptance criterion for **every** phase.

### Phase 0 — Foundations (prerequisite; no user-visible AI feature)
Purpose: make the change set able to hold heterogeneous, atomically-applied proposals without touching the authority boundary.
- **Schema:** add `local_handbook_changeitem.kind` (e.g. `page_revision` default, `page_create`, `page_metadata`, `page_move`, `page_archive`, `page_restore`, `relation_change`, `category_change`, `glossary_*`); add a proposed-payload store (a `payloadjson` column and/or a child table) for non-revision kinds; add a temp-ID column for not-yet-created entities; **relax** the unique `(changesetid, pageid)` index (make `pageid` nullable / default 0). Upgrade step migrates existing items to `kind='page_revision'`.
- **Service:** introduce **transactions** in `changeset_service`; add a `changeset_service::publish_item()` / apply dispatcher that, **gated on human `publish`/`managechangesets`**, applies a payload by kind inside one transaction, resolving temp IDs to real IDs. Keep `submit()` apply-free.
- **Observer:** generalize status sync so non-revision items get a status lifecycle that isn't driven by revision events.
- **Capabilities:** add granular `apipropose*` caps (empty archetypes); split `helper::require_write` per operation.
- **Tests:** temp-ID resolution, atomic rollback on partial failure, and a guard test proving no external/MCP function can reach an apply path.

### Phase 1 — Full page fiche & new pages (proposals' Fase 1)
Depends on Phase 0.
- Proposed-metadata **patch** payload with partial semantics (§6); metadata **diff** in `manage/changeset.php` (§16 governance block); **transactional apply** of the patch at human publish.
- **New-page** change item (temp ID → real page + v1 draft at publish); reject page-without-category (§13, §14).
- **Slug rename + `local_handbook_pagealias`**: unique check, keep old slug resolvable in `view.php`, rewrite plugin-managed internal links, show impact (§7.3).
- **Controlled vocabulary** catalog for `responsiblearea` (at least), with validation (§9).
- **Relation-edit proposals** (create/retype/remove/reorder) as a change-item kind — the first *write* path for `local_handbook_relation`, applied at publish (§10).
- New propose functions/MCP tools mirroring §19 (`upsert_changeset_page` accepting `content`/`metadata`/`relations`; `upsert_changeset_new_page`; `upsert_changeset_relation`) — all draft-only.

### Phase 2 — Taxonomy & content lifecycle (proposals' Fase 3, pulled forward)
Depends on Phase 0/1 (shares the polymorphic item + apply layer). Recommended **before** glossary because it reuses Phase-1 machinery and unblocks archive/restore.
- **Category** change items: create/rename/redescribe/move/merge, with mandatory validation (unique slugs, no cycles, no self-descendant, no orphaned pages, audience compatibility) (§11). Apply at publish; converge with `manage/categories.php` on shared validated methods.
- **Archive/restore lifecycle:** new `page.replacementpageid` / `redirectmode` / `archivenote` / reason columns; `page_archive` / `page_restore` change items; **archive-impact analysis** (canonical policy w/ dependents, quick-guides/templates, required paths, quizzes, glossary anchor, inbound links) (§25); redirect rendering in `view.php` (`notice_only` / `redirect_with_notice` / `automatic_redirect` / `no_redirect`).
- **Fix `path.php`** to hide/mark archived items (§3.11).
- Confirm archived pages retain acks (already true — acks key on immutable `revisionid`) and surface them in an archive view.

### Phase 3 — Institutional glossary (proposals' Fase 2)
Largest net-new surface; independent of Phases 1–2 except the change-item kinds from Phase 0.
- **Entity + versioning:** `local_handbook_glossary_term`, `_glossary_alias`, `_glossary_revision` with their own draft→review→publish workflow (mirrors page workflow; same no-approve/publish boundary) (§5, §15).
- **View-time highlighter** at the render choke point (`local_handbook_render_revision_content`) or as a `filter_local_handbook_glossary` subplugin — **never** modifies stored HTML; DOM-aware, respects the exclusion rules (§11), overlap/precedence, and frequency modes (§12); cached by (revision, language, glossary version) with publish-time invalidation (§13).
- **First AMD module + `amd/build` pipeline** for an accessible tooltip/popover (keyboard, screen reader, touch, no-JS fallback) (§9, §10); `.local-handbook-glossary-term` style in `styles.css`.
- **Glossary browse page** (§14); glossary change-item kinds + propose functions/MCP tools (§17); audit tools for undefined/displaced/conflicting terms — as **findings** (the finding-type list is explicitly open-ended, so `undefined_term`/`displaced_term` slot in).

### Phase 4 — Mass changes & integrations (proposals' Fase 4)
- **Dry-run** bulk operations producing individual change-set items (§18); `validate_changeset` / `preview_changeset` / `get_changeset_impact` read functions (§15).
- Integrations: reading paths, quizzes, attachments, translations (§4.6, §22).

### Cross-cutting (every phase)
- **Audience-aware filtering** hardening (prerequisite, land early) — §3.11.
- **Per-entity concurrency tokens** and transactional apply — §3.8.
- **Audit** trail per operation (technical account, human sponsor, change set, entity, before/after, validations, conflicts) — `CAPABILITIES.md` §21; the `changeset.source='ai'` + truthful `createdby` + separate `authoruserid` pattern already exists to build on.

---

## 5. Sequencing note vs the proposals' §22

The proposals order phases: (1) metadata + new pages, (2) glossary, (3) taxonomy + lifecycle, (4) mass. I recommend **swapping glossary and taxonomy/lifecycle** (my Phase 2 = their Fase 3; my Phase 3 = their Fase 2) because taxonomy/lifecycle reuses the exact Phase-0/1 apply machinery and is small relative to glossary, whereas glossary introduces three first-of-their-kind subsystems (a versioned entity, a text filter, and the AMD/front-end pipeline) that are independent and can proceed in parallel once Phase 0 lands. This is a recommendation, not a correction — the dependency that actually matters is that **Phase 0 precedes all of them**.

---

## 6. Open decisions for the team

1. **Payload storage:** one `payloadjson` column on `changeitem` vs typed child tables per kind. JSON is faster to build and matches the proposals' patch shape; typed tables give referential integrity and easier querying for impact analysis. (Leaning: `payloadjson` for Phase 0/1, promote hot kinds to typed tables if querying demands it.)
2. **New capability for lifecycle apply:** reuse `publish` for archive/restore apply, or add `local/handbook:managelifecycle`? A dedicated cap keeps "who can publish content" separate from "who can archive," at the cost of another role to provision.
3. **Glossary highlighter as a `filter_*` subplugin vs in-plugin post-processor.** Subplugin is the idiomatic Moodle answer and lets the highlight run anywhere `format_text` runs; the in-plugin post-processor keeps everything under `local_handbook` and its scoped CSS. (Leaning: subplugin, so it composes with core caching.)
4. **Controlled-vocabulary scope for Phase 1:** just `responsiblearea`, or the full catalog set (§9) at once? Recommend starting with `responsiblearea` + `criticality`/`contenttype` (already enumerated in `page_service`).

---

## 7. Bottom line

Nothing in either proposal requires weakening the "AI proposes, humans dispose" boundary — both documents reinforce it, and the codebase already encodes it in three layers. The work is to **extend the staging layer** (polymorphic change items, transactional apply-at-publish, granular propose caps) so that boundary can hold for metadata, taxonomy, lifecycle, and glossary — then build those features on top. Do Phase 0 first; it is where the guarantee is kept or lost.
