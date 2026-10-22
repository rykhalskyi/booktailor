---
created: 2026-10-22
type: plan
status: proposed
summary: Eight-phase delivery plan for BookTailor, from architecture spikes to a packaged Nextcloud release.
---

# BookTailor — Delivery Plan

Part of the [BookTailor epic](../epics/booktailor.md). Requirements live in the
[MVP specification](../specs/booktailor-mvp.md).

## Approach

- Build vertical slices, not layer-by-layer.
- Resolve architecture risks before product code in Phase 0.
- Keep each phase shippable behind the existing Nextcloud app shell.
- Every phase ends with tests, updated documentation, and a recorded outcome.
- Keep the MVP boundary visible: no sharing, no DOCX, no arbitrary reparenting.

## Phase overview

| Phase | Ticket | Goal | Depends on |
|---|---|---|---|
| 0 | [T0 — Foundation](../tickets/phase-0-foundation.md) | Decisions, schema, spikes, contracts | — |
| 1 | [T1 — Core domain](../tickets/phase-1-core-domain.md) | Books, chapters, assets, storage, versions | T0 |
| 2 | [T2 — Main UI](../tickets/phase-2-main-ui.md) | Chapter tree and asset UI | T1 |
| 3 | [T3 — Structures](../tickets/phase-3-structures-numbering.md) | Inclusion, ordering, numbering | T1, T2 |
| 4 | [T4 — Editing & tags](../tickets/phase-4-editing-versions-tags.md) | Editor, history, tags | T1–T3 |
| 5 | [T5 — Composition](../tickets/phase-5-composition.md) | Tag rules, Markdown merge, outputs | T1–T3 |
| 6 | [T6 — AI assistant](../tickets/phase-6-ai-assistant.md) | Langfuse, BYOK, tools, chat | T1–T4 |
| 7 | [T7 — Release](../tickets/phase-7-images-settings-release.md) | Images, settings, polish, packaging | T1–T6 |

## Phase details

### Phase 0 — Foundation and de-risking

**Deliverables**

- Domain glossary and conceptual schema for books, chapters, structures, assets, tags,
  versions, compositions, and AI actions.
- Decision record for the visible-folder layout, including rename/move behavior.
- Spikes:
  - Nextcloud file CRUD, upload, move, and delete in a visible folder;
  - native version listing/restore and sidecar check-in messages;
  - Euro Office / Nextcloud Markdown editor launch and save flow;
  - background-job execution for composition;
  - Langfuse prompt management, observability, and BYOK.
- Draft OpenAPI contract for the MVP endpoints.
- PHPUnit and frontend test harness.
- Initial migrations or a migration plan.

**Exit criteria**

- Schema reviewed against every functional requirement in the spec.
- Each spike has a documented result and a fallback.
- Compose job contract is written down.
- No product UI is required in this phase.

**Open decisions resolved here**

- Visible-folder layout and rename/move behavior.
- “Default chapter” vs default structure.
- Editor integration approach.

### Phase 1 — Core domain and storage

**Deliverables**

- Book CRUD with per-user isolation.
- Default structure created with each book.
- Chapter hierarchy: create, rename, delete cascade; default-structure ordering.
- Asset lifecycle: create empty Markdown, upload, delete, list.
- Tag assignment on assets.
- Visible-folder file storage and metadata synchronization.
- Version listing, check-in message storage, and restore API.
- Owner-scoped API endpoints and unit tests.

**Exit criteria**

- A full Book → Chapter → Asset lifecycle works through the API.
- Files appear in the visible Nextcloud folder.
- Version metadata is recorded for saves with messages.
- Cascade deletion is covered by tests.

**Open decisions resolved here**

- Tag scope (per book vs global).
- Image storage layout inside the asset folder.

### Phase 2 — Main UI

**Deliverables**

- Main layout: chapter tree on the left, structure selector at the top, asset list per
  chapter.
- Expand/collapse with chevrons.
- Add root chapter and add subchapter actions.
- Chapter menu: new asset, upload asset, delete asset, delete chapter, history, agent
  placeholder.
- Asset rows with filename and tags; click to open the editor.
- Confirmation dialogs for destructive actions.
- API client, loading/error/empty states, and basic state management.

**Exit criteria**

- A user can build a chapter tree and manage assets end-to-end in the UI.
- Destructive actions are confirmed and reflected without a page reload.
- The default structure is selected and displayed correctly.

### Phase 3 — Structures and numbering

**Deliverables**

- Structure CRUD and selection; default structure protected.
- Independent inclusion of chapters, including the orphan-child-at-top-level case.
- Per-structure sibling ordering.
- Draft movement rules: reorder siblings, promote/demote one level, attach a promoted
  chapter under another parent; no arbitrary reparenting.
- Numbering service for `1.`, `1.1`, `1.1.1`.
- Structure management screen.
- Invariant tests for cycles, ownership, deletion, and default inclusion.

**Exit criteria**

- Multiple structures produce correct order and numbering.
- Moving a chapter is blocked unless it follows the allowed rules.
- Deleted chapters disappear from every structure.

### Phase 4 — Editing, versions, tags

**Deliverables**

- Open assets in Euro Office or the Nextcloud Markdown editor.
- Save flow with optional check-in message.
- Version history modal with timestamp, author, and message.
- Restore previous version.
- Per-book tag vocabulary: create, rename, delete.
- Assign/remove tags and filter assets by tag.
- Tags management screen.
- History menu wired into the chapter menu.

