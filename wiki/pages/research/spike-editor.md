---
created: 2026-10-23
type: research
status: implemented
summary: Editor spike — Nextcloud Text launch path, save observation, and check-in message flow on Nextcloud 35.
---

# Spike — Markdown editor integration (T0)

Part of [T0 — Foundation](../tickets/phase-0-foundation.md). Decisions: D-20 (Text via
Files URL, observe `NodeWrittenEvent`) and D-21 (check-in labels the current revision).
Schema context: [domain-schema](domain-schema.md). Version behavior:
[versions spike](spike-versions.md).

## Environment

- `nextcloud-docker-dev`, server 35.0.0.3 (master), test user `user1`.
- Markdown editor: **Nextcloud Text** `9.0.1` (stable35), installed from source into
  `workspace/server/apps-extra/text` and enabled. The server master tree does not bundle
  Text and the app store has no 35-beta build; stable35 supports `min-version=35`.
- Collabora/Euro Office are **not** involved: BookTailor assets are Markdown and Text is
  the handler for them.

## Launch path

The Files app opens a file through its `openfile` parameter:

```
/index.php/apps/files/?dir=/BookTailor/<book>/<chapter>&openfile=<fileId>
```

Verified: the page loads `text-files.mjs` and `text-viewer.mjs`, and the user can create
and edit Markdown files in this flow. BookTailor only needs the file ID and path to build
this URL; no Text-specific API is required.

## Save path and observation

Text's save service (`DocumentService::autosave`) writes through the file context's
`save()`, which uses the native file API. That is the same write path exercised by a DAV
`PUT`, and the spike confirmed:

- a save creates a native version of the **previous** content;
- the current revision (file mtime) gets a version-author metadata entry;
- `BeforeNodeWrittenEvent` / `NodeWrittenEvent` fire, as shown by the native
  `files_versions` listeners creating versions and author metadata.

BookTailor should register a `NodeWrittenEvent` listener, match the node's file ID to an
`assets.file_id`, and treat it as a save. Do **not** call `createVersion()` on every
write: Text autosaves, and native versioning already snapshots saves.

## Current revision is a pseudo-version

`getVersionsForFile()` returns the current revision alongside historical versions. It
has no `.v<revision>` file, so `read()`/`rollback()` on it fail. The native Files UI
filters it out; BookTailor must:

- identify it by `revisionId === file.getMTime()`;
- never download, restore, or label it as a historical version;
- still show it as the **Current** row in BookTailor's history, with its metadata.

## Check-in message flow (validated)

1. User edits the asset in Text and saves (autosave).
2. BookTailor labels the **current** revision:
   `backend->setMetadataValue($file, $file->getMTime(), 'booktailor_message', $text)`.
3. On the next save, native versioning snapshots that revision to
   `files_versions/...v<revision>` and the message stays on it. It is now a readable
   historical version with the message.
4. BookTailor's history shows the current revision (with its message) plus older
   versions newest-first, excluding the duplicate current entry from the version list.

Test result: a message set on the current revision (`1792773077`) was visible on that
revision and, after the next save, that revision became historical with the message and
the expected content; the new current revision had no message.

This matches the semantics: the message describes the content that was saved, not the
snapshot it replaced. Autosaves do not need a message prompt.

## Findings

1. **Text is enough.** No Euro Office/Collabora dependency for Markdown.
2. **Launch is a URL, not an API.** `openfile=<fileId>` in the Files app.
3. **Saves are observable** through the standard node write events; no Text-specific
   hooks.
4. **Check-in on current revision works** and survives into history on the next save.
5. **The current pseudo-version must be filtered** for download/restore.
6. **Text autosaves.** BookTailor must debounce/skip duplicate event handling and never
   create app-level versions per write.
7. **Runtime dependency checks:** verify `text` and `files_versions` are enabled. If
   either is missing, open the asset read-only or hide the check-in/history UI.

## Reproduction sketch

```php
// label the current revision after an edit
$rev = $file->getMTime();
$backend = $vm->getBackendForStorage($file->getStorage());
if ($backend instanceof IMetadataVersionBackend) {
    $backend->setMetadataValue($file, $rev, 'booktailor_message', 'my check-in');
}

// history: include current, exclude it from the restorable list
foreach ($vm->getVersionsForFile($user, $file) as $v) {
    $isCurrent = (int)$v->getRevisionId() === $file->getMTime();
    // current: show metadata only; historical: read/restore
}
```

## Fallbacks

- Text disabled: open the file in read-only preview and disable editing.
- `files_versions` disabled or no metadata backend: hide check-in/history; editing still
  works.
- Native version unavailable for a storage backend: show the current content only.

## Open checks (later phases)

- Exercise a save through the Text HTTP API (the write path is the same as DAV).
- Runtime verification on Nextcloud 31 and 34.
- Concurrent editors and autosave races.
- Very large files and autosave limits.
