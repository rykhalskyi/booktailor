---
created: 2026-10-22
type: ticket
status: implemented
summary: Build asynchronous Markdown composition driven by tag rules, with output management and a compose-service contract.
---

# T5 — Markdown Composition

Phase 5 of 8 in the [BookTailor plan](../plans/booktailor-mvp.md). Requirements are in
the [MVP spec](../specs/booktailor-mvp.md); context is in the
[epic](../epics/booktailor.md).

## Spec

Generate a final Markdown document from a structure. Assets are selected by tag-based
rules, merged in chapter order, and written to the book's visible folder as an
asynchronous job. DOCX output is deferred to the separate compose service.

Scope:

- Composition job model and status.
- Tag-rule engine with priority and fallback.
- Markdown merge and chapter numbering.
- Image resolution/copying.
- Compose-service contract.
- Output storage and metadata.
- Compositions screen.

Out of scope:

- DOCX generation.
- The separate compose server implementation.
- AI-driven composition.

## Plan

The job contract is already frozen in
[compose-job-contract](../research/compose-job-contract.md): `ComposeJob extends
QueuedJob`, argument `{compositionId}`, conditional `queued → running` claim, explicit
retry, owner-scoped output. T5 implements it locally; no separate server.

### Slice 0 — Freeze the T5 semantics (record decisions first)

1. **Rule precedence.** Sort rules by `priority` ascending (ties by rule id). Evaluate
   non-fallback rules first; the first rule with at least one tagged asset in the
   chapter wins. Only if none match, evaluate `fallback` rules in priority order the
   same way. A chapter matched by a rule includes every asset carrying that tag,
   ordered by asset name then id. An asset is included at most once.
2. **No match.** A chapter that matches no rule still emits its heading and an empty
   body. If the whole composition matches zero assets it fails with
   `No assets matched the composition rules`.
3. **Empty rule list.** An empty rule list means "all assets in filename order" (a
   useful baseline composition); it is not an error.
4. **No per-chapter overrides in the MVP.** The schema only supports
   composition-wide rules; do not add a per-chapter rule table.
5. **Merge layout.** Output starts with `# <book name>`, then each included chapter
   as `## <number> <name>` for depth 1, `###` for depth 2, capped at `######`.
   Numbering comes from `NumberingService` (`1.`, `1.1`, …). Asset content is
   preserved (LF-normalized, trailing blank lines trimmed) and joined with two blank
   lines.
6. **Images.** Rewrite `![...](images/<name>)`, `./images/<name>`, and
   `<img src="images/...">` references. Copy referenced chapter images into
   `compositions/images/`, reusing one destination per source file id and suffixing
   collisions with ` (2)`, ` (3)`, …. A missing local image fails the composition with
   the asset and path in the error; remote/absolute URLs are left untouched.
7. **Output naming.** `compositions/<slug>-<YYYYMMDD-HHMMSS>.md` in UTC, where the
   slug is the optional `name` hint or the structure name; collisions get ` (2)`, ….
   No new column is needed; `output_path` is the snapshot.
8. **Retry/stale.** Stale threshold stays 30 minutes, computed at read time. Retry is
   allowed only for `failed` or stale `running`; a retry resets the row to `queued`
   and clears `error`, `started_at`, `completed_at`, and output fields. No automatic
   retries and no attempts column in the MVP.
9. **Delete.** Only terminal (`succeeded`, `failed`) compositions can be deleted;
   delete the markdown output but leave shared `compositions/images/` in place.

Record these as decisions before coding; they close the T5 open questions in the
compose contract.

### Slice 1 — Backend model and API

- Add `Entity/Composition.php` and `Entity/CompositionRule.php`
  (`bookId`, `structureId`, `structureName`, `status`, `outputFileId`,
  `outputPath`, `error`, timestamps; rule `tagId`, `priority`, `fallback`).
- Add `Db/CompositionMapper.php` (list by book with status/limit/offset, get,
  insert/update, conditional claim, delete) and `Db/CompositionRuleMapper.php`
  (batch fetch by composition ids, replace rules).
- Add `Service/CompositionService.php`:
  - `create` validates the structure belongs to the book and every rule tag belongs
    to the book, normalizes priorities, inserts composition + rules in one
    transaction, then enqueues `ComposeJob` via `IJobList`.
  - `list`, `get` (owner-scoped via `BookService`), `retry`, `delete`, `download`.
  - `stale` computed from `started_at` for `running` rows.
- Add `Controller/CompositionController.php` with the routes already in the
  OpenAPI draft: `GET|POST /books/{bookId}/compositions`,
  `GET|DELETE /compositions/{compositionId}`,
  `POST /compositions/{compositionId}/retry`,
  `GET /compositions/{compositionId}/download` (markdown response, owner only).
- Extend `SerializerService` with `composition`/`compositionRule`; include rules in
  list responses using one batched query.
- No migration: `booktailor_compositions` and `booktailor_composition_rules` already
  exist from T1.

### Slice 2 — Rule resolution and Markdown merge

- Add `Service/CompositionRuleResolver.php`: pure selection over
  chapter → tagged assets → ordered rules, with fallback and the empty-rules rule.
  Keep it free of Nextcloud file IO for unit testing.
