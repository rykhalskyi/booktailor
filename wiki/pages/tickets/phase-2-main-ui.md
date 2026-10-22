---
created: 2026-10-22
type: ticket
status: proposed
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

Pending. Fill in components, state decisions, tests, and deviations when done.
