# EuropaSchule Institutional Handbook

## Moodle plugin concept and implementation specification

**Proposed component:** `local_handbook`  
**User-facing names:** Manual Institucional / Institutional Handbook  
**Target platform:** Moodle 5.2.1  
**Institution:** EuropaSchule, operated by Educación Helvética S.A.  
**Document status:** Revised project specification (v1.1, incorporates pre-implementation review)  
**Initial audience:** Developers and coding agents responsible for designing and implementing the plugin

---

## 1. Purpose

EuropaSchule needs a single, authoritative institutional handbook inside Moodle. The handbook will contain the policies, procedures, standards, practical guidance, templates, role descriptions, and institutional knowledge required by school personnel.

The plugin will make this information accessible from the Moodle sidebar to authorized staff. It will also expose a secure external API so approved tools such as Codex, ChatGPT, Claude, or administrative scripts can search the handbook, analyze relationships between pages, identify possible contradictions or outdated content, and prepare draft revisions.

The handbook must remain under human institutional control. External agents may read authorized content, create findings, and prepare drafts; publication and policy approval remain governed by Moodle capabilities and the plugin's editorial workflow.

The first major content pathway within the handbook will be **Ser docente en EuropaSchule**, a structured induction and reference guide for teachers for the 2026-2027 school year.

---

## 2. Problem statement

Institutional knowledge currently exists across conversations, documents, Moodle courses, administrative practices, templates, and individual experience. This creates several risks:

- Rules may be communicated differently by different people.
- Two documents may contain contradictory instructions.
- Staff may rely on an outdated version of a procedure.
- Responsibilities and escalation routes may remain unclear.
- New teachers may receive extensive information without a reliable reference source.
- Policy changes may not be reflected in training material or Moodle quizzes.
- Important knowledge may depend too heavily on particular individuals.
- A separate wiki would require another login, another permission system, another interface, and another platform to maintain.

The plugin will centralize the information while using Moodle's existing authentication, permissions, files, editor, logs, search capabilities, and assessment tools.

---

## 3. Product vision

The Institutional Handbook should function as all of the following:

1. **The authoritative source of institutional policy and procedure.**
2. **A practical reference during the school day.**
3. **An induction pathway for new and returning personnel.**
4. **A controlled editorial system with revision history and approval.**
5. **A searchable institutional memory.**
6. **A source corpus for controlled AI-assisted quality review.**
7. **A connection point between written expectations and Moodle assessments.**

The system should feel like a well-organized knowledge base rather than a collection of unrelated Moodle pages.

---

## 4. Guiding principles

### 4.1 One authoritative version

Each rule, procedure, or standard should have one canonical handbook page. Other pages should link to it or summarize it without creating competing versions.

### 4.2 Capability-based access

Access must be determined through Moodle capabilities, not through hard-coded role names. Administrators may assign the capabilities to any suitable role.

Audience-based restrictions are part of the security boundary, not a presentation convenience. They must be enforced through the same central authorization service as capability checks (see section 22.1), never through ad hoc filtering scattered across individual pages or endpoints.

### 4.3 Human-controlled publication

External agents and ordinary editors may create drafts. Only authorized reviewers and publishers may approve or publish institutional content.

### 4.4 Complete revision history

Published content must never be overwritten without retaining the previous revision. The system must show what changed, when it changed, who changed it, and why.

### 4.5 Clear ownership

Every published page should identify the responsible area or owner, its approving authority, its effective date, and its next review date.

### 4.6 Practical writing

Pages should explain expectations, procedures, responsibilities, exceptions, records, and escalation routes in language staff can apply during real situations.

### 4.7 Theme independence

The EuropaSchule child theme may position and style the handbook entry in the Space-based sidebar. The plugin must retain ownership of access control, routes, content, and core functionality. It should remain usable under another compatible Moodle theme.

### 4.8 External access through a controlled API

Codex and other tools should work through authenticated web-service functions or an MCP adapter. Routine handbook work must not depend on direct database access.

### 4.9 Privacy by design

Pages may be marked as available, metadata-only, or excluded from external AI access. Sensitive personnel or student information must not enter an external analysis corpus without explicit authorization.

### 4.10 Progressive implementation

The first release should establish a dependable knowledge base and editorial workflow. Advanced AI analysis, rich visualizations, and secondary features should build on that foundation.

---

## 5. Scope

### 5.1 Included in the initial product scope

- System-level Moodle local plugin.
- Capability-controlled sidebar or primary-navigation entry.
- Hierarchical categories and subcategories.
- Handbook pages with stable slugs and URLs.
- Rich-text editing through Moodle's supported editor, initially TinyMCE.
- Images, diagrams, downloadable files, and other attachments through the Moodle File API.
- Drafting, review, approval, publication, superseding, and archiving.
- Immutable revision history.
- Page metadata, ownership, scope, audience, authority, and review dates.
- Page relationships and internal links.
- Search, filtering, tags, and recently updated content.
- Reading pathways, beginning with teacher induction.
- Required-reading acknowledgements.
- Connections to Moodle quizzes or other Moodle activities used for knowledge checks.
- External read and draft APIs.
- Findings for possible contradictions, duplication, ambiguity, missing information, stale content, and broken relationships.
- Audit events and administrative reports.
- Import and export tools for backup, migration, and controlled bulk editing.
- English and Spanish language strings for the user interface.
- Responsive and accessible presentation.

### 5.2 Possible later extensions

- Page translations connected through translation groups.
- Direct integration with an LLM provider from inside Moodle.
- Semantic or embedding-based search.
- Automated notifications when a connected Moodle quiz may be outdated.
- Visual organizational charts generated from structured role data.
- Policy-signature workflows requiring stronger confirmation.
- Mobile-app integration.
- Public or parent-facing handbook areas.
- Approval rules that vary by category or authority level.
- Collaborative inline comments on draft text.

