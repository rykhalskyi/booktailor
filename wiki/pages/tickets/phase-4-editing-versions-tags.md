---
created: 2026-10-22
type: ticket
status: proposed
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

Pending. Fill in editor flow, version mapping, tag model, tests, and deviations when
done.
