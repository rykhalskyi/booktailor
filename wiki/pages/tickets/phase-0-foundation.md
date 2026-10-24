---
created: 2026-10-22
type: ticket
status: in-progress
summary: Resolve architecture, schema, storage, versioning, editor, job, and AI unknowns before product code.
---

# T0 — Foundation and De-risking

Phase 0 of 8 in the [BookTailor plan](../plans/booktailor-mvp.md). Requirements are in
the [MVP spec](../specs/booktailor-mvp.md); context is in the
[epic](../epics/booktailor.md).

## Spec

Resolve the decisions and technical unknowns that would otherwise force rework in later
phases. This phase produces no user-facing feature; it produces a reviewed architecture,
a schema, working spikes, and contracts.

Scope:

- Domain glossary and conceptual schema.
- Visible-folder storage layout and rename/move behavior.
- Nextcloud file, version, editor, and background-job capabilities.
- Langfuse and BYOK feasibility.
- OpenAPI draft and test harness.
- Confirm whether “default chapter” means the default structure.

Out of scope:

- Product UI.
- Full CRUD implementation.
- AI features beyond feasibility.

## Plan

1. Write the domain glossary and schema draft covering books, chapters, structures,
   structure membership/order, assets, tags, version metadata, compositions, composition
   rules, and AI action logs.
2. Decide the visible-folder layout:
   - human-readable chapter mirror vs stable identifiers;
   - behavior on chapter rename, move, and delete;
   - image folder convention.
3. Spike Nextcloud file operations through the app APIs:
   - create folder, upload Markdown, read, rename/move, delete;
   - confirm files appear in the user's Files.
4. Spike native versions:
   - list versions, restore a version;
   - store and retrieve a check-in message keyed to a version;
   - confirm version identifiers are stable enough for sidecar metadata.
5. Spike editor integration:
   - launch Euro Office and/or the Nextcloud Markdown editor for an asset;
   - confirm the save path and how BookTailor observes a save.
6. Spike background jobs:
   - enqueue, run, and report status for a composition-like job.
7. Spike Langfuse and BYOK:
   - trace a prompt;
   - store and use a user-provided key without exposing it.
8. Draft the OpenAPI surface for books, chapters, structures, assets, versions, tags,
   compositions, AI, and settings.
9. Set up test harnesses and document how tests run in the dev environment.
10. Record decisions and open items in the wiki.

Acceptance criteria:

- Schema covers every MVP requirement and has no unresolved relationship.
- Each spike has a documented result, including a fallback where relevant.
- The compose job contract is written down.
- The OpenAPI draft is reviewed.
- The “default chapter” ambiguity is resolved.
- Tests can run locally or in the Nextcloud dev container.

Risks:

- Native version identifiers may not map cleanly to sidecar messages.
- Euro Office may not expose a scriptable launch/save flow.
- A visible human-readable folder may be expensive to keep in sync on moves.

## Outcome

In progress.

Done:

- Local Nextcloud dev setup is wired (app bind-mounted into `apps-extra`, Vite entry
  fixed, build documented in [Build & Deploy](../build-deploy.md)).
- Domain glossary, conceptual schema, invariants, and visible-folder rules are in
  [Domain schema and storage layout](../research/domain-schema.md).
- Decisions D-13 through D-18 recorded in [decisions.md](../../decisions.md).
- File-operations spike is complete and documented in
  [Spike — Nextcloud file operations](../research/spike-file-operations.md):
  stable file IDs, idempotent create calls, stale-node behavior, native trash, and DAV
  visibility verified.
- Version spike is complete and documented in
  [Spike — Native versions and check-in messages](../research/spike-versions.md):
  listing, rollback, native author, and custom version metadata verified; D-19 drops
  the sidecar table.
- Editor spike is complete and documented in
  [Spike — Markdown editor integration](../research/spike-editor.md): the Text app is
  the Markdown editor, saves are observed via node events, and check-in labels the
  current revision (D-20/D-21). Text was installed in the dev stack from stable35.

Remaining:

- Background-job spike.
- Langfuse/BYOK spike (waiting for instance details).
- OpenAPI draft and test-harness verification.
- Compose-job contract.
