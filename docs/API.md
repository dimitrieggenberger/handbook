# local_handbook external API

Moodle External Services (REST) interface for approved tools — Claude,
ChatGPT/Custom GPT actions, scripts, and later the MCP adapter (spec §17–18).

## One-time site setup

1. Site administration → General → Advanced features → enable **web services**.
2. Site administration → Server → Web services → Manage protocols → enable **REST**.
3. Create a service account user (e.g. `handbook-ai`), give it a system role
   containing: `local/handbook:apiaccess`, `local/handbook:view`,
   `local/handbook:edit` and (recommended) `local/handbook:viewhistory`.
   **Never** grant `local/handbook:publish` to this role (§17.3).
4. Site administration → Server → Web services → External services →
   **Institutional Handbook API** → Authorised users → add the service account.
5. Manage tokens → create a token for the service account on that service.
   Store the token outside any repository (§17.5); it is revocable there too.

## Calling convention

`POST https://<site>/webservice/rest/server.php` with form fields:

- `wstoken` — the token
- `wsfunction` — one of the functions below
- `moodlewsrestformat=json`
- the function's own parameters

Example — read a page:

```bash
curl -s "https://learn.europaschule.eu/webservice/rest/server.php" \
  -d "wstoken=$TOKEN" \
  -d "wsfunction=local_handbook_get_page" \
  -d "moodlewsrestformat=json" \
  -d "identifier=supervision-durante-los-recreos"
```

Example — edit cycle (draft → update → submit for review):

```bash
# 1. Create a draft based on the published revision you just read.
curl -s .../server.php -d "wstoken=$TOKEN" -d "moodlewsrestformat=json" \
  -d "wsfunction=local_handbook_create_revision_draft" \
  -d "identifier=supervision-durante-los-recreos" \
  -d "expectedpublishedrevisionid=123"
# → returns {"id": 456, "timemodified": 1789000000, ...}

# 2. Update it, passing the concurrency token back.
curl -s .../server.php -d "wstoken=$TOKEN" -d "moodlewsrestformat=json" \
  -d "wsfunction=local_handbook_update_draft" \
  -d "revisionid=456" -d "expectedtimemodified=1789000000" \
  --data-urlencode "content=<h2>Objetivo</h2><p>...</p>" \
  --data-urlencode "changesummary=Ajuste de zonas por la obra"

# 3. Hand it to the human review queue.
curl -s .../server.php -d "wstoken=$TOKEN" -d "moodlewsrestformat=json" \
  -d "wsfunction=local_handbook_submit_draft_for_review" \
  -d "revisionid=456" \
  --data-urlencode "changesummary=Ajuste de zonas por la obra"
```

## Functions

Read (§17.2) — require `apiaccess` + `view`:

| Function | Purpose |
|---|---|
| `local_handbook_list_categories` | Visible categories. |
| `local_handbook_list_pages` | Page summaries; filters: `categoryid`, `includearchived`; paginated. |
| `local_handbook_get_page` | One page + its published revision content. |
| `local_handbook_search_pages` | Search title/summary/plaintext of published pages. |
| `local_handbook_list_page_revisions` | Revision metadata, newest first. |
| `local_handbook_get_revision` | One revision with content when permitted (superseded needs `viewhistory`; working revisions need `edit`/`review`). |
| `local_handbook_list_changes` | Pages modified since a timestamp; returns `servertime` as the next cursor. |
| `local_handbook_list_relations` | Typed relations of a page, both directions. |
| `local_handbook_list_findings` | Quality findings (default: open + under review), with affected pages. |
| `local_handbook_get_context_index` | Compact index of every AI-permitted page — metadata, category path, published hash, whether a working draft exists, relations both directions — **without content** (§36.6). Load first for cross-handbook analysis. |
| `local_handbook_get_working_page` | The page's current working draft (content when permitted), its status and owning change set; never changes state (needs `edit`). |
| `local_handbook_get_changeset` | One change set with its per-page items and statuses. |
| `local_handbook_list_changesets` | Change sets, filterable by `status`/`source`. |

Draft writes (§17.3) — additionally require `edit`:

| Function | Purpose |
|---|---|
| `local_handbook_create_page_draft` | New page + v1 draft (unpublished). |
| `local_handbook_create_revision_draft` | New draft based on the published revision; `expectedpublishedrevisionid` guards against a stale base. |
| `local_handbook_update_draft` | Update draft content; `expectedtimemodified` is mandatory (conflict = clear error, never overwrite). |
| `local_handbook_submit_draft_for_review` | Move the draft into the human review queue. Change summary required. |
| `local_handbook_create_finding` | Advisory quality finding (contradiction, outdated reference, …) citing one or more pages with anchors/excerpts. Never changes content (§19.3). |

Change sets (§36.4) — grouped multi-page proposals, additionally require `edit`:

| Function | Purpose |
|---|---|
| `local_handbook_create_changeset` | Create a change set (marked source `ai`). |
| `local_handbook_upsert_changeset_draft` | Create or update the set's draft for **one** page. Reuses the same editable draft on repeat calls (`expectedtimemodified`); returns a structured conflict — never an overwrite — for a human draft, a foreign change set's draft, an in-review revision, a stale published base (`expectedpublishedrevisionid`), or a concurrency mismatch. Never publishes. |
| `local_handbook_submit_changeset_for_review` | Submit the set's eligible drafts; returns a per-page result (conflicts are skipped, not forced). |

There is **no publish and no approve function** — for single drafts or change
sets. Review, approval and publication are human UI actions, and this is
asserted by an automated test.

## AI-access rules (§17.4)

Per-page `aiaccess` field, enforced on every function:

- `full` — metadata + content.
- `metadata_only` — metadata only; content fields come back empty
  (`contentincluded: false`); draft writes are refused.
- `excluded` — omitted from every list/search; direct access returns a
  clear access-denied error; draft writes are refused.

Every write fires a Moodle event (`draft_updated`, `draft_submitted`,
`page_created`) for the audit log, attributed to the service account.