### 5.3 Explicit non-goals for the first release

- Replacing Moodle courses, course pages, or the course curriculum.
- Replacing Moodle Quiz with a custom assessment engine.
- Becoming a student information system or personnel-management system.
- Storing confidential case files about individual students or employees.
- Acting as a general-purpose document drive.
- Automatically publishing AI-generated policy.
- Depending on Space theme internals for core functionality.
- Editing handbook content directly in Moodle database tables.
- Providing a public website or anonymous access.

---

## 6. Plugin identity and technical direction

### 6.1 Component

Use a Moodle local plugin:

```text
local_handbook
```

The displayed name must come from language strings:

```text
Spanish: Manual Institucional
English: Institutional Handbook
```

### 6.2 Target compatibility

- Initial target: Moodle 5.2.1.
- Follow Moodle 5.2 coding standards and supported APIs.
- Use namespaced, autoloaded classes for application logic.
- Keep `lib.php` limited to callbacks that Moodle requires there.
- Use Moodle DML, Forms, Output, File, Access, External Services, Search, Tag, Event, Task, Cache, and Privacy APIs where applicable.
- Use Mustache templates and Moodle-compatible JavaScript modules.
- Keep presentation compatible with Boost conventions so the child theme can enhance it safely.
- Avoid inline CSS and theme-specific markup in stored handbook content.

---

## 7. Users and roles

### 7.1 Primary users

- Classroom teachers.
- Homeroom teachers.
- Department leaders.
- Academic coordination.
- Student counseling.
- Official directorate.
- Administration.
- Rectorate.
- Moodle administrators.
- Approved external service accounts.

### 7.2 System-level staff access

Moodle teachers commonly receive the teacher role only inside their courses. A system-level handbook cannot rely on those course-level assignments to determine access.

EuropaSchule should create a system-level role such as:

```text
EuropaSchule Staff
```

All employees who require handbook access should receive that role at system context. The role should initially receive `local/handbook:view`. Additional capabilities may be assigned to coordinators, managers, editors, reviewers, and publishers.

The plugin may document or report missing access assignments, but it should not silently infer staff status by scanning course roles.

### 7.3 Initial capabilities

| Capability | Purpose |
|---|---|
| `local/handbook:view` | View published pages available to the user. |
| `local/handbook:viewrestricted` | View pages assigned to restricted staff audiences. |
| `local/handbook:viewhistory` | View earlier revisions and change details. |
| `local/handbook:acknowledge` | Record required-reading acknowledgement. |
| `local/handbook:edit` | Create pages and draft revisions. |
| `local/handbook:review` | Review drafts and request changes. |
| `local/handbook:approve` | Approve a revision for publication. |
| `local/handbook:publish` | Publish, supersede, archive, or restore authorized content. |
| `local/handbook:managecategories` | Manage categories, ordering, and structural relationships. |
| `local/handbook:managepaths` | Create reading paths and assign required content. |
| `local/handbook:managefindings` | Review and resolve quality findings. |
| `local/handbook:viewreports` | View reading, review, publishing, and quality reports. |
| `local/handbook:manageapi` | Configure services and approved external access. |
| `local/handbook:apiaccess` | Use handbook external-service functions when included in an approved service. |
| `local/handbook:manage` | Full plugin administration, excluding Moodle site administration. |

Capabilities must be checked in system context for the initial release. If page-level or category-level contexts are introduced later, they require a separate design decision and migration plan.

---

## 8. Navigation and child-theme integration

### 8.1 Canonical route

The handbook home should have a stable route:

```text
/local/handbook/index.php
```

Example supporting routes:

```text
/local/handbook/view.php?page=slug-or-id
/local/handbook/category.php?id=123
/local/handbook/path.php?id=123
/local/handbook/search.php
/local/handbook/manage/
```

Moodle 5.x includes a routing API. Phase 0 should evaluate using it for slug-based URLs such as `/local/handbook/page/<slug>`, keeping the query-parameter routes above as stable fallbacks.

### 8.2 Visibility rule

The sidebar item must appear only when the current user has:

```php
has_capability('local/handbook:view', context_system::instance())
```

Direct page access must repeat the appropriate capability and audience checks. Hiding the menu item is a usability feature; it is not the security boundary.

### 8.3 Responsibility split

The plugin must:

- Provide the canonical route.
- Enforce capabilities.
- Provide an icon and localized label.
- Mark its pages active in navigation.
- Remain reachable independently of the child theme.

The EuropaSchule child theme may:

- Position the item in the Space-based sidebar.
- Apply EuropaSchule colors and visual treatments.
- Choose an appropriate icon treatment.
- Emphasize required reading or outstanding actions.

The child theme should check whether the plugin is installed before rendering a hard-coded link. The plugin and theme must fail gracefully if either component is temporarily unavailable.

---

## 9. Information architecture

### 9.1 Initial category structure