- Add `Service/MarkdownComposer.php`: structure tree (reuse
  `StructureTreeBuilder` + `NumberingService`), heading rendering, content joining,
  image-reference parsing/rewriting, and image destination allocation.
- Use a minimal in-app merge rather than a new Composer dependency; record the
  reason (no transformation beyond concatenation/numbering is needed).

### Slice 3 — Local compose job and output storage

- Add `BackgroundJob/ComposeJob.php extends QueuedJob`, injecting
  `CompositionService` and `LoggerInterface`.
- Worker steps: claim with the conditional update, load composition/book/structure
  and all assets/tags/rules in batched queries, resolve the tree, merge, copy images
  and write markdown to a temp file under the book's `compositions/`, rename into
  place, then persist `succeeded` with `output_file_id`, `output_path`, and
  `completed_at`.
- Catch `Throwable`, persist a short user-safe `failed` error, log ids/duration
  without content, and leave no partial output.
- Extend `StorageService` with `compositionsFolder(Book)` and image-copy helpers;
  never trust a client path and never write outside the owner's folder.
- Skip already-`succeeded` rows; deleted books/structures exit or fail with an
  actionable message.

### Slice 4 — Compose and Compositions UI

- Add `Composition`/`CompositionRule` types and `api.compositions` methods
  (`list`, `create`, `get`, `remove`, `retry`, `download`).
- Add Compose and Compositions navigation items in `App.vue` (under Book tools, like
  Tags), with `composeOpen`/`compositionsOpen` state in `useLibrary` reset on book
  change.
- `ComposeScreen.vue`: structure selector (default selected), ordered rule editor
  (tag, priority up/down, fallback toggle, remove, add), optional output-name hint,
  validation and submit; show queued confirmation.
- `CompositionsList.vue`: status, structure, created time, error, actions Open in
  Files, Download, Retry (failed/stale), Delete (terminal only); poll every 3 s while
  any row is `queued`/`running`, stop on unmount; empty/loading/error states.

### Slice 5 — Verification and documentation

- PHP integration tests: create/list/get, ownership isolation, structure/tag
  validation, rule resolution and fallback, empty rules, heading/numbering output,
  image copying and link rewriting, missing image failure, no-match failure,
  success/failure job transitions, retry rules, delete rules, tag-deletion cascade,
  and book deletion.
- PHP unit tests for `CompositionRuleResolver` and `MarkdownComposer`.
- Vitest: API client, compose form/rule editor, list polling/actions, store state.
- Run `composer lint`, `composer cs:check`, `composer psalm`, `composer test:unit`,
  and `npm run check` + `npm run build`.
- Update the ticket Outcome, decisions, log, and regenerate the index.

Risks / checks:

- OCS download must return `text/markdown` (use a file/stream response, not a JSON
  `DataResponse`).
- Cron may be absent; the row must remain `queued` and the UI must say so.
- Large books: one batched asset/tag query and a single in-memory markdown build are
  acceptable for the MVP, but avoid per-asset queries.
- Deep trees: cap headings at level 6 while keeping the number.
- Shared `compositions/images/` means downloaded markdown alone may not carry
  images; note this limitation or return a zip later. The Files link keeps the
  output self-contained on disk.

Acceptance criteria:

- A structure can produce a valid Markdown composition.
- Tag rules select the intended assets and fall back correctly.
- Chapter numbering in the output matches the selected structure.
- Images referenced by included assets survive composition.
- Failed jobs report an error and do not leave partial outputs.
- Outputs appear in the Compositions screen and can be downloaded.
- Tests pass.

Dependencies:

- T1 asset storage.
- T2 UI shell.
- T3 structures and numbering.

## Outcome

Implemented on `feature/t5-composition`.

- Decisions D-56–D-61 freeze rule precedence, no-match/empty-rule behavior, heading
  layout, image copying, output naming, and retry/delete semantics.
- Added `Composition`/`CompositionRule` entities, mappers, serializer support, and the
  `CompositionController` routes (list/create/show/delete/retry/download).
- Added `CompositionService`, `CompositionRuleResolver`, `MarkdownComposer`, and
  `ComposeJob`; `create` persists the row and enqueues `{compositionId}` in one
  transaction.
- Added `Version1002Date20261026` for the optional `output_name` hint and bumped the
  app to 1.3.0.
- Frontend: composition types and API client methods, a Compose view with an ordered
  tag-rule editor (fallback, reordering, duplicate validation), and a Compositions
  view with status polling, Files open, download, retry, and confirmed delete.
- Backend verification: 50 PHPUnit tests (215 assertions), Psalm clean, PHP-CS-Fixer
  clean, full PHP lint clean.
- Frontend verification: 85 Vitest tests in 20 files, ESLint/stylelint/vue-tsc clean,
  production build succeeds.
- HTTP smoke on the dev stack: create book/chapter/asset → create composition →
  execute `ComposeJob` → `succeeded` with output path → download returned the merged
  Markdown.

Known limitation: a downloaded Markdown file does not include the shared
`compositions/images/` folder; opening the output in Files keeps it self-contained.
No automated browser smoke was added in this slice.
