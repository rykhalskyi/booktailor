---
created: 2026-10-22
type: ticket
status: proposed
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

Pending. Fill in schema, services, UI, tests, and deviations when done.