```text
Manual Institucional
├── Identidad institucional
│   ├── Modelo educativo
│   ├── Fortalezas institucionales
│   ├── Prioridades del año
│   └── Cultura profesional
├── Organización y responsabilidades
│   ├── Estructura institucional
│   ├── Responsabilidades por área
│   ├── Con quién hablar sobre qué
│   └── Escalamiento y autoridad
├── Práctica académica
│   ├── Currículo
│   ├── Planificación
│   ├── Sílabos y temarios
│   ├── Evaluación
│   ├── Calificación
│   └── Moodle
├── Vida estudiantil
│   ├── Supervisión
│   ├── Asistencia y puntualidad
│   ├── Convivencia y disciplina
│   ├── Incidentes
│   ├── Consejería
│   └── Protección del menor
├── Modalidades
│   ├── Presencial
│   ├── Homeschool
│   └── Híbrida
├── Familias y comunicación
│   ├── Comunicación institucional
│   ├── Reuniones
│   ├── Quejas
│   └── Confidencialidad
├── Operaciones diarias
│   ├── Jornada escolar
│   ├── Transiciones
│   ├── Recreos y patio
│   ├── Aulas
│   ├── Kiosco y alimentación
│   ├── Médico escolar
│   └── Emergencias
├── Administración del personal
│   ├── Asistencia laboral
│   ├── Permisos y ausencias
│   ├── Salarios y seguros
│   └── Conducta laboral
├── Manuales por función
│   ├── Docente
│   ├── Docente guía
│   ├── Líder de departamento
│   ├── Coordinación académica
│   └── Consejería estudiantil
├── Procedimientos y guías rápidas
└── Formularios, plantillas y ejemplos
```

Categories must support nesting, manual ordering, visibility, descriptions, and optional audience restrictions.

### 9.2 Page relationships

The plugin should support typed relationships between pages:

- Related to.
- Depends on.
- Implements.
- Replaces.
- Supersedes.
- Exception to.
- Procedure for.
- Quick guide for.
- Template for.
- Assessment connected to.
- Translation of.

Typed relationships will help users navigate the handbook and help external analysis distinguish intentional differences from contradictions.

---

## 10. Page model

### 10.1 Required metadata

Each handbook page should support:

| Field | Description |
|---|---|
| Title | User-facing page title. |
| Slug | Stable, unique URL identifier. |
| Summary | Short description used in search and cards. |
| Category | Primary location in the hierarchy. |
| Sort order | Position within the category. |
| Content type | Policy, procedure, standard, guideline, quick guide, template, example, or role description. |
| Authority level | Configurable precedence used during review and contradiction analysis. |
| Scope | Institution, department, role, modality, grade range, or another defined scope. |
| Audience | Staff groups expected or permitted to read it. |
| Responsible area | Area responsible for maintaining accuracy. |
| Owner | Moodle user responsible for review. |
| Approver | Moodle user or authority responsible for approval. |
| Effective date | Date from which the published revision applies. |
| Review date | Date by which the page should be reviewed. |
| Required reading | Whether acknowledgement is required. |
| Criticality | Reference, operational, mandatory, or safety-critical. |
| AI access | Full content, metadata only, or excluded. |
| Language | Content language. |
| Translation group | Optional relationship between equivalent language versions. |
| Tags | Search and classification tags. |
| Moodle activity link | Optional quiz or activity connected to the page. |
| Created and modified data | Users and timestamps. |

### 10.2 Content format

- Canonical content should initially be clean Moodle HTML created through TinyMCE.
- Use semantic headings beginning with `h2` inside the page body because the page title is the primary heading.
- Automatically generate or preserve stable heading anchors for section linking.
- Sanitize unsupported scripts, event handlers, inline styles, and unsafe embeds.
- Store images and attachments with the Moodle File API.
- Generate a normalized plain-text representation for search and external analysis.
- API responses may offer HTML and normalized plain text.
- Markdown import may be added as a controlled conversion route; round-trip Markdown editing is outside the first release unless it can preserve Moodle editor content reliably.

### 10.3 Authority hierarchy

The hierarchy must be configurable. An initial proposal is:

1. Institution-wide policy approved by rectorate or the competent authority.
2. Official institutional procedure.
3. Departmental standard approved by the responsible coordination.
4. Operational guide or quick guide.
5. Template.
6. Example or explanatory material.

Scope and effective dates must always be considered alongside authority. Two pages may legitimately differ because they apply to different modalities, departments, grades, or dates.

---

## 11. Revision and publication workflow

### 11.1 Revision states

Each page may have one published revision and a newer working revision. Revisions should support:

```text
draft
in_review
changes_requested
approved
published
superseded
rejected
```

Archiving applies to the page as a whole. Superseding applies to an earlier published revision.

### 11.2 Workflow

```text
Create or revise
      ↓
Draft revision
      ↓
Submit for review
      ↓
Review and comments
      ↓
Approve or request changes
      ↓
Publish with effective date
      ↓
Previous published revision becomes superseded
```

### 11.3 Required workflow controls

- Every revision has a sequential version number.
- Every draft records the published revision on which it was based.
- Updates use optimistic concurrency checks to prevent silent overwriting.
- A change summary is required when submitting for review.
- Reviewers may request changes with a reason.
- Publication records the publisher and publication time.
- The page's published-revision pointer (`publishedrevisionid`) is the single authoritative record of what is published; revision status values are historical workflow metadata, never a second source of truth.
- All workflow state transitions happen in one service method inside a database transaction.
- Sequential version numbers are protected against concurrent draft creation by a unique index on page and version number (or equivalent locking).
- A future effective date may be recorded, but scheduled automatic publication is a later feature unless specifically implemented and tested.
- A published page may be archived without deleting its revision history.
- Restoring an older revision creates a new revision based on the old content; it does not erase later history.
- External agents cannot bypass the workflow.

### 11.4 Editorial comparison

The review interface should show:

- Metadata changes.
- Text additions and removals.
- Previous and proposed effective dates.
- Changed relationships.
- Changed audience, scope, authority, or AI-access classification.
- Connected pages and assessments that may require review.

---

## 12. User experience

### 12.1 Handbook home

The home page should include:

- Prominent search.
- Category navigation.
- Required reading still pending.
- Assigned reading paths.
- Quick guides.
- Recently updated pages.
- Important or safety-critical pages.
- Links to forms and templates.
- Editing or review queues for authorized users.

### 12.2 Reader view

The page reader should display:

