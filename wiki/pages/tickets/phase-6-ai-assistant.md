---
created: 2026-10-22
type: ticket
status: implemented
summary: Add a Langfuse-backed, BYOK AI assistant that reads the book and proposes changes for user confirmation.
---

# T6 — AI Assistant

Phase 6 of 8 in the [BookTailor plan](../plans/booktailor-mvp.md). Requirements are in
the [MVP spec](../specs/booktailor-mvp.md); context is in the
[epic](../epics/booktailor.md).

## Spec

Deliver the AI assistant that makes BookTailor more than a file organizer. The assistant
uses Langfuse for prompt management and observability, supports bring-your-own-key
(BYOK), understands the full book context, and can propose chapter/asset changes that
the user must confirm.

Scope:

- Langfuse integration.
- BYOK settings and protected key storage.
- Provider abstraction.
- Right-side chat panel.
- Predefined and user prompts.
- Book/chapter/asset/structure context.
- Read and propose-only write tools.
- Confirmation UI and audit log.
- Summary and term-extraction workflows.

Out of scope:

- Autonomous writes without confirmation.
- Fine-tuning or model hosting.
- Cross-book context.

## Plan

Implementation plan proposed on 2026-10-26. The user-facing requirement for this phase
is explicit: AI provider setup is a first-class in-app **Settings** view, following the
`byebyemoneylist-ns` `Settings.vue` pattern (profile cards, add/edit dialog, active
profile radio), not a hidden `occ`-only form. Langfuse remains app-level configuration.

### Proposed decisions (record with `wiki.mjs decision` when implementation starts)

- Support multiple per-user LLM profiles with exactly one active profile; encrypt each
  key with `ICrypto`, never return it, and expose only a last-four mask (extends D-23).
- Support DeepSeek and SiliconFlow initially through one OpenAI-compatible provider
  adapter; show unsupported providers as disabled options. No app-level fallback key.
- Put AI settings in the in-app Settings screen and make it reachable without a selected
  book. T7 later extends this screen with non-AI settings; it does not replace it.
- Use normal JSON chat responses with a progress state, not SSE streaming. The client
  can abort/retry; the server keeps the MVP transport simple.
- Use a bounded JSON tool protocol for reads and proposals instead of provider-specific
  native function calling, so DeepSeek and SiliconFlow behave identically.
- Persist every proposal in the existing `booktailor_ai_action_log`; confirmation
  revalidates and applies it through the domain services.
- Keep predefined prompts in a server-side catalog with local fallbacks; store user
  prompts per book in a new table.
- Langfuse tracing is best-effort. Content logging is opt-in per user and redacted when
  disabled; prompt names, model, usage, and trace ids still flow.

### Architecture

```text
Settings / BYOK
  LlmProfile entity + mapper
  LlmProfileService (encrypt, mask, ownership, active-profile invariant)
  AiSettingsController
        |
        v
Chat backend
  AiProviderInterface -> OpenAiCompatibleProvider (DeepSeek, SiliconFlow)
  AiProviderRegistry
  AiContextService (book, structure, chapter, asset, tag context)
  AiToolDispatcher (read allowlist; proposal allowlist)
  AiChatService (prompt -> provider -> bounded tool loop -> reply + proposals)
  LangfuseService + PromptCatalog (best-effort prompt fetch and OTLP trace)
        |
        v
Proposal / audit
  AiAction entity + mapper (existing table)
  AiProposalService (validate, persist, confirm/reject)
  Existing Book/Chapter/Asset/Structure/Tag services (apply only on confirm)
```

### API surface

All routes are OCS under `/api/v1`, authenticated, and owner-scoped. Non-owned
resources return 404. The T0 draft currently describes one `/settings` provider; this
plan supersedes that shape with multiple profiles and keeps `/ai/settings` for aggregate
settings. Update `openapi.draft.yaml` accordingly.

| Method | Route | Purpose |
|---|---|---|
| GET | `/ai/settings` | Profiles, active profile, Langfuse status, content-logging flag |
| PATCH | `/ai/settings` | Update content-logging preference |
| GET | `/ai/profiles` | List profiles with masked keys |
| POST | `/ai/profiles` | Create profile with write-only `apiKey` |
| PATCH | `/ai/profiles/{id}` | Update profile; omit `apiKey` to keep it |
| DELETE | `/ai/profiles/{id}` | Delete profile |
| POST | `/ai/profiles/{id}/activate` | Activate one profile, deactivate the rest |
| POST | `/ai/profiles/{id}/test` | Optional key/model connectivity check |
| POST | `/ai/chat` | Send message; return reply, trace id, proposed actions |
| GET | `/books/{bookId}/ai-actions` | Audit list, optional status filter |
| GET | `/ai-actions/{id}` | One audit row |
| POST | `/ai-actions/{id}/confirm` | Apply a proposal after revalidation |
| POST | `/ai-actions/{id}/reject` | Reject a proposal |
| GET/POST | `/books/{bookId}/prompts` | List/create user prompts |
| PATCH/DELETE | `/prompts/{id}` | Update/delete a user prompt |

