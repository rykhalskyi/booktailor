---
created: 2026-10-23
type: research
status: implemented
summary: File API spike — visible folders, stable IDs, collision and trash behavior on Nextcloud 35.
---

# Spike — Nextcloud file operations (T0)

Part of [T0 — Foundation](../tickets/phase-0-foundation.md). The storage rules being
validated are in [domain-schema](domain-schema.md).

## Environment

- `nextcloud-docker-dev`, server 35.0.0.3 (master), PHP container
  `master-nextcloud-1`, MySQL, local primary storage.
- Test user `user1`, no app code involved; operations used `OCP\Files` through
  `IRootFolder::getUserFolder()`.
- The bootstrap used `require '/var/www/html/lib/base.php'` and an explicit user
  session (`IUserSession::setUser()`), because trash-bin hooks are user-scoped.

## Verified operations

| Operation | Result |
|---|---|
| `getUserFolder()` | Returns `/user1/files`; the app root maps to the user's Files. |
| `newFolder('a/b/c')` | Creates nested folders in one call. |
| Create folder / file | Works; file has size, etag, and `text/markdown` MIME type. |
| Write / read content | Round-trips exactly. |
| Rename folder | `move()` renames the folder. |
| Move folder subtree | `move()` moves the whole subtree. |
| Move file | `move()` moves the file. |
| File / folder IDs | Stable across file moves and folder renames/moves, including children. |
| Duplicate `newFile()` | Returns the **existing** node; no exception, content unchanged. |
| Duplicate `newFolder()` | Returns the **existing** folder; no exception. |
| Path traversal (`../escape.md`) | Rejected with `OCP\Files\NotPermittedException`. |
| `delete()` on a fresh file | Removes it from Files and creates a `files_trashbin` entry. |
| `delete()` on a fresh folder | Moves the subtree to trash. |
| `delete()` on a stale node | Silent no-op (no exception). |
| DAV visibility | `PROPFIND /remote.php/dav/files/user1/...` sees the created tree. |
| Trash cleanup | `occ trashbin:cleanup user1` empties the trash. |

## Findings

1. **Create is not collision-safe.** `newFile()` and `newFolder()` are effectively
   idempotent: an existing path is returned instead of throwing. The app must check
   `nodeExists()` and de-duplicate before creating; otherwise an upload would
   `putContent()` over an existing asset.
2. **File IDs are stable.** Storing IDs for books, chapters, assets, and composition
   outputs is valid across renames and moves. Paths remain display data.
3. **Node objects are not move-safe.** After moving an ancestor, previously obtained
   `Node` instances still point at the old path; `delete()` on them silently does
   nothing. Re-fetch by path/file ID after any move.
4. **Native trash works through the API** when a user session is active. Folder
   deletion trashes the whole subtree. Background jobs have no user session, so they
   need user context (or an explicit trash/cleanup policy) for deletions.
5. **Path traversal is blocked** by the filesystem layer with
   `NotPermittedException`, in addition to the app-level filename sanitization.
6. **DAV sees app-created files immediately**; no `files:scan` is needed.

## Reproduction

Bootstrap inside the container:

```php
require '/var/www/html/lib/base.php';

$server = \OC::$server;
$server->get(IUserSession::class)->setUser(
    $server->get(IUserManager::class)->get('user1')
);
$home = $server->get(IRootFolder::class)->getUserFolder('user1');
```

Run:

```bash
docker cp spike.php master-nextcloud-1:/tmp/spike.php
docker exec -u www-data -w /var/www/html master-nextcloud-1 php /tmp/spike.php
```

Verify through DAV:

```bash
curl -u user1:user1 -X PROPFIND -H 'Depth: 4' \
  http://nextcloud.local/remote.php/dav/files/user1/<folder>/
```

Clean up:

```bash
# delete the spike folder through the API, then:
docker exec -u www-data master-nextcloud-1 php occ trashbin:cleanup user1
```

## Implications for the app

- Put all filesystem work behind one `StorageService`; it pre-checks names,
  de-duplicates, and returns fresh nodes.
- Never cache `Node` instances across rename/move operations; key by file ID and
  re-fetch.
- Record the file ID at creation and use it for reconciliation.
- Run delete operations with the owner's user context, including composition cleanup.
- Keep app-level sanitization and duplicate resolution even though the filesystem
  rejects traversal.

## Fallback

If a later Nextcloud version or storage backend does not preserve file IDs, fall back
to storing paths and reconciling by path plus content hash. If native trash cannot be
triggered from background jobs, keep the deleted metadata row until a user-scoped
cleanup runs instead of deleting files permanently from the job.

## Open checks (later phases)

- Same behavior on Nextcloud 31 and 34.
- External/S3 primary storage.
- Concurrent creates and collision races.
- Trash retention and quota behavior.