- Title and summary.
- Page content.
- On-page table of contents generated from headings.
- Effective date and last update.
- Responsible area or owner.
- Content type and scope.
- Related pages and relevant forms.
- Connected Moodle quiz or activity when applicable.
- Required-reading acknowledgement.
- Print-friendly view.
- A way to report a possible error or outdated instruction.

Detailed authority and workflow metadata may be visually compact for ordinary readers while remaining available to editors and reviewers.

### 12.3 Editor view

The editor should provide:

- Metadata form.
- TinyMCE content editor.
- File and image support.
- Relationship selector.
- Preview.
- Change summary.
- Save draft.
- Submit for review.
- Warning when another draft or newer revision exists.
- Validation for missing required metadata.

### 12.4 Review queue

Authorized reviewers should see:

- Drafts awaiting review.
- Changes requested and resubmitted.
- Pages approaching or beyond review date.
- Pages without an owner or approver.
- Findings linked to pages under review.
- Connected assessments that may be affected.

### 12.5 Administration

Administration should include:

- Category and ordering management.
- Content-type and authority configuration.
- Audience configuration.
- Reading-path management.
- Report access.
- API and integration guidance.
- Import and export.
- Search reindexing controls when required.
- Plugin health checks.

---

## 13. Search, tags, and discovery

### 13.1 Search requirements

Search must cover:

- Titles.
- Summaries.
- Normalized page text.
- Tags.
- Responsible areas.
- Content type.
- Scope and audience where permitted.

Results must respect the current user's capabilities and page audience. Restricted page titles and snippets must not leak through search results.

### 13.2 Search implementation

- Integrate published, permitted pages with Moodle's Search API where practical.
- Do not index restricted-audience pages into Moodle global search; serve them only through the plugin's own search, which applies the central authorization service. Global-search `check_access` callbacks are a backstop, not the primary control.
- Provide a dedicated handbook search with filters.
- Use the Tag API for controlled and free tags as appropriate.
- Reindex content when a revision is published, superseded, restored, or archived.
- Draft content should appear only in authorized editorial search views.

### 13.3 Useful filters

- Category.
- Content type.
- Responsible area.
- Audience.
- Scope.
- Tag.
- Updated date.
- Review status.
- Required reading.
- Criticality.

---

## 14. Files, illustrations, diagrams, and visual content

- Use the Moodle File API for all page media and attachments.
- Use separate file areas for page content, attachments, category images, and reading-path images if needed.
- Store meaningful alternative text for images.
- Render diagrams responsively.
- Sanitize SVG uploads or restrict them according to Moodle's security configuration.
- Avoid embedding images as base64 data inside page HTML.
- Preserve file access control through plugin file-serving callbacks, which must apply the central authorization service.
- Key revision content files by revision: use the revision ID as the file-area item ID so every revision permanently owns the files required to render it. Archived and superseded revisions then always retain their historical content.
- Copy the base revision's files into a new draft's file area when the draft is created (copy-on-draft). Removing a file from a draft therefore never affects a published, superseded, or archived revision.

The editor should support hand-created illustrations, externally generated images, flowcharts, and organizational diagrams without making any particular illustration tool a plugin dependency.

---

## 15. Reading paths and teacher induction

### 15.1 Purpose

A reading path is an ordered collection of handbook pages for a particular audience, role, or annual induction process.

The first path should be:

```text
Ser docente en EuropaSchule 2026-2027
```

### 15.2 Initial teacher pathway

1. Institutional identity and annual priorities.
2. Professional culture and conduct.
3. Institutional structure and escalation.
4. Relationship with students and child safeguarding.
5. Daily routines, transitions, and supervision.
6. Homeroom responsibilities and classroom environment.
7. Discipline, incidents, and complaints.
8. Curriculum, planning, and assessment.
9. Moodle standards and digital practice.
10. Presencial, homeschool, and hybrid modalities.
11. Parent communication and institutional writing.
12. Administrative procedures and annual routines.

### 15.3 Path features

- Ordered sections and pages.
- Required and optional items.
- Role or audience assignment.
- School-year version.
- Completion progress.
- Required-reading acknowledgement by page version.
- Links to one or more Moodle quizzes.
- Completion report for authorized managers.
- Ability to preserve an earlier year's path as historical evidence.

### 15.4 Moodle Quiz integration

The plugin should reuse Moodle Quiz rather than implement its own question engine.

Initial integration may consist of stable links between paths or pages and Moodle quiz activities. A later phase may read completion and grade information through supported Moodle APIs.

The system should eventually help identify quiz questions that may require review after a connected handbook page changes. It should not automatically alter quiz questions.

Course module IDs are not stable across course backup and restore. Stored quiz links must be treated as breakable references: the scheduled link checker should verify them, and the interface should surface broken quiz links for repair rather than failing silently.

---

## 16. Required-reading acknowledgements

An acknowledgement records that a user confirmed reading a particular published revision.

It must include:

- User ID.
- Page ID.
- Published revision ID.
- Reading path, if applicable.
- Timestamp.
- Confirmation wording version.

Publishing a materially changed required page should make the new revision available for renewed acknowledgement. The decision to require a new acknowledgement may be set during publication.

Acknowledgement confirms receipt and reading. It does not replace quiz evidence or imply legal consent unless a future, separately reviewed signature workflow is implemented.

---

## 17. External API

### 17.1 Purpose

The API will allow approved tools to read, search, analyze, and prepare content without SSH or direct database access.

Use Moodle External Services and declare functions in `db/services.php`. Every function must validate parameters, system context, capabilities, content access, and return values.

### 17.2 Read functions

Initial read functions should include equivalents of:

```text
local_handbook_list_categories
local_handbook_list_pages
local_handbook_get_page
local_handbook_search_pages
local_handbook_list_page_revisions
local_handbook_get_revision
local_handbook_list_changes
local_handbook_list_relationships
local_handbook_list_paths
local_handbook_export_authorized_corpus
local_handbook_list_findings
```

