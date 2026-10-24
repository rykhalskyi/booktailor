---
created: 2026-10-23
type: research
status: implemented
summary: Native version spike — listing, restoring, per-version metadata, and same-second collisions on Nextcloud 35.
---

# Spike — Native versions and check-in messages (T0)

Part of [T0 — Foundation](../tickets/phase-0-foundation.md). Decisions: D-05 (native
versions) and [D-19](../../decisions.md) (native metadata instead of a sidecar table).
The schema is in [domain-schema](domain-schema.md).

## Environment

- `nextcloud-docker-dev`, server 35.0.0.3 (master), test user `user1`, local storage.
- Version API: `OCA\Files_Versions\Versions\IVersionManager` resolved from the server
  container; backend `LegacyVersionsBackend`.
- The `files_versions` app is shipped and enabled (`2.0.0-dev.0`).
- A user session was set explicitly; version author metadata and permission checks
  depend on it.

## Verified operations

| Operation | Result |
|---|---|
| Resolve `IVersionManager` | Available through the server container. |
| `createVersion($user, $file)` | Snapshots the current file content with revision = file mtime. |
| `getVersionsForFile($user, $file)` | Returns `IVersion[]`; order is **oldest → newest**. |
| Revision identifier | `getRevisionId()` equals the revision timestamp (unix seconds). |
| Version author | Native metadata `author` is populated automatically from the session. |
| Set custom metadata | `setMetadataValue()` stores arbitrary key/value pairs (JSON). |
| Read custom metadata | `IVersion` implements `IMetadataVersion`; values round-trip. |
| Metadata after move | Survives a file move (file ID is unchanged). |
| Rollback | Restores content, sets the file mtime to the restored revision. |
| Pre-rollback content | Snapshot exists afterwards (created if it was not already a version). |
| Versions after rollback | Available for download/restore; check-in metadata intact. |
| DAV visibility | The visible Markdown file is untouched; versions stay in `files_versions`. |

## Findings

1. **No app table is needed.** Check-in messages and author live in native version
   metadata. `asset_version_metadata` was removed from the schema (D-19).
2. **Use the backend path for 31–34.** On Nextcloud 31–34 `IVersionManager` has no
   `setMetadataValue()`. Resolve the backend and guard for the interface:

   ```php
   $backend = $versions->getBackendForStorage($file->getStorage());
   if ($backend instanceof IMetadataVersionBackend) {
       $backend->setMetadataValue($file, $revision, 'booktailor_message', $text);
   }
   ```

   The same code works on 35. `IMetadataVersion` / `IMetadataVersionBackend` exist in
   31–35 and `Version` implements `IMetadataVersion`.
3. **History must be sorted explicitly.** `getVersionsForFile()` returns oldest first;
   the UI/history endpoint should sort by timestamp descending.
4. **Same-second saves collapse.** Revision IDs have one-second resolution. Two
   `createVersion()` calls in the same second write the same revision file; the second
   content wins and the first content is lost (`rapid_version_count=1` in the spike).
   The app cannot create a distinct revision for the same file and second.
5. **Rollback rewinds the mtime.** Restoring sets the file's mtime to the restored
   revision, so revision IDs after a restore must not assume monotonic wall time.
6. **Versions expire natively.** `files_versions` retention removes old versions;
   their check-in messages disappear with them. There is nothing to clean in app
   tables.
7. **Dependency is on a shipped app.** `files_versions` is enabled by default but can
   be disabled. The app should detect that and disable version history/check-in
   messages rather than fail.
8. **Materialize the current revision before writing metadata.** T1 integration found
   that `setMetadataValue()` looks up the native `files_versions` row; call
   `getVersionsForFile()` first so the current revision row exists, then write the
   check-in metadata.

## Reproduction

```php
require '/var/www/html/lib/base.php';

$server = \OC::$server;
$user = $server->get(IUserManager::class)->get('user1');
$server->get(IUserSession::class)->setUser($user);
$home = $server->get(IRootFolder::class)->getUserFolder('user1');

$file = $home->newFile('spike.md');
$file->putContent("one\n");
sleep(1);
$vm = $server->get(IVersionManager::class);
$vm->createVersion($user, $file);          // snapshot "one"
sleep(1);
$file->putContent("two\n");

// list, write metadata, restore
$versions = $vm->getVersionsForFile($user, $file);
$backend = $vm->getBackendForStorage($file->getStorage());
$backend->setMetadataValue($file, (int)$versions[0]->getRevisionId(), 'booktailor_message', 'first draft');
$vm->rollback($versions[0]);               // content becomes "one"
```

Run the same way as the file spike: `docker cp`, then
`docker exec -u www-data -w /var/www/html master-nextcloud-1 php /tmp/spike.php`.
Clean up through the API and empty the trash with `occ trashbin:cleanup user1`.

## Implications for the app

- Add a `VersionService` that wraps listing, metadata writes, and rollback.
- Store message/author metadata under `booktailor_message` and `booktailor_author`.
- Sort history descending; show native `author` when present.
- Debounce the save/check-in action: explain that saves within the same second share
  one version, or block a second explicit check-in until the next second.
- Treat missing `files_versions` or a backend without metadata support as a degraded
  mode: no check-in messages, and version history unavailable.

## Fallback

If `files_versions` is disabled or the backend cannot store metadata, hide the
check-in/history UI. Do not silently keep messages in memory. A sidecar table remains a
recoverable option if a future supported server drops metadata support, but it should
not be built now (D-19).

## Open checks (later phases)

- Runtime verification on Nextcloud 31 and 34 (source-level API check done).
- Behavior with external/S3 primary storage.
- Retention/expiry interaction with history UI.
- Concurrent saves and lock handling.