### Data model and migrations

Add `Version1003Date20261027`:

- `booktailor_llm_profiles`: `id` (BIGINT autoincrement), `user_id`, `name`, `provider`,
  `api_key` (encrypted TEXT), `model` nullable, `connect_timeout` (default 30),
  `read_timeout` (default 60), `max_tokens` (default 2048), `is_active` (default false),
  `created_at`, `updated_at`; index on `user_id`.
- `booktailor_ai_prompts`: `id`, `book_id` (FK cascade), `user_id`, `name`, `text`,
  `created_at`, `updated_at`; index on `book_id`.
- Extend `booktailor_ai_action_log` with `decided_at` (nullable DATETIME) and `result`
  (nullable TEXT) so approve/reject are auditable with an outcome or validation error.

No new table for chat messages: chat history is session-local in the panel for the MVP.
No app-level AI fallback key. Content-logging consent is stored as a per-user app config
value.

### Stage 1 — Settings and BYOK foundation

Backend:

- `lib/Entity/LlmProfile.php`, `lib/Db/LlmProfileMapper.php` with
  `findAllByUser`, `findByIdAndUser`, `findActiveByUser`, and transactional `setActive`.
- `lib/Service/LlmProfileService.php`: trim/validate, encrypt with `ICrypto`, mask
  (`••••last4`), decrypt only server-side, single-active invariant, ownership checks.
- `lib/Controller/AiSettingsController.php` for `/ai/settings` and profile CRUD.
- Reuse `AbstractApiController` for envelope/error handling; never log keys or request
  bodies containing them.

Frontend:

- `src/components/SettingsScreen.vue` with the card/dialog structure from the example.
- `src/components/settings/AiProfileCard.vue` and `AiProfileDialog.vue`.
- `src/constants/aiProviders.ts` (DeepSeek and SiliconFlow supported; others disabled).
- Extend `src/types.ts`, `src/api/client.ts`, and `src/icons.ts` (key, robot, cog, send,
  close, plus existing icons; no new icon dependency).
- Add `Settings` to the app navigation and a `settingsOpen` view flag in
  `useLibrary.ts`; settings must not require a selected book and must close other views.
- Include an empty state that links to "Add LLM profile" when AI is disabled.

Tests:

- Service test: create/update/delete/activate, ownership, last-four masking, key never
  serialized, encrypted-at-rest round trip.
- Frontend tests: list, add/edit dialog behavior, active selection, error/empty states.
- API smoke: create profile, list it, verify no plaintext key appears in the response.

### Stage 2 — Provider abstraction and Langfuse

- `lib/Service/Ai/AiProviderInterface.php` with `complete(AiCredentials, messages,
  options): AiCompletion`; `AiProviderRegistry` maps provider id to implementation.
- `lib/Service/Ai/OpenAiCompatibleProvider.php`: DeepSeek and SiliconFlow endpoints,
  model defaults, bearer auth, connect/read timeouts, `max_tokens`, JSON response mode,
  redacted errors. Decrypt the key inside the call path only; discard it immediately.
- `lib/Service/Ai/LangfuseService.php`: fetch prompt by name/label with a short timeout;
  emit one OTLP generation per call using D-22; best-effort try/catch; include prompt
  name/version in the trace; return the trace id when available.
- `lib/Service/Ai/PromptCatalog.php`: local fallbacks `booktailor-chat`,
  `booktailor-summarize`, `booktailor-extract-terms`, `booktailor-ask-book`.
- `GET /ai/settings` reports `langfuse: {configured, url}` but never the project secret.
  Document app config in [build-deploy](../build-deploy.md):
  `langfuse_url`, `langfuse_public_key`, `langfuse_secret_key`; no secrets in the repo.
- Content-logging off means input/output attributes are omitted from OTLP; prompt/model/
  usage/trace remain. Add a unit test for this redaction.

### Stage 3 — Chat, context, and read tools

- `lib/Service/Ai/AiContextService.php`: load book, selected structure, chapter tree,
  chapter assets, and tags; enforce a total character cap and per-asset truncation with
  explicit truncation markers. Reuse existing serializers/mappers, not raw SQL.