### 17.3 Draft and finding functions

Initial write functions should include equivalents of:

```text
local_handbook_create_page_draft
local_handbook_create_revision_draft
local_handbook_update_draft
local_handbook_submit_draft_for_review
local_handbook_create_finding
local_handbook_update_finding
local_handbook_add_relationship_suggestion
```

Publishing functions may exist for trusted administrative workflows, but they must require a separate high-level capability. The standard AI service account should not receive it.

### 17.4 API behavior

- Pagination is required for lists and corpus exports.
- Responses should include stable IDs, slugs, version numbers, content hashes, and modification timestamps.
- `list_changes` should support incremental synchronization.
- Draft writes should include the expected base revision or content hash.
- Conflicting writes must fail clearly rather than overwrite newer work.
- Every API write must generate an audit event.
- API responses must omit content that the service account cannot view.
- Pages marked `metadata_only` should return metadata without body content.
- Pages marked `excluded` should be omitted or return a clear access-denied result without revealing sensitive text.
- File transfer must use supported Moodle web-service or file endpoints.

### 17.5 Service accounts

Use separate least-privilege accounts or tokens for:

- Read-only analysis.
- Draft creation and findings.
- Administrative integration, only if later required.

Tokens must be revocable and must not be stored in the plugin repository. Production secrets must remain in secure server or client configuration.

---

## 18. MCP and agent integration

### 18.1 Recommended architecture

The Moodle plugin should expose the authoritative External Services API. A small, separately deployable MCP server may translate that API into focused tools for Codex, ChatGPT, Claude, or other approved clients.

```text
Codex / ChatGPT / approved agent
                ↓
        MCP adapter or API client
                ↓
      Moodle External Services API
                ↓
           local_handbook
```

The MCP adapter is not required for the first plugin skeleton. The API design must make the adapter straightforward to build later.

### 18.2 Proposed MCP tools

```text
handbook_search
handbook_get_page
handbook_list_changes
handbook_get_related_pages
handbook_compare_pages
handbook_create_draft
handbook_update_draft
handbook_submit_for_review
handbook_record_finding
handbook_list_open_findings
```

### 18.3 Agent operating rules

External agents should be instructed to:

- Read current published pages before proposing changes.
- Treat page metadata, scope, authority, and dates as part of the meaning.
- Create drafts instead of modifying published content.
- Provide a concise change summary.
- Cite the pages and sections supporting each finding.
- Distinguish confirmed contradictions from possible contradictions.
- Preserve intentionally different rules for different modalities or roles.
- Avoid accessing pages excluded from AI use.
- Never include handbook access tokens in generated content or logs.

---

## 19. Quality findings and contradiction review

### 19.1 Finding types

The plugin should support findings such as:

- Possible contradiction.
- Duplicate or substantially overlapping content.
- Ambiguous responsibility.
- Missing escalation route.
- Missing required record or form.
- Outdated role, date, or system reference.
- Inconsistent terminology.
- Broken internal link.
- Missing owner or approver.
- Review date exceeded.
- Procedure without a connected policy.
- Policy without an implementable procedure.
- Different rules across modalities without an explicit explanation.
- Connected Moodle assessment may be outdated.
- Accessibility or readability concern.

### 19.2 Finding data

Each finding should record:

- Type.
- Severity.
- Confidence.
- Status.
- Explanation.
- Recommended action.
- Source, such as human, Codex, ChatGPT, Claude, or scheduled audit.
- One or more affected pages.
- Relevant heading anchors or excerpts where appropriate.
- Creation and modification timestamps.
- Assigned reviewer.
- Resolution note.

### 19.3 Finding states

```text
open
under_review
accepted
dismissed
resolved
intentional_difference
```

AI findings are advisory. They must not change page status, authority, or published content automatically.

### 19.4 Contradiction analysis requirements

Analysis must consider:

- Page scope.
- Audience.
- Authority level.
- Effective dates.
- Responsible area.
- Modality.
- Grade range.
- Whether one page supersedes another.
- Whether one page explicitly defines an exception.

This metadata is essential for reducing false findings.

---

## 20. Suggested database entities

Exact field names and indexes should be finalized during implementation. Table names must remain within Moodle database naming limits.

### 20.1 `local_handbook_category`

- `id`
- `parentid`
- `name`
- `slug`
- `description`
- `descriptionformat`
- `sortorder`
- `visible`
- `audiencekey`
- `timecreated`
- `timemodified`
- `createdby`
- `modifiedby`

### 20.2 `local_handbook_page`

- `id`
- `categoryid`
- `slug`
- `title`
- `summary`
- `contenttype`
- `authoritylevel`
- `scopejson`
- `audiencejson`
- `responsiblearea`
- `owneruserid`
- `approveruserid`
- `publishedrevisionid`
- `effectivedate`
- `reviewdate`
- `requiredreading`
- `criticality`
- `aiaccess`
- `language`
- `translationgroupid`
- `sortorder`
- `archived`
- `timecreated`
- `timemodified`
- `createdby`
- `modifiedby`

### 20.3 `local_handbook_revision`

- `id`
- `pageid`
- `versionnumber`
- `status`
- `baserevisionid`
- `content`
- `contentformat`
- `plaintext`
- `contenthash`
- `changesummary`
- `reviewnote`
- `effectivefrom`
- `requiresreacknowledgement`
- `timecreated`
- `timemodified`
- `createdby`
- `modifiedby`
- `reviewedby`
- `approvedby`
- `publishedby`
- `timeapproved`
- `timepublished`

### 20.4 `local_handbook_relation`

- `id`
- `sourcepageid`
- `targetpageid`
- `relationtype`
- `sortorder`
- `timecreated`
- `createdby`

