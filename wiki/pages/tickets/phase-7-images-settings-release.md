---
created: 2026-10-22
type: ticket
status: proposed
summary: Finish image management, settings, accessibility, end-to-end tests, and release packaging.
---

# T7 — Images, Settings, and Release

Phase 7 of 8 in the [BookTailor plan](../plans/booktailor-mvp.md). Requirements are in
the [MVP spec](../specs/booktailor-mvp.md); context is in the
[epic](../epics/booktailor.md).

## Spec

Complete the remaining user-facing features and prepare a release candidate. This phase
covers image management, settings, quality passes, and packaging.

Scope:

- Image add/replace/remove per asset.
- Markdown image insertion via the Nextcloud editor.
- Settings screen for AI keys and defaults.
- i18n and accessibility.
- End-to-end tests.
- Performance and security review.
- Version bump, changelog, and packaging.

Out of scope:

- DOCX output.
- Sharing and group permissions.
- Mobile-specific layouts.

## Plan

1. Implement the image manager:
   - list images for an asset;
   - add, replace, remove;
   - validate type/size;
   - keep Markdown references valid.
2. Confirm the Markdown editor image-insertion flow and add help text.
3. Build the Settings screen:
   - AI provider/key entry;
   - default editor/composition preferences;
   - storage location display;
   - no secrets echoed back.
4. Run the i18n pass:
   - extract all strings;
   - add translation scaffolding.
5. Run the accessibility pass:
   - keyboard navigation;
   - focus management;
   - labels and ARIA;
   - contrast.
6. Add end-to-end tests for:
   - book → chapter → asset → edit;
   - structure switch and numbering;
   - version restore;
   - composition and download;
   - AI proposal confirmation;
   - image add and render.
7. Run performance checks on a large tree and many assets; fix N+1 and pagination
   issues.
8. Run the security review: ownership, uploads, paths, AI keys, CSRF.
9. Update version, `CHANGELOG.md`, and packaging instructions.
10. Update the wiki, mark tickets implemented, and regenerate the index.

Acceptance criteria:

- Images can be added, replaced, and removed, and survive composition.
- Settings persist and never expose keys.
- The app is keyboard-navigable and screen-reader friendly on critical screens.
- End-to-end tests pass.
- Performance is acceptable for a realistic book size.
- The app packages and installs on a clean Nextcloud 31–34 instance.
- The wiki reflects the shipped state.

Dependencies:

- T1–T6.

## Outcome

Pending. Fill in release version, test results, packaging notes, and deviations when
done.