- `lib/Service/Ai/AiChatService.php`:
  - require an active profile; otherwise return a clear "configure AI in Settings" error;
  - fetch the prompt from Langfuse with local fallback;
  - send the current turn with a compact JSON protocol:
    `{reply, toolCalls, proposals}`;
  - execute only allowlisted reads (`get_book`, `list_chapters`, `get_chapter`,
    `list_assets`, `get_asset_content`, `list_tags`, `get_structure`) for at most two
    rounds, with ownership checks on every read;
  - collect proposals without executing them;
  - tolerate invalid/non-JSON responses by returning the raw text as the reply with no
    proposals;
  - return `{message, proposedActions, traceId?}` and a usage summary when available.
- `lib/Controller/AiChatController.php` for `POST /ai/chat`.
- `src/composables/useAiChat.ts`: per-book session history, loading/progress, abort,
  retry, last error, and proposal state.
- `src/components/AiChatPanel.vue`: foldable right-side panel with a toggle, context
  indicator (book / structure / chapter / selected assets), message list, free-form
  input, quick prompts, and disabled state when no active profile exists.
- Wire the panel into the library screen without disturbing the existing tree/asset
  layout; keep it usable on narrow screens.

Tests:

- Context truncation and size limits; no cross-book leakage.
- Tool allowlist: unknown tool rejected, non-owned asset/chapter read rejected.
- Fake provider: normal reply, invalid JSON fallback, provider HTTP failure, Langfuse
  failure still returns a reply.
- Frontend: send, loading, abort, retry, context indicator, disabled state.

### Stage 4 — Proposals, confirmation, audit

- `lib/Entity/AiAction.php`, `lib/Db/AiActionMapper.php`, and
  `lib/Service/Ai/AiProposalService.php`.
- Allowlist proposal actions and validate payloads strictly:
  - `create_asset`, `update_asset`, `delete_asset`;
  - `create_chapter`, `rename_chapter`, `delete_chapter`;
  - `create_structure`, `rename_structure`, `delete_structure`.
- Validation rules: target IDs belong to the claimed book/owner; names/filenames follow
  existing limits; assets stay in their chapter; the default structure cannot be deleted;
  content is size-capped; unknown fields are rejected. Reuse domain services for the
  final write, never a direct mapper insert/update from the AI layer.
- Persist each proposal as `status=proposed` with action, payload, book id, user id, and
  creation time. Return the audit row in the chat response.
- `POST /ai-actions/{id}/confirm`: only `proposed` may be confirmed; revalidate the full
  payload, apply through the owning service, set `applied` + `decided_at` + result, or
  `failed` + error. A second confirm returns 409.
- `POST /ai-actions/{id}/reject`: set `rejected` + `decided_at`; no writes.
- `GET /books/{bookId}/ai-actions` and `GET /ai-actions/{id}` expose the audit log.
- Frontend `AiProposalCard.vue`: preview create/update/delete, a small line diff for
  updates, approve/reject buttons, busy/error states, and refresh of the tree/assets/
  tags after an applied action. Add an "AI action history" section to Settings.
- Tests: chat never mutates before confirmation, confirm applies exactly the validated
  action, reject leaves data untouched, ownership/default-structure protections, double
  confirmation conflict, failed validation is audited.

### Stage 5 — Workflows, user prompts, and docs

- Quick workflows in the chat panel:
  - **Summarize**: selected chapter/assets -> proposed `create_asset` summary;
  - **Extract terms/lists**: selected assets -> proposed `create_asset` term list;
  - **Ask about the book**: tree/tags plus read tools; no write proposal required.
- User prompts: backend CRUD and a "Save prompt" flow in the panel; prompt picker merges
  user prompts with the predefined catalog.
- Provenance: record the source action and trace id in the AI action audit row and
  prepend a short `<!-- Generated by BookTailor AI; prompt=...; trace=... -->` comment to
  proposed created content. No asset schema change in T6.
- Update the ticket outcome, decisions, `openapi.draft.yaml`, build/deploy docs, and log;
  regenerate `wiki/index.md`.

### Verification

1. Apply migration in the dev container and smoke the profiles API.
2. `composer run lint`, `composer run cs:check`, `composer run test:unit`,
   `composer run psalm`; run `composer run openapi` if route docblocks are complete and
   review the diff.
3. `npm run lint`, `npm run stylelint`, `npm run typecheck`, `npm test`, `npm run build`.
4. Manual: add a DeepSeek/SiliconFlow profile, verify the key is masked; send a chat
   prompt; confirm a summary proposal; verify nothing is written before approval;
   approve and check the tree/asset; reject another proposal; confirm the Langfuse trace
   and redaction toggle; test with Langfuse unavailable and with no active profile.

