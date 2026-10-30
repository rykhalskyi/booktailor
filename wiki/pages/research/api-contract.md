---
created: 2026-10-24
type: research
status: implemented
summary: T0 OpenAPI draft — OCS base path, resource shapes, async composition, BYOK settings, and review notes.
---

# API contract (T0 draft)

Part of [T0 — Foundation](../tickets/phase-0-foundation.md). The machine-readable draft
is [openapi.draft.yaml](../../../openapi.draft.yaml); it validates with Redocly and has 52
operations across books, chapters, structures, assets, versions, tags, compositions, AI,
and settings.

## Conventions

- Base path: `/ocs/v2.php/apps/booktailor/api/v1`. The `/api/v1` prefix is deliberate
  (D-26); the current skeleton uses `/api` and will move during T1.
- OCS envelope: every response is `{ocs: {meta, data}}`. Lists use
  `meta.totalitems`/`meta.itemsperpage` with `limit`/`offset`.
- Auth: the current user owns every book. Non-owned resources return **404**, not 403, to
  avoid disclosing existence.
- IDs: database ids are integers; Nextcloud file ids are integers; version revision ids
  are strings (native timestamps).
- Mutations that touch files use the Node API; the visible folder is authoritative for
  file identity, while the DB stores file ids (D-13).
- No secrets are returned. BYOK keys are write-only and masked on read (D-23).

## Resource surface

| Area | Endpoints |
|---|---|
| Books | list, create, get, rename, delete |
| Chapters | tree, create, get, rename/move, delete |
| Structures | list, create, rename, delete, list entries, replace entries |
| Assets | list, create empty, upload, get, rename, delete, read/write content |
| Versions | list, check in, restore, read version content |
| Tags | list per book, create, rename, delete, list/replace asset tags |
| Compositions | list, create/queue, get, delete, retry, download |
| AI | chat, action audit, confirm/reject, prompt list/create/update/delete |
| Settings | AI settings status; per-user provider profiles and write-only BYOK keys |

## Contract choices worth reviewing

- **Structure entries are replace-all.** `PUT /structures/{id}/entries` requires every
  chapter exactly once with `included` and `position`; this makes ordering and inclusion
  atomic and avoids a chatty per-entry API. Reject missing/duplicate chapters with 422.
- **Composition creation is `202`, not `201`.** The row exists immediately with
  `status=queued`; the worker runs asynchronously. `POST /compositions/{id}/retry`
  returns `202` for failed/stale rows and `409` otherwise.
- **Composition status is polled.** `GET /compositions/{id}` returns the row plus a
  computed `stale` flag. The contract is in
  [Compose job contract](compose-job-contract.md).
- **Version ids are strings.** Native revision ids are timestamps and are not DB ids.
  Check-in attaches a message to the current native revision (D-21).
- **Content writes go through the Node API.** `PUT /assets/{id}/content` exists for API
  clients and tests; the primary editor is the Text app (D-20). Both paths must trigger
  native versioning/save events.
- **Settings use profile records and write-only keys.** `GET /ai/settings` returns
  profiles with `apiKeyMasked`; profile create/update accepts a write-only `apiKey`;
  `DELETE /ai/profiles/{id}` removes the profile and its key.
- **AI chat links observability.** `ChatResponse.traceId` is the Langfuse trace id, and
  `proposedActions` carries the pending proposals that require confirmation (D-10).
- **Chapter trees can lazy-load.** Without `structureId`, optional `parentId` and `depth`
  return canonical levels for the T2 tree. With `structureId`, the full included tree is
  returned using effective parents, so structure views stay unambiguous.
- **Book storage is visible in the API.** `Book.path` exposes the visible folder path
  for the settings/storage display without exposing server filesystem paths.
- **Prompts are book-scoped in the draft.** The final split between local prompt records
  and Langfuse prompt names is a T6 decision; the draft exposes both `text` and optional
  `langfuseName`/`langfuseVersion`.

## Not in the draft

- Admin/global AI defaults (T6).
- DOCX composition and the separate compose server (deferred, D-06).
- Sharing, groups, and public links (out of scope).
- Streaming chat transport; the draft assumes a normal JSON response for the MVP.
- Search, trash management, and image-specific endpoints beyond file references.


