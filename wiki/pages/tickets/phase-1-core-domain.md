---
created: 2026-10-22
type: ticket
status: proposed
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

Pending. Fill in migrations, services, endpoints, tests, and deviations when done.
