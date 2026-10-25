---
created: 2026-10-22
type: ticket
status: implemented
summary: Add custom structures with independent chapter inclusion, sibling ordering, draft move rules, and numbering.
---

# T3 — Structures and Numbering

Phase 3 of 8 in the [BookTailor plan](../plans/booktailor-mvp.md). Requirements are in
the [MVP spec](../specs/booktailor-mvp.md); context is in the
[epic](../epics/booktailor.md).

## Spec

Implement structures as named, ordered, partially inclusive views of a book's chapter
tree. Each structure stores its own sibling order and numbering; the chapter hierarchy
itself remains global to the book.

Scope:

- Structure CRUD and selection; default structure protected.
- Independent inclusion of chapters.
- Orphan-child-at-top-level rendering.
- Per-structure sibling ordering.
- Draft movement rules.
- Numbering service.
- Structure management screen.
- Invariant tests.

Out of scope:

- Composition.
- Sharing.
- Arbitrary drag-and-drop reparenting.

## Plan

1. Finalize the structure membership/order schema:
   - inclusion flag per structure/chapter;
   - sibling position;
   - effective parent for orphan children.
2. Implement the structure service:
   - create, rename, delete;
   - prevent deletion of the default structure;
   - include/exclude chapters;
   - reorder siblings.
3. Implement chapter movement validation:
   - allow sibling reorder;
   - allow promote/demote one level;
   - allow attaching a promoted chapter under another parent;
   - reject arbitrary reparenting and cycles.
4. Implement the numbering service:
   - compute `1.`, `1.1`, `1.1.1` from the selected structure;
   - handle orphan children at top level;
   - keep numbering stable after reorder.
5. Build the structure management screen:
   - create/delete structures;
   - include/exclude chapters;
   - reorder and move;
   - preview numbering.
6. Wire the structure selector into the main UI.
7. Add tests for:
   - independent inclusion;
   - numbering;
   - move rules;
   - deletion from structures;
   - default-structure invariants.
8. Update the wiki, decisions, and index.

Acceptance criteria:

- A book can have multiple structures.
- A structure can include a child without its parent; the child appears at top level
  with its own numbering.
- Sibling order changes only the selected structure.
- Disallowed moves are rejected with a clear message.
- Numbering matches the draft examples.
- Tests pass.

Dependencies:

- T1 core hierarchy and ordering.
- T2 main UI.

## Outcome

Implemented. Structures are now first-class ordered, partially inclusive views with
server-side numbering, guarded chapter movement, and a management view.

Delivered:

- Structure CRUD with default-structure protection. New structures copy the default
  entries, including inclusion and sibling positions.
- Full entry replacement for a structure with these invariants:
  - every book chapter appears exactly once;
  - unknown, duplicate, and cross-book chapters are rejected;
  - the default structure cannot exclude a chapter;
  - positions are normalized per canonical sibling group.
- New chapters inherit inclusion from their canonical parent in custom structures;
  new root chapters are included.
- `StructureTreeBuilder` centralizes effective-parent and ordering logic; `NumberingService`
  produces `1.`, `1.1`, `1.1.1` and the chapter serializer returns `number`.
- Excluded chapters are promoted into their canonical slot rather than ordered against
  positions from unrelated sibling groups, so orphan order stays deterministic and
  user-controllable.
- Chapter movement rules: promote to the grandparent, demote under the previous sibling,
  attach under a sibling of the current parent, and promote to the root. Arbitrary
  reparenting and cycles are rejected.
- Movement updates the visible chapter folder, rolls back the folder on database failure,
  and appends the moved chapter to every structure sibling group.
- Structure timestamps advance when chapters are added or moved, not only when entries
  are replaced.
- Structure manager view with structure create/rename/delete, include/exclude toggles,
  sibling reordering, allowed-target chapter moves, and a numbering preview. Entry writes
  are blocked while the editor does not hold the selected structure, and chapter moves
  require an explicit target.
- API client support for structure CRUD, entries read/write, and chapter movement.

Deviations:

- Structure management is an in-app view opened from the main toolbar rather than a
  separate route.
- Entry changes save immediately instead of using a batch save action.
- Chapter moves use an allowed-target picker; drag-and-drop reparenting remains out of
  scope.

Verification:

- `npm run check` (12 files / 42 tests)
- `npm run build`
- `composer run lint`
- `composer run cs:check`
- `composer run psalm`
- `composer run test:unit` (includes ordering, timestamp, cascade, and controller
  validation tests)
- HTTP smoke: create book → chapters → custom structure → entry replacement → promote
  a chapter to root → verify numbering → delete book

Main files: `lib/Service/StructureService.php`, `lib/Service/StructureTreeBuilder.php`,
`lib/Service/NumberingService.php`, `lib/Service/ChapterService.php`,
`lib/Controller/StructureController.php`, `lib/Controller/ChapterController.php`,
`src/components/StructureManager.vue`, `src/components/MoveChapterDialog.vue`,
`src/api/client.ts`, `src/utils/movement.ts`, `tests/integration/`.

