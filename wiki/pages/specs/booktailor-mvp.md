---
created: 2026-10-22
type: spec
status: proposed
summary: Requirements, domain model, storage, API, and non-functional rules for the BookTailor MVP.
---

# BookTailor — MVP Specification

Part of the [BookTailor epic](../epics/booktailor.md). Delivery sequencing lives in the
[plan](../plans/booktailor-mvp.md).

## Context

BookTailor is a Nextcloud app for organizing Markdown-based books. A **Book** is a
workspace; its **Chapters** form a hierarchy; each chapter holds Markdown **Assets**
that may be raw sources, intermediate notes, summaries, or final text. **Structures**
define ordered, partially inclusive views of the chapter tree. A **Composition** turns a
structure into a final Markdown document using tag-based selection rules.

The repository is currently a Nextcloud app template. The MVP turns it into the
authoring and Markdown-composition product described here.

## Actors and permissions

- Authenticated Nextcloud users own their books.
- In the MVP, a book has exactly one owner; sharing and group permissions are out of
  scope.
- Every backend operation checks that the current user owns the target book.
- Composition jobs run with the app's service account but write outputs owned by the
  book owner.
- Admin-only configuration is limited to app enablement and, if needed, global AI
  defaults.

## Functional requirements

### Books

- Create, list, rename, and delete books.
- Deleting a book requires confirmation and cascades to its chapters, structures,
  assets, tags, compositions, and app metadata.
- Each new book gets a **default structure** containing all chapters.
- The book list shows at least name, last modified time, and chapter/asset counts.
- Books are isolated per user; no cross-user listing.

### Chapters

- Chapters are hierarchical and support arbitrary practical depth.
- Users can create root chapters and add subchapters.
- Users can rename a chapter.
- Users can delete a chapter; deletion requires confirmation and removes the entire
  subtree, including all assets and their metadata.
- Sibling order is stored per structure, not globally.
- Movement rules in the MVP:
  - reorder siblings within a structure;
  - promote or demote a chapter by one level;
  - attach a promoted chapter under another parent at that level;
  - no arbitrary drag-and-drop reparenting.
- Chapter numbering is computed from the selected structure: `1.`, `1.1`, `1.1.1`.
- The default structure always includes every chapter.
- The draft's “default chapter” is interpreted as the default structure; confirm during
  T0.

### Structures

- Create, rename, and delete structures; the default structure cannot be deleted.
- A structure selects chapters independently: including a child does not require
  including its parent.
- If a chapter is included but none of its ancestors are, it appears at the top level
  of that structure with its own numbering.
- Sibling ordering is stored per structure and is independent of other structures.
- Users select the current structure from the main UI.
- Invariants:
  - no cycles in the global chapter hierarchy;
  - every referenced chapter belongs to the same book;
  - the default structure always includes all chapters;
  - deleted chapters are removed from every structure.

### Assets

- Create an empty Markdown asset in a chapter.
- Upload an existing Markdown file into a chapter.
- Delete an asset with a confirmation dialog.
- List assets under a chapter with filename and tags.
- Open an asset in the configured editor (Euro Office or the Nextcloud Markdown
  editor).
- Asset names must be unique within a chapter; the app resolves collisions.
- Assets are stored as files in Nextcloud and indexed by app metadata.
- Deleting an asset removes it from the chapter and the metadata index; native
  Nextcloud trash/version behavior applies to the file itself.
- Assets may have zero or more tags.

### Tags

- Tags are managed per book in the MVP (open question: global user vocabulary).
- Create, rename, and delete tags.
- Assign and remove multiple tags on an asset.
- Filter the asset list by tag.
- Deleting a tag removes it from all assets and from composition rules that reference it.
- Tags are free-form labels; they carry no behavior except composition matching.

### Versions

- Use Nextcloud native file versions for snapshots.
- On save, the user may provide an optional check-in message.
- Store check-in messages and author in native Nextcloud version metadata (D-19); no
  app-side version table.
- Show a history list sorted newest first, with timestamp, author, and check-in
  message.
- Restore a previous version; restoration follows Nextcloud's native behavior and makes
  the restored content current.
- Version history is available from the chapter menu and the editor flow.

### Editing

- Open an asset in Euro Office or the Nextcloud Markdown editor, depending on
  configuration.
- Saving from the editor updates the file and creates a native version.
- BookTailor records the optional check-in message at save time.
- Image insertion is handled by the Nextcloud Markdown editor.
- Users return to the chapter tree without losing their place.

