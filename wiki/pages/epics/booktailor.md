---
created: 2026-10-22
type: epic
status: proposed
summary: BookTailor epic — organize Markdown books, chapters, assets, structures, compositions, and AI-assisted authoring in Nextcloud.
---

# BookTailor — Epic

BookTailor is a Nextcloud app for managing the structure of a book, storing its
chapters, working on chapter content with AI assistance, and composing selected
content into a final Markdown document. It targets learning-material creation, where
chapters collect source files, intermediate notes, summaries, and terminology lists.

The app is currently a Nextcloud template skeleton. This epic covers the path from the
skeleton to a usable authoring and composition product.

## Goal

Give a user one place to:

1. Create and organize multiple **Books**.
2. Build a hierarchy of **Chapters**.
3. Store Markdown **Assets** in each chapter, with tags and version history.
4. Define **Structures** that order and partially include chapters for different outputs.
5. Generate a **Composition** from a structure using tag-based selection rules.
6. Use an AI assistant that can read the book and propose changes for confirmation.
7. Manage images embedded in Markdown assets.

## Domain glossary

| Term | Meaning |
|---|---|
| **Book** | Top-level workspace owned by a user. Contains chapters, structures, tags, and compositions. |
| **Chapter** | A node in the book hierarchy. Has a name, a parent (except roots), and assets. |
| **Asset** | A Markdown file inside a chapter. May be a raw source, intermediate note, summary, or final text. |
| **Tag** | A label on an asset (e.g. `Raw`, `Summary`, `Terms`). Tags drive filtering and composition rules. |
| **Structure** | A named, ordered selection of chapters within a book. A book always has a default structure containing all chapters. |
| **Composition** | A generated final document produced from a structure and a set of tag-based selection rules. |
| **Default structure** | The structure automatically created with a book; it includes all chapters. |

## Design principles

- **Nextcloud-native storage.** Assets live in a visible dedicated folder in the user's
  Files; metadata and ordering live in the app database.
- **Book is the workspace; Composition is the output.** The two concepts are never
  conflated in code or UI.
- **Markdown first.** Markdown is the source format and the first composition output.
  DOCX is added later through the separate compose service.
- **Hierarchy is global; order is per structure.** A chapter's parent is fixed in the
  book; each structure decides which chapters appear and in what sibling order.
- **Composition is rule-driven.** Tag priorities select which assets feed each chapter
  of a composition.
- **AI proposes; the user decides.** AI tools may read everything but structural or
  content changes require explicit confirmation.
- **Versioned content.** Use Nextcloud's native file versions and store check-in
  messages as sidecar metadata.
- **Core first.** Build books, chapters, assets, and editing before structures,
  composition, and AI.

## Scope

### In scope

- Multiple books per user, without sharing or group permissions in the MVP.
- Hierarchical chapters with the movement rules from the draft.
- Markdown asset create/upload/delete/edit, tags, and versions.
- Structures with independent chapter inclusion, sibling reordering, and numbering.
- Async Markdown composition driven by tag rules.
- AI assistant with Langfuse prompt management/observability and bring-your-own-key.
- Image storage and management for Markdown assets.
- A complete Nextcloud navigation and settings experience.

### Out of scope for the MVP

- Sharing books with other users or groups.
- Real-time collaborative editing beyond what Nextcloud/Office already provides.
- DOCX composition in the first release.
- Arbitrary drag-and-drop reparenting of chapters.
- A full document editor built by BookTailor; use Euro Office / Nextcloud editors.
- Mobile-specific layouts.

## Delivery

The work is split into eight phases. Each phase has a ticket with spec, plan, and
acceptance criteria.

| Phase | Ticket | Focus |
|---|---|---|
| 0 | [T0 — Foundation](../tickets/phase-0-foundation.md) | Decisions, schema, spikes, contracts |
| 1 | [T1 — Core domain](../tickets/phase-1-core-domain.md) | Books, chapters, assets, storage, versions |
| 2 | [T2 — Main UI](../tickets/phase-2-main-ui.md) | Chapter tree and asset UI |
| 3 | [T3 — Structures](../tickets/phase-3-structures-numbering.md) | Inclusion, ordering, numbering |
| 4 | [T4 — Editing & tags](../tickets/phase-4-editing-versions-tags.md) | Editor, history, tags |
| 5 | [T5 — Composition](../tickets/phase-5-composition.md) | Tag rules, Markdown merge, outputs |
| 6 | [T6 — AI assistant](../tickets/phase-6-ai-assistant.md) | Langfuse, BYOK, tools, chat |
| 7 | [T7 — Release](../tickets/phase-7-images-settings-release.md) | Images, settings, polish, packaging |

See the [MVP specification](../specs/booktailor-mvp.md) and the
[delivery plan](../plans/booktailor-mvp.md) for the full requirements and sequencing.

## Success criteria

- A user can create a book, build a chapter tree, upload Markdown assets, and manage
  tags and versions without leaving BookTailor.
- A user can define multiple structures and see correct chapter numbering for each.
- A user can compose a Markdown book from tag rules and download the result.
- AI can summarize raw assets into a new asset, but the user confirms the write.
- Images referenced by Markdown assets survive composition.
- The app passes its PHP and frontend test suites and can be packaged for Nextcloud.

## Open items

These are recorded in the [spec](../specs/booktailor-mvp.md) and resolved in the phase
where they matter:

- Exact visible-folder layout and rename/move behavior.
- Whether the draft's “default chapter” means the default structure.
- Tag scope (per book or global) and composition rule precedence/fallback.
- Euro Office integration details.
- Langfuse hosting, supported providers, and key storage.
- Composition output naming/location.
- Image storage layout.
- Sharing and permissions beyond the MVP.
