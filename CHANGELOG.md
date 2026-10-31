# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Asset composition selection: mark or unmark assets for composition directly in the chapter tree, with a per-chapter table for names, tags, update times, and inclusion state.
- Composition preview that lists the assets a structure will compose before the composition is created.

### Changed

- Compositions require a name and are listed by that name, falling back to the structure name for older rows.
- Creating a composition is blocked while the preview is loading or when the selected structure has no included assets.
- Composition output now sorts included assets by name instead of relying on storage order.

### Removed

- AI action history: proposals are now pending-only and are discarded after approval, rejection, or a failed confirmation. The Settings history section and the audit-log endpoints are gone, and existing history rows are purged. Abandoned proposals older than 24 hours are pruned when the user creates a new proposal.
- Tag-based composition rules and the `booktailor_composition_rules` table. Assets are now selected explicitly; existing assets start excluded and must be marked for composition.

## [1.5.0] - 2026-10-29

### Added

- Chapter image manager: upload, preview, replace, copy Markdown snippets, and reference-safe deletion for PNG, JPEG, GIF, and WebP images.
- Settings sections for storage location, upload limits, effective editor/composition defaults, and app/Nextcloud versions.
- Playwright end-to-end coverage for authoring, images, and Settings.
- Translation foundation with `@nextcloud/l10n`; the image gallery, Settings, navigation, structure manager, AI chat, and shared dialogs are translated, with the remaining screens to follow.

### Changed

- Images now validate MIME types and size limits before writing, reject SVG and path separators, and keep referenced files protected.
- Accessibility improvements across the image gallery, Settings, dialogs, and navigation.

## [1.4.0] - 2026-10-26

### Added

- AI assistant with per-user DeepSeek/SiliconFlow profiles, encrypted write-only BYOK keys, in-app AI settings, saved prompts, a foldable chat panel, read tools, proposal confirmation, and an audit log.
- Best-effort Langfuse prompt management and OTLP tracing with opt-in content logging.
- First release