### Composition

- Create a composition from a selected structure.
- Composition runs as an asynchronous job.
- Composition uses tag-based rules:
  - an ordered list of tag priorities;
  - fallback to the next matching tag when no asset matches;
  - exact rule shape and per-chapter overrides are decided in T5.
- For each included chapter, matching assets are selected and merged in a defined order.
- The merged Markdown includes chapter headings and structure-based numbering.
- Images referenced by included assets are copied or rewritten so the output is
  self-contained.
- Output is stored in the book's visible folder; metadata and status are stored in the
  app database.
- Composition status: `queued`, `running`, `succeeded`, `failed`, with an error message
  on failure.
- The Compositions screen lists outputs with status, creation time, structure, and
  actions to open, download, or delete.
- The compose-server contract is defined in T0/T5. The MVP implements a local Markdown
  worker; DOCX output is deferred.

### AI assistant

- Use Langfuse for prompt management and observability.
- Support bring-your-own-key (BYOK) configuration.
- Provide a foldable chat panel on the right side of the main UI.
- AI has read access to books, chapters, assets, structures, and tags.
- Predefined prompts plus user-defined prompts plus free-form chat.
- AI tools include reads and proposed writes:
  - proposed writes create, edit, or delete chapters/structures/assets only after user
    confirmation;
  - no silent mutations.
- Initial use cases:
  - create a summary asset from raw assets;
  - extract terms or lists into a new asset;
  - answer questions about the current book.
- Record proposed and applied AI actions in an audit log.
- Provider abstraction and supported providers are decided in T6.

### Images

- Images associated with an asset are stored alongside it.
- Users can add, replace, and remove images through the UI.
- Markdown image references are resolved by the Nextcloud Markdown editor.
- Composition must preserve or copy referenced images into the output.
- Allowed image types and size limits follow Nextcloud configuration; the app
  validates before accepting.

### UI and navigation

The Nextcloud navigation contains:

- **Chapter tree** — main authoring screen.
- **Structures** — structure management.
- **Compose** — create a composition.
- **Compositions** — list generated outputs.
- **Tags** — tag management.
- **Settings** — AI keys and defaults.

Main screen:

- Chapters on the left, with chevrons for expandable nodes.
- Current structure selector at the top.
- Asset list under each chapter; each row shows filename and tags.
- Add root chapter button at the top of the tree.
- Chapter three-dot menu:
  - **Asset**: new file, upload file, delete file (with confirmation);
  - **Add subchapter**;
  - **Delete chapter** (with confirmation);
  - **History**;
  - **Agent**.
- Destructive actions always require confirmation.
- Empty, loading, and error states are defined for every screen.
- The layout is responsive enough for desktop and tablet; mobile-specific layouts are
  out of scope.

### Settings

- Configure AI provider and BYOK credentials.
- Show the book storage location.
- Configure default editor/composition preferences where applicable.
- Keys must never be exposed in the UI after entry and must be stored using
  Nextcloud's secret storage or an equivalent protected mechanism.

## Domain model (conceptual)

The reviewed schema, glossary, invariants, and storage rules are in
[domain-schema](../research/domain-schema.md). Summary:

```
books(id, user_id, name, description, root_file_id, created_at, updated_at)
chapters(id, book_id, parent_id, name, file_id, created_at, updated_at)
structures(id, book_id, name, is_default, created_at, updated_at)
structure_chapters(structure_id, chapter_id, included, position)
assets(id, chapter_id, name, file_name, file_id, created_at, updated_at)
tags(id, book_id, name, slug)
asset_tags(asset_id, tag_id)
compositions(id, book_id, structure_id, structure_name, status, output_file_id, output_path, error, created_at, completed_at)
composition_rules(id, composition_id, tag_id, priority, fallback)
ai_action_log(id, user_id, book_id, action, payload, status, created_at)
```

Notes:

- The chapter hierarchy is global to the book; only ordering and inclusion are
  structure-specific.
- Effective parents are derived from inclusion at read time; they are not stored
  (D-17).
- `file_id` points to the Nextcloud file; the file remains the content source of
  truth, the database stores metadata and stable file IDs.

## Storage layout

The reviewed layout and rename/move rules are in
[domain-schema](../research/domain-schema.md). Summary:

```
/BookTailor/
  <Book>/
    <Chapter>/
      <asset>.md
      images/<image files>
    <nested chapter>/
    compositions/
      <composition>.md
```

- The folder tree mirrors the canonical chapter hierarchy and is visible in the user's
  Nextcloud Files (D-13).