### 20.5 `local_handbook_path`

- `id`
- `name`
- `slug`
- `description`
- `audiencejson`
- `schoolyear`
- `active`
- `quizcmid`
- `timecreated`
- `timemodified`
- `createdby`
- `modifiedby`

### 20.6 `local_handbook_pathitem`

- `id`
- `pathid`
- `pageid`
- `sectionname`
- `sortorder`
- `required`
- `quizcmid`

### 20.7 `local_handbook_ack`

- `id`
- `userid`
- `pageid`
- `revisionid`
- `pathid`
- `confirmationversion`
- `timeacknowledged`

### 20.8 `local_handbook_finding`

- `id`
- `findingtype`
- `severity`
- `confidence`
- `status`
- `summary`
- `explanation`
- `recommendation`
- `source`
- `externalreference`
- `assigneduserid`
- `resolutionnote`
- `timecreated`
- `timemodified`
- `createdby`
- `modifiedby`
- `resolvedby`
- `timeresolved`

### 20.9 `local_handbook_findpage`

- `id`
- `findingid`
- `pageid`
- `revisionid`
- `anchor`
- `excerpt`

### 20.10 Data-model notes

- Use Moodle XMLDB definitions in `db/install.xml`.
- Add unique indexes for category slugs and page slugs as appropriate.
- Add a unique index on `(pageid, versionnumber)` in the revision table to prevent duplicate version numbers under concurrent draft creation.
- `local_handbook_page.publishedrevisionid` is the single source of truth for publication state; do not derive publication decisions from revision status alone.
- Add indexes for review dates, published revisions, workflow status, and common report filters.
- Validate JSON fields against application schemas before storage.
- Consider normalized audience and scope tables later if reporting or permissions require complex queries.
- Do not store access tokens or API secrets in these tables.
- Preserve referential integrity through application logic and appropriate database keys.

---

## 21. Events, logs, notifications, and tasks

### 21.1 Events

Create Moodle events for important actions, including:

- Page created.
- Draft created or updated.
- Draft submitted for review.
- Changes requested.
- Revision approved.
- Revision published.
- Page archived or restored.
- Required reading acknowledged.
- Finding created, accepted, dismissed, or resolved.
- Corpus exported through the API.

### 21.2 Notifications

Possible notification providers:

- Draft submitted to reviewer.
- Changes requested.
- Revision approved or published.
- Page review due soon.
- Page review overdue.
- Required reading assigned or materially updated.

Notification frequency and recipients must be configurable to prevent excessive messages.

### 21.3 Scheduled tasks

Potential scheduled tasks:

- Mark or report pages whose review date is approaching or overdue.
- Rebuild derived plain text or search indexes when needed.
- Check internal links.
- Produce periodic quality summaries.
- Clean abandoned draft files according to a defined retention policy.

Slow analysis and batch work should use scheduled or ad hoc tasks rather than blocking browser requests.

---

## 22. Security and privacy

### 22.1 Security requirements

- Require login for every plugin page and file.
- Check the most specific applicable capability for every page and action.
- Centralize capability and audience checks in a single authorization service class consumed by every surface: reader pages, search indexing, search results, the external API, corpus export, `list_changes`, and file-serving callbacks. No surface may implement its own ad hoc audience filtering.
- Treat theme visibility as presentation only.
- Validate all form and API input.
- Use Moodle sesskeys for state-changing browser actions.
- Use Moodle Forms API where appropriate.
- Use Moodle DML placeholders; do not concatenate untrusted SQL.
- Sanitize handbook HTML and uploaded file types.
- Prevent restricted titles, summaries, and excerpts from leaking through search or API results.
- Use optimistic concurrency for draft updates.
- Log external writes and corpus exports.
- Apply rate limits or operational limits to large exports and expensive searches.
- Keep service tokens outside source control.
- Give AI service accounts the minimum required capabilities.
- Prevent AI service accounts from publishing by default.

### 22.2 Privacy requirements

- Implement the Moodle Privacy API for user-linked data such as authorship, reviews, acknowledgements, findings, and assignments.
- Document which user data is retained as institutional audit history.
- Avoid placing individual student or employee case information in general handbook pages.
- Support AI-access classifications on pages.
- Ensure corpus export respects those classifications and the service account's capabilities.
- Provide administrators with clear information about what an external integration can read or write.

### 22.3 Content classifications for AI access

```text
full
metadata_only
excluded
```

`full` allows authorized content retrieval. `metadata_only` exposes the existence and metadata of the page without its body. `excluded` prevents the page from appearing in an AI corpus or normal external search result.

---

## 23. Accessibility and usability

- Use semantic headings and landmarks.
- Ensure keyboard navigation throughout the reader, editor, and management screens.
- Maintain sufficient color contrast independently of the child theme.
- Provide alternative text for meaningful images.
- Do not communicate status through color alone.
- Make tables responsive or scrollable without hiding content.
- Provide visible focus states.
- Support browser zoom and mobile-width layouts.
- Use clear labels for draft, review, approved, published, and archived states.
- Ensure print views retain headings, links, dates, and page identification.

---

## 24. Performance and caching

- Cache published category trees and navigation data.
- Invalidate relevant caches after category, permission, audience, or publication changes.
- Paginate page lists, revisions, findings, and API exports.
- Avoid loading full page content when only metadata is required.
- Store normalized plain text and content hashes when revisions are saved or published.
- Queue expensive reindexing or batch validation.
- Test permission filtering under realistic staff and content volumes.

The expected EuropaSchule scale is moderate, but the implementation should follow standard Moodle performance practices from the beginning.

---

## 25. Import, export, and backup

### 25.1 Import

Provide a controlled administrative or CLI import route for existing wiki or document content. Initial formats may include JSON and a documented Markdown bundle.