### Risks and mitigations

| Risk | Mitigation |
|---|---|
| Provider-specific response formats | One JSON protocol and tolerant parser; plain-text fallback |
| Model emits unsafe/invalid proposals | Strict allowlist/schema validation; apply only through services after confirmation |
| Prompt key leaks through logs/errors | Never log payloads with keys; redact provider errors; mask on read |
| Context exceeds model window | Hard character cap, per-asset truncation, explicit markers, read-on-demand tools |
| Langfuse outage blocks chat | Best-effort calls with short timeout; local prompt fallback; tracing never fatal |
| Settings becomes a T7 blocker | Deliver the AI settings slice now behind the existing app shell; T7 extends it |

### Open questions for approval

- Providers: DeepSeek + SiliconFlow only for the first cut, or also OpenAI?
  **Recommended: DeepSeek + SiliconFlow**, matching the existing working adapter.
- Transport: synchronous JSON with progress/abort, or SSE streaming?
  **Recommended: synchronous JSON** for the MVP.
- Langfuse: app-level credentials via `occ` with a status-only settings display, or an
  admin settings form for the Langfuse project keys? **Recommended: app-level config**
  and show status in Settings.
- Content logging: default off (opt-in) or on (opt-out)? **Recommended: off by default**.

Acceptance criteria:

- AI can answer questions about the selected book.
- AI can propose a summary asset; nothing is written until the user confirms.
- Proposed structural changes are validated server-side and cannot bypass ownership.
- Langfuse traces are visible for AI calls.
- AI keys are never returned to the client or written to logs.
- Tests pass.

Dependencies:

- T1–T4 for domain APIs, tags, and editing flows.

## Outcome

Implemented on 2026-10-26.

### Shipped

- **Settings UI** with the profile-card pattern requested from `byebyemoneylist-ns`:
  add/edit dialogs, provider select, masked keys, active-profile radio, advanced
  timeouts/tokens, saved prompts, content-logging opt-in, Langfuse status, and AI action
  history.
- **BYOK backend**: `booktailor_llm_profiles`, encrypted `ICrypto` keys, masked reads,
  single-active invariant, ownership checks, and DeepSeek/SiliconFlow validation.
- **Provider layer**: `AiProviderInterface`, `OpenAiCompatibleProvider`, registry,
  key-redacting errors, connect/read timeouts, and JSON response mode.
- **Langfuse**: app-level config (`langfuse_url`, `langfuse_public_key`,
  `langfuse_secret_key`, `langfuse_prompt_label`) with environment-variable fallback
  (`BOOKTAILOR_LANGFUSE_*` or standard `LANGFUSE_*` names), local prompt fallbacks, OTLP generation traces, opt-in
  content logging, and trace ids in chat responses.
- **Chat**: `POST /ai/chat` with context assembly, book/chapter/structure/tag reads,
  allowlisted read tools, bounded tool rounds, predefined prompts, saved prompts, abort,
  retry, and a foldable right-side panel.
- **Proposals and audit**: create/update/delete for assets, chapters, and structures;
  server-side validation; `booktailor_ai_action_log` rows with `decidedAt`/`result`;
  confirm/reject endpoints; and a confirmation UI with create previews and update diffs.
- **Saved prompts**: book-scoped CRUD at `/books/{bookId}/prompts`, plus a prompt picker
  in the chat panel.

### Deviations

- Chat uses synchronous JSON with a progress/abort state rather than SSE streaming,
  per D-65.
- Langfuse credentials remain app-level (`occ config:app:set`); Settings shows status
  only, per D-68.
- No app-level fallback key; AI is disabled until a user activates a profile.
- Saved prompts use the server prompt catalog for predefined entries instead of
  duplicating predefined prompts into every book.
- The live DeepSeek/SiliconFlow call and live Langfuse trace were not exercised in this
  environment because no provider or Langfuse credentials are available. Provider
  request/error handling, Langfuse fallback, proposal gating, ownership, and tool
  permissions are covered by tests.

### Verification

- `composer run lint`, `composer run cs:check`, `composer run psalm`: pass.
- `composer run test:unit`: 71 tests pass (including integration coverage for profiles,
  proposals, tool permissions, and provider/Langfuse fallback).
- `npm run check` (ESLint, Stylelint, vue-tsc, Vitest): pass; 94 JS tests.
- `npm run build`: pass.
- Manual HTTP smoke: migrations applied; profile create/list/delete returned masks only;
  prompt CRUD, AI action list, and chat-without-profile (422) worked.