- Structures never affect the on-disk layout.
- Renaming or moving a chapter renames/moves its folder subtree; file IDs stay stable.
- The app database is the metadata and ordering source of truth.
- Composition outputs are written under the book's `compositions/` folder unless T5
  decides otherwise.

## API surface (proposed)

Exact routes and schemas are defined in the T0 OpenAPI draft. Endpoints are
owner-scoped and use Nextcloud authentication.

| Area | Endpoints |
|---|---|
| Books | list, create, read, update, delete |
| Chapters | list tree, create, update, move, delete |
| Structures | list, create, update, delete, include/exclude, reorder |
| Assets | list, create empty, upload, read, delete, assign/remove tags |
| Versions | list, restore |
| Compositions | create, list, status, download, delete |
| Tags | list, create, rename, delete |
| AI | chat, prompts, propose, confirm, audit |
| Settings | read/update AI keys and defaults |

## Non-functional requirements

- **Isolation:** every request and background job enforces book ownership.
- **File safety:** all file operations go through Nextcloud APIs; never write directly
  to the data directory.
- **Validation:** Markdown-only asset uploads; image type/size validation; path
  traversal protection; filename sanitization.
- **Security:** CSRF protection for UI routes; no secrets in logs; AI keys protected at
  rest; validate all AI-proposed operations server-side.
- **Performance:** lazy-load the chapter tree; paginate large asset lists; run
  composition asynchronously; avoid N+1 queries in tree/list endpoints.
- **Reliability:** composition jobs are idempotent and persist status; failed jobs
  report actionable errors.
- **Accessibility:** keyboard navigation, focus management, ARIA labels, readable
  contrast.
- **Localization:** all user-facing strings translatable; number/date formatting
  localized.
- **Observability:** Nextcloud logs for jobs and errors; Langfuse traces for AI calls.
- **Compatibility:** Nextcloud 31–34, PHP 8.1+.
- **Testing:** PHPUnit for backend; frontend unit tests where practical; end-to-end
  tests for the critical authoring and composition flows.

## Decisions

The full decision record is in [decisions.md](../../decisions.md). Key decisions:

- `D-01` — **Book** is the workspace; **Composition** is the generated output.
- `D-02` — Multiple books per user; no sharing in the MVP.
- `D-03` — Assets live in a visible dedicated Nextcloud folder.
- `D-04` — **Asset** is the umbrella term for Markdown files inside a chapter.
- `D-05` — Native Nextcloud versions (sidecar messages superseded by D-19).
- `D-06` — Markdown composition first; DOCX later.
- `D-07` — Only the draft chapter-move rules in the MVP.
- `D-08` — Structures include chapters independently.
- `D-09` — Composition uses tag-based selection rules.
- `D-10` — AI proposes changes; the user confirms them.
- `D-11` — Compose contract now; separate service later.
- `D-12` — Core domain first, then structures, composition, and AI.
- `D-13` — Visible folders mirror the canonical chapter tree; file IDs are stable.
- `D-14` — The default chapter is the default structure; no separate concept.
- `D-15` — Tags are scoped per book in the MVP.
- `D-16` — Images live in a chapter-level `images/` folder with relative references.
- `D-17` — Effective parents are derived; structure entries store inclusion and order.
- `D-18` — Deletion uses native Nextcloud trash; app metadata cascades.
- `D-19` — Check-in messages and author use native version metadata, not a sidecar table.

## Assumptions and open questions

Resolved in T0 (see [domain-schema](../research/domain-schema.md)):

- The default structure replaces the draft's “default chapter”.
- Tags are scoped per book.
- The visible-folder layout and rename/move behavior follow D-13/D-18.
- Images use a chapter-level `images/` folder.
- Versions and check-in messages use native `files_versions` metadata.

Still open:

- Composition rule precedence, fallback, and per-chapter overrides are decided in T5.
- Euro Office integration details are confirmed in T0/T4.
- Langfuse hosting, provider list, and key storage are decided in T6.
- Composition output naming/location is decided in T5.
- Sharing and permissions are post-MVP.
- AI-generated assets may need provenance metadata; decide in T6.

## Out of scope for the MVP

- Book sharing, group permissions, and collaborative ownership.
- DOCX composition and the separate compose server implementation.
- Arbitrary chapter reparenting.
- A custom rich-text editor; use Euro Office / Nextcloud editors.
- Mobile-specific UI.
- Public APIs for third-party clients beyond the app's own frontend.
