---
created: 2026-10-22
type: ticket
status: implemented
summary: Build the main chapter-tree and asset-management UI on top of the core API.
---

# T2 — Main UI

Phase 2 of 8 in the [BookTailor plan](../plans/booktailor-mvp.md). Requirements are in
the [MVP spec](../specs/booktailor-mvp.md); context is in the
[epic](../epics/booktailor.md).

## Spec

Deliver the primary authoring screen: a chapter tree with expandable nodes, asset lists,
a structure selector, and the chapter/asset actions from the draft. This phase uses the
default structure only; custom structures arrive in T3.

Scope:

- Main layout and navigation entry.
- Chapter tree with chevrons and expand/collapse.
- Asset list per chapter.
- Add root chapter and add subchapter.
- Chapter menu actions.
- Asset create/upload/delete actions.
- Confirmation dialogs.
- API client, state handling, loading/error/empty states.

Out of scope:

- Custom structures and numbering.
- Version history UI.
- Tag management UI.
- AI panel.

## Plan

1. Define the frontend state model for the selected book, chapter tree, selected
   structure, and expanded nodes.
2. Build the API client for books, chapters, and assets.
3. Build the main layout:
   - chapter tree on the left;
   - structure selector at the top;
   - asset list under the selected/expanded chapter.
4. Implement tree rendering:
   - recursive nodes;
   - chevrons for expandable chapters;
   - keyboard and focus behavior;
   - empty and loading states.
5. Implement chapter actions:
   - add root chapter;
   - add subchapter;
   - rename;
   - delete with confirmation.
6. Implement asset actions:
   - create empty Markdown asset;
   - upload Markdown;
   - delete with confirmation;
   - open in the editor (basic integration; full flow in T4).
7. Render asset rows with filename and tags.
8. Add error handling and optimistic or revalidated updates after mutations.
9. Add frontend tests for tree helpers and destructive-action guards.
10. Update the wiki and regenerate the index.

Acceptance criteria:

- A user can build a multi-level chapter tree in the UI.
- Creating, uploading, and deleting assets refreshes the tree without a full reload.
- Destructive actions require confirmation.
- The default structure is selected and displayed.
- Loading, empty, and error states are usable.
- Lint and frontend checks pass.

Dependencies:

- T1 core API.

## Outcome

Implemented. The main authoring screen now drives the T1 API with a chapter tree, structure selector, asset panel, dialogs, and the required states.

Delivered:

- OCS API client in `src/api/client.ts` for books, structures, chapter tree/mutations, and asset list/create/upload/delete. It unwraps the OCS envelope, surfaces API messages, adds the request token and `OCS-APIRequest` header, and uses `generateOcsUrl()`.
- Module-level `useLibrary` composable for books, structures, the chapter tree, selection, per-chapter assets, expanded nodes, loading/error state, and a typed dialog state.
- Book navigation with create-book flow and active book selection.
- Structure selector using the default structure, with the T1 structure read endpoint.
- Recursive chapter tree with chevrons, expand/collapse, selection, keyboard-focus styles, and empty/loading states.
- Chapter actions: add subchapter, new asset, upload file, rename, and delete with confirmation.
- Asset panel for the selected chapter: filename, tag chips, open in editor via `/index.php/f/{fileId}`, new asset, upload, and delete with confirmation.
- Central `DialogHost` for create/rename prompts and destructive confirmations; all deletes require a confirmation dialog.
- Responsive two-pane layout that stacks on narrower screens.
- Frontend tests for tree helpers, destructive confirmation guards, OCS envelope/error handling, and the App empty state: 4 files / 8 tests.

Deviations:

- Assets render in a right-hand pane for the selected chapter rather than nested under every tree node. This keeps the tree compact and loads only the selected chapter's assets; the per-chapter list and refresh behavior still meet the acceptance criteria (D-31).
- The structure selector is read-only in T2; custom structure writes are T3.
- Chapter movement is not exposed in the UI; it remains deferred to T3.
- Opening an asset uses Nextcloud's direct file route (`/index.php/f/{fileId}`); full editor integration and history are T4.

Verification:

- `npm run lint`
- `npm run stylelint`
- `npm test` (4 files / 8 tests)
- `npm run build`
- Headless Chromium smoke: create book → add root chapter → add subchapter → create asset → upload asset → delete both with confirmation → delete chapter with confirmation; no console or API errors.

Main files: `src/App.vue`, `src/api/client.ts`, `src/stores/useLibrary.ts`,
`src/components/ChapterTree.vue`, `ChapterNode.vue`, `AssetList.vue`,
`LibraryToolbar.vue`, `DialogHost.vue`, `TextInputDialog.vue`, `ConfirmDialog.vue`,
`src/utils/tree.ts`, `.eslintrc.cjs`, `tests/js/`.