## T1 implementation status

Implemented under `/ocs/v2.php/apps/booktailor/api/v1`:

- Books: list, create, get, rename, delete.
- Chapters: canonical tree, structure tree, lazy-load parameters, create, get, rename,
  delete.
- Structures: list, show, entries (read-only in T1).
- Assets: list, create, upload, get, rename, delete.
- Tags: list, create, rename, delete, list/replace asset tags.
- Versions: list, check-in, restore.

Implementation adjustments to the draft:

- `Book` now includes `description` and read-only `path`.
- `Asset` includes its `tags` in list/show responses.
- `Chapter` has no canonical `position`; sibling order belongs to `structure_chapters`.
- Check-in is `200` and attaches the message to the current native revision (D-21).

Deferred to later phases: chapter movement and custom structure writes (T3), raw content and
version downloads (T4), compositions (T5), AI/prompts (T6), settings/images (T7).
`openapi.json` remains the generated skeleton until every controller method has the full
extractor docblock; `openapi.draft.yaml` is the reviewed T1 contract.

## T4 implementation status

- Raw content endpoints are now implemented: `GET|PUT /assets/{id}/content` and
  `GET /assets/{id}/versions/{revisionId}/content`.
- `GET /capabilities` reports `editor` (`text` or `null`) and `versions` (boolean) for
  runtime degradation.
- `Asset` now includes read-only `path` (the visible file path) so clients can build the
  Files/Text editor URL.
- Check-in remains `POST /assets/{id}/versions` and labels the current native revision
  (D-21); the UI exposes it from the history dialog.
- Tags include a palette `color` (10 allowed hex values). `POST /books/{id}/tags` accepts
  an optional color and `PATCH /tags/{id}` accepts name and/or color.

## T6 implementation status

- AI settings are implemented under `/ai/settings` with multiple per-user LLM profiles
  at `/ai/profiles` (D-62). The earlier single `/settings` provider shape is superseded.
- Profiles support DeepSeek and SiliconFlow with encrypted write-only keys, a masked
  last-four read value, one active profile, and a connection-test endpoint (D-63).
- Chat is `POST /ai/chat` with normal JSON, a bounded JSON tool loop, read tools, and
  proposal-only writes (D-65/D-66). Responses include `proposedActions`, `warnings`,
  `usage`, and an optional Langfuse `traceId`.
- Proposals are stored in `booktailor_ai_action_log` only while pending and applied
  through `/ai-actions/{id}/confirm`; `/reject` discards them. Rows are deleted after
  a successful or failed decision, so no action history is retained (D-67, superseded
  by D-77). Abandoned proposals older than 24 hours are pruned on the next proposal.
- Predefined prompts live in the server prompt catalog; saved prompts are book-scoped
  and CRUD-capable at `/books/{bookId}/prompts` (D-69).
- Langfuse is app-level configuration and best-effort; content logging is per-user
  opt-in (D-68).
- `openapi.draft.yaml` was updated for the profile endpoints, AI settings schemas, prompt
  keys, and chat warnings/usage.

## Review outcome

Reviewed against the MVP spec and domain schema on 2026-10-24 and accepted as the T1
implementation baseline. The review added `Book.path`, `Settings.defaultEditor`,
`GET /ai-actions/{actionId}`, canonical lazy-load parameters, and a `409` for deleting a
running composition. The following choices are frozen for T1:

- `/api/v1` base path and the OCS envelope.
- 404 for non-owned resources.
- Structure entries as a replace-all operation.
- Composition creation as `202`, with polled status, stale detection, and explicit retry.
- Version ids as native revision strings.
- BYOK keys write-only and masked on read.

Deliberately deferred:

- **T5:** composition rule precedence, output naming, image folder layout, stale
  threshold, and double-submit protection for composition creation.
- **T6:** prompt scope/storage, provider list, streaming chat, and PII redaction.
- **T7:** image-specific endpoints; the draft treats images as chapter files for now.

The generated `openapi.json` remains the skeleton index endpoint until routes are
implemented; `composer run openapi` will replace it from controller docblocks. Keep the
draft in sync when a decision changes a route or schema.