An import must:

- Run first in validation or dry-run mode.
- Report category, page, slug, file, and relationship changes.
- Detect duplicate slugs.
- Preserve source references when available.
- Create drafts rather than immediately publish imported content unless an administrator explicitly chooses a trusted migration mode.

### 25.2 Export

Provide exports suitable for:

- Backup independent of database dumps.
- External analysis.
- Migration.
- Annual archival snapshot.
- Human-readable Markdown or HTML review.

Exports must respect permissions and AI-access classifications.

### 25.3 Moodle backup

Because this is a system-level local plugin, define a documented backup and restore strategy. Do not assume course backup will include handbook data. At minimum, support database and Moodle file backup through normal site backup procedures, plus a plugin-level portable export.

---

## 26. Suggested plugin structure

```text
local/handbook/
├── version.php
├── lib.php
├── settings.php
├── index.php
├── view.php
├── category.php
├── path.php
├── search.php
├── edit.php
├── review.php
├── db/
│   ├── access.php
│   ├── install.xml
│   ├── services.php
│   ├── tasks.php
│   ├── events.php
│   └── messages.php
├── classes/
│   ├── api/
│   ├── event/
│   ├── external/
│   ├── form/
│   ├── local/
│   │   ├── service/
│   │   ├── repository/
│   │   └── validator/
│   ├── output/
│   ├── privacy/
│   ├── search/
│   └── task/
├── manage/
│   ├── categories.php
│   ├── paths.php
│   ├── findings.php
│   ├── reports.php
│   └── import.php
├── cli/
│   ├── export.php
│   ├── import.php
│   ├── check_links.php
│   └── health_check.php
├── templates/
├── amd/
│   └── src/
├── styles.css
├── pix/
├── lang/
│   ├── en/
│   │   └── local_handbook.php
│   └── es/
│       └── local_handbook.php
└── tests/
    ├── external/
    ├── fixtures/
    └── generator/
```

This is a starting structure. Files should be added only when required by implemented functionality.

---

## 27. Development phases

### Phase 0: Repository and architecture

- Create repository and Moodle plugin skeleton.
- Add coding-standard, testing, and contribution guidance.
- Confirm component name and language strings.
- Define capabilities.
- Resolve the schema-blocking open decisions listed in section 32.
- Create initial database schema.
- Evaluate the Moodle routing API for slug-based URLs.
- Establish automated code-quality checks with `moodle-plugin-ci` in continuous integration (Moodle coding-standard checks, PHPUnit, Behat, and JavaScript validation).

### Phase 1: Readable knowledge base

- Categories and nested navigation.
- Published pages.
- Stable slugs and URLs.
- TinyMCE editing for administrators.
- Moodle file handling.
- Capability-based access.
- Sidebar integration point.
- Basic search and filters.
- Responsive reader view.

### Phase 2: Editorial governance

- Draft revisions.
- Review, change requests, approval, and publication.
- Revision comparison.
- Ownership, authority, scope, effective dates, and review dates.
- Events and audit logs.
- Review dashboard.

### Phase 3: External API

- Read and search functions.
- Incremental change listing.
- Authorized corpus export.
- Draft creation and update.
- Finding creation.
- API capability tests and documentation.

### Phase 4: Induction and acknowledgement

- Reading paths.
- `Ser docente en EuropaSchule 2026-2027` pathway.
- Required-reading acknowledgement.
- Moodle quiz links.
- Completion reports.

### Phase 5: Quality-control tools

- Findings dashboard.
- Contradiction and duplication workflow.
- Stale-page and missing-owner reports.
- Internal-link checker.
- MCP adapter specification or separate project.

### Phase 6: Import, export, and visual refinement

- Existing wiki-content import.
- Portable export.
- Annual archive snapshots.
- Enhanced diagrams and illustrations.
- Child-theme visual integration and final usability review.

---

## 28. MVP definition

The minimum viable product is complete when:

- The plugin installs cleanly on Moodle 5.2.1.
- Authorized staff can access it from a stable route.
- The child theme can show the sidebar link based on `local/handbook:view`.
- Students and unauthorized users cannot view the menu content or handbook pages.
- Administrators can create categories and pages.
- Editors can create a draft revision without overwriting published content.
- Authorized users can review and publish a revision.
- Earlier published revisions remain available to authorized users.
- Rich text and images render correctly through Moodle's APIs.
- Published pages are searchable.
- Basic page ownership, effective date, review date, audience, scope, and AI-access metadata work.
- Read-only API functions can list, search, and retrieve authorized published pages.
- A draft-capable API account can create a draft without publishing it.
- Significant actions produce Moodle events or logs.
- Core access, revision, publication, and external-service behaviors have automated tests.

Reading paths, acknowledgements, advanced findings, and Moodle Quiz reporting may follow immediately after the MVP if they would delay a stable core release.

---

## 29. Testing requirements

### 29.1 PHPUnit

Test at minimum:

- Capability enforcement.
- Audience filtering.
- Revision creation and version numbering.
- Workflow transitions.
- Optimistic concurrency failures.
- Publication and superseding.
- Search document preparation.
- API parameter and return validation.
- AI-access filtering.
- Acknowledgements by revision.
- Privacy providers.

### 29.2 Behat or equivalent acceptance coverage

Test at minimum:

- Staff can find and open the handbook.
- Students cannot access it.
- Editor creates and submits a draft.
- Reviewer requests changes.
- Publisher publishes an approved revision.
- Reader acknowledges a required revision.
- Search results respect permissions.
- Child-theme navigation integration remains functional.

### 29.3 Manual quality checks

- Mobile and desktop layouts.
- Keyboard navigation.
- TinyMCE content and file handling.
- Print view.
- Long pages with tables and diagrams.
- Spanish and English interface strings.
- Upgrade from a previous plugin version.
- Backup and portable export.

