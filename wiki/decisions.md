# Decisions

Append-only decision record. The "why" lives here; the full change history lives in git.
- [2026-10-22] D-01 — Book is the workspace and Composition is the generated final document — because the draft overloaded book for both concepts
- [2026-10-22] D-02 — Support multiple books per user with no sharing in the MVP — because workspaces must be independent before permissions are added
- [2026-10-22] D-03 — Store assets in a visible dedicated Nextcloud folder — because users should be able to browse and use Nextcloud editors directly
- [2026-10-22] D-04 — Use Asset as the umbrella term for Markdown files inside a chapter — because they can be sources, intermediates, or results
- [2026-10-22] D-05 — Use native Nextcloud versions plus sidecar check-in metadata — because native history is reliable and messages are app-level data
- [2026-10-22] D-06 — Ship Markdown composition first and add DOCX later — because the compose service is not yet designed
- [2026-10-22] D-07 — Implement only the draft chapter movement rules in the MVP — because arbitrary reparenting adds complexity without proven need
- [2026-10-22] D-08 — Allow structures to include chapters independently — because outputs may need a child without its parent
- [2026-10-22] D-09 — Select composition assets with tag-based rules — because tags already classify sources, summaries, and terms
- [2026-10-22] D-10 — AI tools propose changes and the user confirms them — because structural writes must remain intentional and auditable
- [2026-10-22] D-11 — Define the compose contract now and build the separate service later — because the MVP can compose Markdown locally
- [2026-10-22] D-12 — Build the core domain before structures, composition, and AI — because every later feature depends on books, chapters, and assets
- [2026-10-23] D-13 — Visible folders mirror the canonical chapter tree and DB stores Nextcloud file IDs — because users browse in Files while stable file IDs survive renames and moves
- [2026-10-23] D-14 — The draft's default chapter is interpreted as the default structure; there is no separate default chapter — because the draft conflated two concepts
- [2026-10-23] D-15 — Tags are scoped per book in the MVP — because vocabularies should not leak between independent workspaces
- [2026-10-23] D-16 — Images live in a chapter-level images/ subfolder and are referenced by relative paths — because assets in a chapter share images and composition resolves them uniformly
- [2026-10-23] D-17 — Structure entries store inclusion and per-structure sibling position; effective parents are derived at read time — because storing derived parentage would drift from the canonical tree
- [2026-10-23] D-18 — Deleting chapters and assets moves files to native Nextcloud trash while app metadata cascades — because native trash is the expected recovery path
