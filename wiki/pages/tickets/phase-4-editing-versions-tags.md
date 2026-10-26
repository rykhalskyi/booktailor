---
created: 2026-10-22
type: ticket
status: implemented
summary: Integrate the editor, surface version history with check-in messages, and implement tag management.
---

# T4 — Editing, Versions, and Tags

Phase 4 of 8 in the [BookTailor plan](../plans/booktailor-mvp.md). Requirements are in
the [MVP spec](../specs/booktailor-mvp.md); context is in the
[epic](../epics/booktailor.md).

## Spec

Complete the authoring loop: open an asset in the configured editor, save with an
optional check-in message, browse and restore versions, and manage tags.

Scope:

- Editor launch and save flow.
- Check-in messages.
- Version history modal and restore.
- Per-book tag CRUD.
- Tag assignment/removal and filtering.
- Tags management screen.

Out of scope:

- AI-assisted editing.
- Composition.
- Shared books.

## Plan

1. Integrate the editor flow decided in T0:
   - open the asset in Euro Office or the Nextcloud Markdown editor;
   - return to the correct chapter/asset after editing.
2. Implement the save/check-in flow:
   - detect or trigger a save;
   - prompt for an optional check-in message;
   - store the message against the native version reference.
3. Build the history modal:
   - list versions with timestamp, author, and message;
   - preview or open a version;
   - restore with confirmation.
4. Implement per-book tags:
   - create, rename, delete;
   - safe deletion from assets and composition rules;
   - assign/remove tags on assets.
5. Add tag filtering to the asset list.
6. Build the Tags management screen.
7. Wire the chapter menu “History” and “Agent” placeholder actions.
8. Add tests for:
   - version metadata mapping;
   - restore;
   - tag deletion side effects;
   - filter behavior.
9. Update the wiki, decisions, and index.

Acceptance criteria:

- Assets open in the configured editor and save back to Nextcloud.
- A save with a message is visible in history.
- Restoring a version works and does not lose the message history.
- Tags can be created, assigned, filtered, and deleted.
- Deleting a tag removes it from assets and rules.
- Tests pass.

Dependencies:

- T1 version metadata and asset storage.
- T2 chapter/asset UI.
- T3 structure selection (for the current context).

## Outcome

Implemented. Assets now open in the Nextcloud Files/Text flow, history and check-in
messages are available in the UI, and tags are managed and applied per book.

Delivered:

- Editor launch uses the Files app with `dir=<asset folder>&openfile=<fileId>` (D-20,
  D-47).
  `Asset.path` is returned by the API so the client can build the folder URL; a missing
  path falls back to the `/f/{fileId}` route.
- `NodeWrittenEvent` listener updates `assets.updated_at` when a file is written. It does
  not create versions or prompt, so Text autosaves stay native (D-48).
- `GET|PUT /assets/{id}/content` and `GET /assets/{id}/versions/{revisionId}/content`
  expose the content endpoints deferred from T1 (D-49). The history modal uses the
  version content endpoint for previews.
- `GET /capabilities` reports whether Text and `files_versions` are enabled so the UI can
  hide or disable history/check-in when the server lacks them (D-50).
- `VersionHistoryDialog` shows the current revision plus previous versions newest first
  with timestamp, author, size, and check-in message. The current revision has an optional
  check-in message field; historical versions support preview and restore. Restore asks
  for confirmation inline and refreshes history and the asset list.
- Tag management screen for per-book create, edit, and delete. Tags carry a color from a
  fixed 10-color palette, chosen in the create/edit dialog; the manager, asset chips, and
  tag assignment list render the same colored chip. Deleting a tag relies on the native
  cascades to remove it from assets and composition rules, then refreshes the loaded
  asset lists.
- Migration `Version1001Date20261025` adds `booktailor_tags.color` with the palette
  default; the app version is now 1.2.0. Palette colors are enforced server-side and
  chip text contrast is derived client-side (D-53).
- Tags management moved into the app navigation below the book list; the content view is
  the tag manager, and selecting a book returns to its chapters (D-54).
- Chapter menus expose **Edit assets**, which opens a dedicated asset-management view for
  that chapter with a Back to chapters button (D-55). The view lists the chapter's assets,
  supports create/upload, and gives each row a Tags button plus editor, history, and delete
  actions. Tag chips also act as filter shortcuts; filtering is client-side for the
  selected chapter (D-51). Asset chips on the chapter tree show one colored dot per tag,
  using the same palette.
- Chapter and asset menus expose History. Chapter History opens an asset picker for the
  chapter (D-52); the Agent action remains an explicit placeholder.
- Asset rows now expose Open in editor, Edit tags, History, and Delete.

Deviations:

- Check-in is explicitly triggered from History after editing instead of prompting on
  every save. Text autosaves, and D-21 labels the current revision, so a save-time modal
  would be noisy and could attach the message to the wrong revision.
- Save observation only refreshes metadata; it does not notify the client. Users return
  to the asset list and open History when they want to check in.
- Tag filtering is scoped to the selected chapter, not a book-wide asset search.
- The chapter History action opens an asset picker instead of showing versions for every
  asset in the chapter.

Verification:

- `npm run check` (18 files / 74 tests)
- `npm run build`
- `composer run lint`
- `composer run cs:check` (via `php-cs-fixer --using-cache=no`)
- `composer run psalm` (no errors)
- `composer run test:unit` (37 tests / 175 assertions, including version content,
  capability, and tag color tests)
- Migration applied on the dev server (`occ upgrade`, current migration
  `1001Date20261025`).
- HTTP smoke: capabilities → create book/chapter/tag/asset → write content → assign tag →
  check in → list versions → delete book; then create a colored tag, recolor it with a
  color-only PATCH, rename it, and delete the book.

Main files: `lib/Listener/AssetWriteListener.php`,
`lib/Controller/CapabilitiesController.php`, `lib/Controller/AssetController.php`,
`lib/Controller/VersionController.php`, `lib/Service/SerializerService.php`,
`src/App.vue`, `src/components/AssetList.vue`, `src/components/VersionHistoryDialog.vue`,
`src/components/TagManager.vue`, `src/components/TagEditDialog.vue`,
`src/components/TagAssignmentDialog.vue`, `src/components/ChapterHistoryDialog.vue`,
`src/utils/tags.ts`,
`lib/Service/TagColor.php`, `lib/Migration/Version1001Date20261025.php`,
`src/components/AssetList.vue`, `src/api/client.ts`, `src/stores/useLibrary.ts`,
`src/utils/files.ts`, `src/utils/assets.ts`.