---

## 30. Coding and maintenance standards

- Follow Moodle coding standards.
- Use strict parameter validation.
- Use autoloaded service and repository classes.
- Keep database access separate from page controllers and rendering.
- Keep business rules in testable service classes.
- Use Moodle renderers and Mustache templates for output.
- Use language strings for all user-visible interface text.
- Avoid hard-coded EuropaSchule colors inside business logic or stored content.
- Document public external functions and data structures.
- Include upgrade steps for every schema change.
- Maintain a plugin changelog.
- Do not introduce external PHP or JavaScript dependencies without a documented reason and license review.
- Keep API behavior backward compatible within a documented version when possible.

---

## 31. Initial implementation decisions

The following decisions are established for the initial design:

1. The plugin is a system-level local plugin named `local_handbook`.
2. The user-facing Spanish name is `Manual Institucional`.
3. Moodle remains the authentication and authorization system.
4. A dedicated system-level staff role will provide reliable staff access.
5. The EuropaSchule child theme may place the link in the Space-based sidebar.
6. The plugin remains functional independently of Space-specific presentation.
7. TinyMCE HTML is the initial canonical page-content format.
8. Moodle files store illustrations and attachments.
9. Published revisions are immutable historical records.
10. AI tools create drafts and findings; humans control publication.
11. Moodle External Services provide the authoritative integration API.
12. An MCP adapter may be developed separately after the API is stable.
13. Moodle Quiz remains responsible for comprehension assessments.
14. Teacher induction is a reading path within the larger institutional handbook.

---

## 32. Open decisions requiring confirmation during development

### 32.1 Decisions that block early schema work

These must be resolved before the Phase 0 database schema is finalized, because they shape table structures, indexes, and the authorization service:

- Whether category audiences use configurable keys, Moodle cohorts, custom roles, or a combination.
- Whether page owners and approvers are individual users, institutional areas, or both.
- Whether API authentication will use dedicated Moodle service tokens, OAuth through an adapter, or both. (Dedicated Moodle service tokens are the pragmatic first-release default.)

### 32.2 Decisions that may be deferred to their relevant phases

- Whether ordinary readers may view the revision history or only a change summary.
- Whether publication requires separate approval and publishing users in every category.
- Which pages may contain restricted administrative or personnel procedures.
- Whether required-reading acknowledgement is mandatory for every annual version or only changed pages.
- How Moodle Quiz completion and grade data should be connected to reading paths.
- Whether imported legacy wiki content should initially remain unpublished pending review.
- Whether page translations are required in the first year.
- Whether Markdown import is needed in the first implementation phase.
- Whether annual PDF snapshots are produced by the plugin or by a separate export service.
- Which authority hierarchy and content types require rectorate approval.
- Which notification channels and reminder frequency should be enabled.

Open decisions should be recorded in the repository rather than resolved silently by a coding agent.

---

## 33. First development milestone

The first coding milestone should produce a safe, reviewable skeleton with:

- Standard Moodle plugin metadata.
- English and Spanish names.
- Database tables for categories, pages, and revisions.
- Initial capabilities.
- A system-capability check protecting `/local/handbook/index.php`.
- A basic handbook home page.
- Category listing.
- Published-page reader.
- Administrative category and page creation.
- One draft and publication workflow.
- A minimal Mustache-based presentation compatible with Boost and the EuropaSchule child theme.
- PHPUnit coverage for access and revisions.
- Installation and developer documentation.

This milestone should be installed first on the Moodle test or mirror environment. The implementation should then be reviewed using one sample category and a small number of pages before adding the external API or importing the complete manual.

---

## 34. Reference documentation

- Moodle 5.2 local plugins: <https://moodledev.io/docs/5.2/apis/plugintypes/local>
- Moodle 5.2 Access API: <https://moodledev.io/docs/5.2/apis/subsystems/access>
- Moodle 5.2 Navigation API: <https://moodledev.io/docs/5.2/apis/core/navigation>
- Moodle 5.2 External Services: <https://moodledev.io/docs/5.2/apis/subsystems/external>
- Moodle 5.2 external-service security: <https://moodledev.io/docs/5.2/apis/subsystems/external/security>
- Moodle 5.2 File API: <https://moodledev.io/docs/5.2/apis/subsystems/files>
- Moodle 5.2 Privacy API: <https://moodledev.io/docs/5.2/apis/subsystems/privacy>
- Moodle 5.2 API overview: <https://moodledev.io/docs/5.2/apis>
- Model Context Protocol specification: <https://modelcontextprotocol.io>
- moodle-plugin-ci: <https://moodlehq.github.io/moodle-plugin-ci/>

---

## 35. Project success criteria

The project succeeds when EuropaSchule personnel have one reliable place to find institutional expectations and procedures; leadership can control, review, and update those instructions; teachers can complete structured induction and return to the material throughout the year; and approved AI tools can help maintain consistency without receiving authority to change institutional policy independently.

---

## 36. Document revision history

- **v1.1** — Incorporated pre-implementation review: mandated a single central authorization service for capability and audience checks across all surfaces (4.2, 13.2, 14, 22.1); made the page's published-revision pointer the single source of truth and added version-number concurrency protection (11.3, 20.10); specified per-revision file areas with copy-on-draft semantics (14); excluded restricted-audience pages from Moodle global search indexing (13.2); noted quiz course-module-ID fragility and link-checker coverage (15.4); added Moodle routing API evaluation for slug URLs (8.1, 27); named `moodle-plugin-ci` for automated quality checks (27, 34); split section 32 into schema-blocking and deferrable decisions; corrected the Model Context Protocol reference (34).
- **v1.0** — Initial project specification.
