---
created: 2026-10-22
type: ticket
status: proposed
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

1. Define the compose job contract:
   - input manifest (book, structure, chapter selection, asset selection, tag rules);
   - job request/status/result shape;
   - output naming and location;
   - error and retry semantics.
2. Implement the composition job:
   - persist `queued` state;
   - run asynchronously;
   - update `running`, `succeeded`, or `failed`;
   - store an actionable error on failure.
3. Implement the tag-rule engine:
   - ordered tag priorities;
   - fallback when no asset matches;
   - per-chapter behavior;
   - deterministic ordering of selected assets.
4. Implement Markdown merge:
   - select a Markdown merge library or a minimal merge implementation, and record
     the choice;
   - chapter headings and structure numbering;
   - asset content in selected order;
   - image references rewritten or copied;
   - self-contained output folder.
5. Implement local Markdown worker for the MVP.
6. Store outputs in the visible folder and index them in the database.
7. Build the Compose screen and Compositions list:
   - create a composition;
   - show status/progress;
   - open/download/delete outputs.
8. Add tests for:
   - rule matching and fallback;
   - merge output;
   - image handling;
   - job failure and retry;
   - output permissions.
9. Update the wiki, decisions, and index.

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

Pending. Fill in rule semantics, job contract, merge behavior, tests, and deviations
when done.
