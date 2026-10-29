# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.5.0] - 2026-10-29

### Added

- Chapter image manager: upload, preview, replace, copy Markdown snippets, and reference-safe deletion for PNG, JPEG, GIF, and WebP images.
- Settings sections for storage location, upload limits, effective editor/composition defaults, and app/Nextcloud versions.
- Playwright end-to-end coverage for authoring, images, and Settings.
- Translation scaffolding with `@nextcloud/l10n` and translated T7/Core UI strings.

### Changed

- Images now validate MIME types and size limits before writing, reject SVG and path separators, and keep referenced files protected.
- Accessibility improvements across the image gallery, Settings, dialogs, and navigation.

## [1.4.0] - 2026-10-26

### Added

- AI assistant with per-user DeepSeek/SiliconFlow profiles, encrypted write-only BYOK keys, in-app AI settings, saved prompts, a foldable chat panel, read tools, proposal confirmation, and an audit log.
- Best-effort Langfuse prompt management and OTLP tracing with opt-in content logging.
- First release