**Exit criteria**

- Edit → save with message → view history → restore works.
- Tags can be created, assigned, filtered, and deleted safely.
- Deleting a tag removes it from assets and composition rules.

### Phase 5 — Markdown composition

**Deliverables**

- Composition job model with `queued`, `running`, `succeeded`, `failed` states.
- Tag-rule engine with ordered priorities, fallback, and per-chapter behavior.
- Markdown merge with chapter headings and structure-based numbering.
- Image resolution and copying into the output.
- Compose-server contract: input manifest, job request, status, and output.
- Local Markdown worker for the MVP; DOCX deferred.
- Output stored in the visible folder and indexed in the database.
- Compositions screen: list, open, download, delete.

**Exit criteria**

- Any structure can produce a valid Markdown composition.
- Tag rules select the intended assets and fall back correctly.
- Images referenced by included assets survive composition.
- Failed jobs report an actionable error and do not leave partial outputs.

**Open decisions resolved here**

- Exact rule precedence and per-chapter overrides.
- Output naming and location.

### Phase 6 — AI assistant

**Deliverables**

- Langfuse prompt management and observability integration.
- BYOK settings with protected key storage.
- Provider abstraction.
- Foldable right-side chat panel.
- Predefined prompts, user prompts, and free-form chat.
- Read context over books, chapters, assets, structures, and tags.
- Read tools plus propose-and-confirm write tools.
- Confirmation UI for proposed changes.
- Audit log for proposed and applied actions.
- Initial workflows: summary from raw assets, extract terms/lists, book Q&A.

**Exit criteria**

- AI can generate a summary asset, but the user must confirm creation.
- AI cannot mutate data without an explicit confirmation.
- Langfuse traces are visible for each AI call.
- AI keys are never returned to the client after entry.

**Open decisions resolved here**

- Langfuse hosting model.
- Supported providers.
- AI key storage mechanism.
- Provenance metadata for AI-generated assets.

### Phase 7 — Images, settings, release

**Deliverables**

- Image manager: add, replace, and remove images per asset.
- Insert-image flow relies on the Nextcloud Markdown editor.
- Settings screen for AI keys, defaults, and storage information.
- Internationalization and accessibility pass.
- End-to-end tests for the critical flows.
- Performance and security review.
- Version bump, changelog, and packaging instructions.

**Exit criteria**

- Images survive edit and composition flows.
- Critical flows pass end-to-end tests.
- The app can be packaged and installed on a clean Nextcloud 31–34 instance.
- The wiki reflects the shipped state and the index is regenerated.

## Milestones

| Milestone | Phases | Outcome |
|---|---|---|
| M1 — Usable core | 0–2 | Create books, build the tree, manage assets in the UI |
| M2 — Full authoring | 3–4 | Multiple structures, numbering, editing, versions, tags |
| M3 — First composition | 5 | Generate and download a Markdown book |
| M4 — AI assistant | 6 | Safe, observable AI workflows |
| M5 — Release candidate | 7 | Images, settings, tests, packaged app |

## Sequencing and parallelism

- T0 must finish before product code starts.
- The AI/Langfuse spike in T0 can run in parallel with the storage and version spikes.
- T5 can start after T3 in parallel with T4.
- T6 depends on T4 for the editing/tag workflows it builds on.
- T7 is the final integration and release phase.

## Risks and mitigations

| Risk | Mitigation |
|---|---|
| Nextcloud file/version APIs behave differently than expected | Spike in T0; isolate file operations behind a service |
| Chapter ordering across structures becomes inconsistent | Invariant tests; numbering service; single ordering API |
| Sidecar check-in messages drift from native versions | Key metadata by version reference; reconcile on read; spike in T0 |
| Euro Office integration is not scriptable | Confirm launch/save flow in T0; fall back to the Markdown editor |
| Async jobs lose state or run twice | Persist job state; idempotent handlers; retries with clear errors |
| AI writes bypass ownership or confirmation | Server-side validation; proposal table; confirmation endpoint |
| AI keys leak through API responses or logs | Protected storage; write-only settings; redact logs |
| Composition rules are ambiguous | Freeze rule semantics in T5 before UI work; test fallbacks |
| Scope creep into sharing/DOCX | Keep the MVP boundary in the spec; defer explicitly |

## Testing strategy

- **Backend:** PHPUnit unit tests per controller/service; integration tests for
  migrations and file operations in a Nextcloud environment.
- **Frontend:** unit tests for numbering, rule rendering, and state helpers where
  practical.
- **End-to-end:** chapter/asset CRUD, structure switching, version restore, composition,
  AI confirmation.
- **Manual:** editor integration, file visibility, image rendering, and permission
  checks.
- **Static checks:** `composer run lint`, `composer run cs:check`, `composer run psalm`,
  `npm run lint`, `npm run stylelint`.

## Documentation workflow

- Each phase ticket gets its `## Outcome` filled when the phase is done.
- New decisions are appended with `node scripts/wiki.mjs decision`.
- Activity is logged with `node scripts/wiki.mjs log`.
- `wiki/index.md` is regenerated with `node scripts/wiki.mjs index`; never edited by
  hand.
- Run `node scripts/wiki.mjs lint` before closing a ticket.

## Definition of done per phase

- Acceptance criteria in the ticket are met.
- Tests and static checks pass.
- No secrets are committed.
- Spec and plan are updated if behavior diverged.
- Ticket `status` is `implemented` and `## Outcome` lists files and deviations.
- Index and log are updated.
