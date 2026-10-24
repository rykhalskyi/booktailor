---
created: 2026-10-24
type: research
status: implemented
summary: Background-job spike — queued execution, cron drain, status persistence, and the failure gap on Nextcloud 35.
---

# Spike — Background jobs for composition (T0)

Part of [T0 — Foundation](../tickets/phase-0-foundation.md). The composition contract is in
[Compose job contract](compose-job-contract.md); the schema is in
[Domain schema and storage layout](domain-schema.md).

## Environment

- `nextcloud-docker-dev`, server 35.0.0.3 (beta 2), PHP 8.5.9, test user `user1`.
- App bind-mounted at `/var/www/html/apps-extra/booktailor`.
- CLI entry points: `occ background-job:execute`, `php cron.php`, and
  `occ background-job:list`.
- A temporary `SpikeComposeJob extends QueuedJob` read two Markdown files by file id
  from the owner's folder, concatenated them, wrote `composition.md`, and persisted a
  JSON status record in app config under `spike_job_<runId>`.

## Verified operations

| Operation | Result |
|---|---|
| Enqueue with `IJobList::add()` | Job appears in `background-job:list` with JSON arguments. |
| Job id type | String on Nextcloud 33+ (`144139797601955840` in the spike). |
| Find job by class and argument | `getJobsIterator(SpikeComposeJob::class, null, 0)` works. |
| Run manually | `occ background-job:execute <id> --force-execute` runs the job. |
| Run through cron | `php cron.php` drains the queued job without a user session. |
| Run without a session | `IRootFolder::getUserFolder($userId)` resolves the owner's files. |
| Read files by id | `$home->getById($fileId)` returns the current file node. |
| Write output | `putContent()` creates the composition output in the visible folder. |
| Status before run | App config showed `"status":"queued"`. |
| Status after success | `"status":"succeeded"` with job id, processed count, output file id, path, bytes, and timestamps. |
| Queued job after success | Removed from the job list. |
| Failure handling | A missing file id produced `"status":"failed"` with the error; the job was removed and the exception was logged. |
| Argument visibility | Arguments are stored as JSON and printed by `background-job:list`; they must not contain secrets or file contents. |

## Findings

1. **`QueuedJob` deletes itself before `run()`.** A failure, fatal error, or OOM cannot
   be retried by the job list. The app must persist status and offer an explicit retry
   (D-24).
2. **Exceptions are swallowed by `Job::start()`.** Nextcloud logs them, but the command
   still reports “Job executed!”. The worker must catch `Throwable`, record a
   user-actionable error, and rethrow only for logging.
3. **Jobs have no user session.** Pass the owner id in the argument and use
   `IRootFolder::getUserFolder($userId)`. Do not rely on `IUserSession`.
4. **Arguments are plain JSON in the job table.** Keep them minimal — the composition id
   only in the real job (D-25).
5. **Cron and manual execution share the same path.** `cron.php` and
   `background-job:execute` both call `IJob::start()`, so a manual run is a faithful
   check for CI and debugging.
6. **Status needs app state.** The job list only exposes last-run and duration metadata;
   it does not carry domain status. The `compositions` row is the source of truth.
7. **Hard crashes leave no failure record.** Because the job disappears before `run()`,
   a fatal error can leave `status=running` forever. The contract treats old running rows
   as stale and allows a user-initiated retry.

## Reproduction

```bash
# enqueue (temporary script with base.php and \OC_App::loadApp('booktailor'))
docker exec -u www-data master-nextcloud-1 php /tmp/enqueue_spike_job.php

# manual run
docker exec -u www-data master-nextcloud-1 php occ background-job:execute <jobId> --force-execute

# cron run for a second job
docker exec -u www-data master-nextcloud-1 php /var/www/html/cron.php

# inspect status
docker exec -u www-data master-nextcloud-1 php occ config:app:get booktailor spike_job_<runId>
```

The temporary job class and scripts were removed after the spike; the contract carries
the production shape.

## Implications for the app

- Add `OCA\BookTailor\BackgroundJob\ComposeJob extends QueuedJob`.
- Create the composition row and rules in one transaction, then enqueue `{compositionId}`.
- Claim work with a conditional `queued → running` update; exit if another worker won.
- Persist `running`, `succeeded`, and `failed` transitions with timestamps and an
  actionable error.
- Use the owner's user folder; never trust paths from the client.
- Do not put file contents, API keys, or user messages in job arguments.
- Retry is explicit (`POST /compositions/{id}/retry`), not automatic in the MVP.

## Fallback

If cron is not configured, Nextcloud still runs background jobs on page loads (AJAX or
webcron mode). CI and debugging can use `background-job:execute`. If no worker runs at
all, compositions remain `queued` and the UI must show that state rather than pretending
the job completed.

## Open checks (later phases)

- Parallel cron workers and the conditional claim under real concurrency.
- Long compositions versus PHP and cron time limits.
- Runtime behavior on Nextcloud 31 and 34.
- Stale-running threshold and whether a heartbeat column is needed in T5.
