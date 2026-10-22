---
created: 2026-10-22
type: ticket
status: proposed
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

1. Decide and document:
   - Langfuse hosting model;
   - supported providers;
   - key storage mechanism;
   - provenance metadata for generated assets.
2. Implement provider abstraction and BYOK:
   - write-only key entry;
   - protected storage;
   - redaction in logs and responses.
3. Integrate Langfuse:
   - prompt retrieval/management;
   - traces and spans for each call;
   - failure fallback when Langfuse is unavailable.
4. Build the right-side foldable chat panel:
   - message history;
   - streaming or progress state;
   - cancel/retry;
   - context indicator.
5. Implement prompt sources:
   - predefined prompts;
   - user prompts;
   - free-form chat.
6. Implement context assembly:
   - selected book;
   - structure and chapter tree;
   - relevant assets and tags;
   - size limits and truncation policy.
7. Implement tools:
   - read tools for books, chapters, assets, structures, tags;
   - propose tools for create/edit/delete;
   - server-side validation of proposals;
   - confirmation endpoint that applies a proposal.
8. Add the confirmation UI:
   - diff/preview of the proposed change;
   - approve/reject;
   - audit entry for both.
9. Implement initial workflows:
   - summarize raw assets into a new asset;
   - extract terms/lists;
   - answer questions about the book.
10. Add tests for:
    - key redaction;
    - proposal validation;
    - confirmation gating;
    - tool permissions;
    - Langfuse failure handling.
11. Update the wiki, decisions, and index.

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

Pending. Fill in provider choice, Langfuse setup, tool surface, confirmation flow, and
deviations when done.
