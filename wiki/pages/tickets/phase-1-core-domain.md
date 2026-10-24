---
created: 2026-10-22
type: ticket
status: implemented
summary: Implement the book, chapter, asset, tag-assignment, storage, and version-metadata backend.
---

# T1 — Core Domain and Storage

Phase 1 of 8 in the [BookTailor plan](../plans/booktailor-mvp.md). Requirements are in
the [MVP spec](../specs/booktailor-mvp.md); context is in the
[epic](../epics/booktailor.md).

## Spec

Implement the backend foundation for books, chapters, assets, and versions. The result
is a complete lifecycle through the API, with files stored in a visible Nextcloud
folder and metadata in the app database.

Scope:

- Book CRUD and owner isolation.
- Default structure creation.
- Hierarchical chapter CRUD and cascade deletion.
- Asset create/upload/delete/list.
- Tag assignment.
- Visible-folder storage and metadata synchronization.
- Version listing, check-in messages, and restore API.
- Unit tests for domain rules and endpoints.

Out of scope:

- The main chapter-tree UI.
- Custom structures beyond the default.
- Editor integration.
- Composition and AI.

## Plan

1. Add migrations for the schema agreed in T0.
2. Implement ownership checks and scoped queries for every book resource.
3. Implement the book service:
   - create, list, rename, delete;
   - create the default structure in the same transaction;
   - cascade cleanup on delete.
4. Implement the chapter service:
   - create root and child chapters;
   - rename;
   - delete subtree and assets;
   - enforce hierarchy invariants.
5. Implement the storage service:
   - map assets to the visible folder;
   - create empty Markdown files;
   - upload and validate Markdown;
   - delete files and metadata;
   - handle filename collisions.
6. Implement tag assignment:
   - assign/remove tags on assets;
   - create tag rows as needed per the T0 tag-scope decision.
7. Implement native version access:
   - list versions for an asset;
   - read and write check-in messages in the sidecar table;
   - restore a version.
8. Expose owner-scoped API endpoints and document them.
9. Add unit tests for:
   - ownership isolation;
   - cascade deletion;
   - filename collisions;
   - version metadata mapping;
   - file operations (mocked or integration).
10. Update OpenAPI and the wiki.

Acceptance criteria:

- A user can create a book, chapters, and assets through the API.
- Files are visible in the Nextcloud folder and survive rename/restart.
- Deleting a chapter removes its subtree and assets.
- A save with a check-in message is recorded and readable.
- A previous version can be restored.
- Tests and static checks pass.

Dependencies:

- T0 schema, storage-layout, and version spikes.

## Outcome

Implemented. The core domain and visible-folder lifecycle work through the OCS API and are covered by integration tests.

Delivered:

- Migration `Version1000Date20261024` creates the T0 schema: books, chapters,
  structures, structure entries, assets, tags, asset tags, compositions, composition
  rules, and AI action log, with foreign keys and cascades. `compositions.started_at` is
  present for stale detection.
- Entities and query-builder mappers for the core records; join-table mappers for
  structure entries and asset tags.
- `StorageService` owns visible folders, filename sanitization, de-duplication
  (` (2)`, ` (3)`, …), file-id lookup, renames, trash deletes, and relative paths.
- `BookService`, `ChapterService`, `AssetService`, `TagService`, `StructureService`, and
  `VersionService` implement the lifecycle with owner scoping and 404 for non-owned
  resources.
- OCS routes under `/ocs/v2.php/apps/booktailor/api/v1`: books CRUD, chapter tree
  (canonical and structure view with lazy-load parameters), chapter create/rename/delete,
  structure list/show/entries, asset list/create/upload/get/rename/delete, tag
  CRUD/assignment, and version list/check-in/restore.
- Version check-in attaches the message to the current native revision (D-21); history
  is sorted newest first and marks the current row. The current revision is materialized
  via `getVersionsForFile()` before writing metadata. Restore uses the native backend.
- Integration tests create temporary users and exercise the lifecycle, ownership
  isolation, collisions, cascade deletion, tags, and version check-in/restore. The full
  suite runs 8 tests / 50 assertions, including 7 integration lifecycle tests. The test
  bootstrap loads only BookTailor and `files_versions`.
- `composer run lint`, `composer run cs:check`, `composer run psalm`, and
  `composer run test:unit` pass. Psalm was upgraded to 6.20.1 with `files_versions`
  stubs and `doctrine/dbal` so static analysis runs on PHP 8.5.
- The T0 draft was updated for implementation: `Book.description`/`Book.path`,
  `Asset.tags`, no canonical `Chapter.position`, and check-in as a 200 on the current
  revision. `openapi.json` remains the generated skeleton because the extractor requires
  full per-route docblocks; the reviewed contract is `openapi.draft.yaml`.
- App version bumped to 1.1.1 and the dev database migrated.

Deferred:

- Chapter movement (promote/demote/attach) is T3; `PATCH` with a different parent returns
  409.
- Custom structure writes and entry reordering are T3.
- Raw asset/version content endpoints are T4; the Text app and Files API are the editing
  path.
- Compositions, AI, and settings endpoints are T5/T6/T7; their tables exist but are not
  wired.

Main files: `lib/Migration/`, `lib/Entity/`, `lib/Db/`, `lib/Service/`,
`lib/Controller/`, `tests/integration/CoreDomainTest.php`, `psalm.xml`,
`build/stubs/files_versions.php`.
