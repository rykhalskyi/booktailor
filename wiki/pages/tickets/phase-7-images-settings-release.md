---
created: 2026-10-22
type: ticket
status: implemented
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

Implementation plan proposed on 2026-10-29. T6 already delivered the in-app Settings
shell; T7 extends it instead of creating a second settings surface. Images follow
D-16: one `images/` folder per chapter, referenced by relative `images/<name>` paths.
No new image table: images are addressed by Nextcloud file id plus their chapter.

### Decisions (approved 2026-10-29)

- **D-70** — Keep the chapter-level `images/` layout and store no image metadata in the
  app database; resolve images through the chapter folder and Nextcloud file ids.
- **D-71** — Upload accepts raster images only (`png`, `jpeg`, `gif`, `webp`) after MIME
  sniffing, rejects SVG in the MVP, and enforces the lower of Nextcloud's upload limit
  and a 10 MiB app cap.
- **D-72** — Replace writes new bytes under the existing filename and delete is refused
  while any asset still references the image (409 with referencing asset names). The
  app never silently rewrites Markdown.
- **D-73** — Settings gains storage/limits/effective-defaults information, but no new
  preference storage in T7. Effective defaults are displayed as current behavior, not
  fake editable settings.
- **D-74** — Use Playwright for E2E, kept out of `npm run check`; use an env-gated stub
  provider so the real proposal-confirmation path runs.
- **D-75** — Translate frontend strings and user-facing controller/validation messages;
  leave developer logs and internal exception text in English for the MVP.
- **D-76** — Release T7 as `1.5.0`.

The Markdown editor's attachment flow stays authoritative for inserting image Markdown;
BookTailor provides upload, gallery, and a copyable snippet. Confirm the Text app's image
destination during implementation and document the result.

### Stage 1 — Image backend

Add `lib/Service/ImageService.php` plus small `StorageService` helpers:

- `getChapterImagesFolder(Chapter, bool $create)`, `writeImage`, `deleteImage`, and
  `uniqueImageName`; all paths stay inside the chapter folder.
- `list(userId, chapterId)` returns `{fileId, name, mime, size, path, url, references}`
  using one batched scan of the chapter's Markdown files for references.
- `upload(userId, chapterId, upload)`: validate upload error, size, detected MIME, and
  extension; sanitize the basename; refuse collisions.
- `replace(userId, chapterId, fileId, upload)`: resolve the file inside the chapter's
  `images/` folder, validate, then overwrite under the same name.
- `delete(userId, chapterId, fileId)`: resolve ownership and references; return a
  `ValidationException`/`ConflictException` with the referencing asset names when used.
- `references(userId, chapterId, name)` reuses the Markdown image patterns from
  `MarkdownComposer` (plain Markdown and `<img src>`), so add/remove rules match
  composition.

Add `lib/Controller/ImageController.php` (OCS `/api/v1`, `ApiRoute` attributes,
`NoAdminRequired`):

| Method | Route | Purpose |
|---|---|---|
| GET | `/chapters/{chapterId}/images` | Gallery data plus reference counts |
| POST | `/chapters/{chapterId}/images` | Multipart upload, field `file` |
| PUT | `/chapters/{chapterId}/images/{fileId}` | Multipart replacement, same name |
| DELETE | `/chapters/{chapterId}/images/{fileId}` | Delete when unreferenced |

Extend `SerializerService` with an `image` shape and include a preview URL based on the
file id. Register the service in `lib/AppInfo/Application.php` only if constructor
injection needs it. Update `openapi.draft.yaml`.

Tests:

- Upload valid PNG/JPEG and reject SVG, fake MIME, oversize, empty, and traversal names.
- List/replace/delete are owner-scoped; another user's chapter/file id returns 404.
- Replace keeps the name and existing references; mismatched extension is rejected.
- Delete returns 409 while referenced and succeeds after the reference is removed.
- Composition still copies a chapter image and the rewritten output renders.

### Stage 2 — Image UI and editor flow

- Add `src/components/ChapterImages.vue` (gallery/grid with thumbnails, empty state,
  upload button, replace/delete actions, copy-snippet action) and integrate it into the
  chapter asset manager below the asset rows.
- Add `src/components/ImageUploadDialog.vue` (or a hidden file input plus confirmation)
  with type/size help, upload progress/busy state, and errors from the API.
- Add `src/utils/images.ts` for preview URLs, Markdown snippet formatting
  (`![alt](images/<name>)`), human-readable sizes, and extension checks.
- Extend `src/types.ts`, `lib/Service/SerializerService.php`, and `src/api/client.ts`
  with `ChapterImage` and `api.images` methods. Use the existing `FormData` request
  path; do not add a new HTTP dependency.
- Delete uses the existing confirmation dialog and shows the referencing assets when the
  API refuses. Replace uses the same dialog pattern and refreshes the gallery.
- Verify the Nextcloud Text image-attachment flow in the dev instance. If Text writes
  into the same chapter `images/` folder, keep its insertion path and document it; if
  it does not, make the copy-snippet flow the primary path and note the limitation in
  `build-deploy.md` and the ticket outcome.
- Tests: gallery rendering, empty/error states, copy snippet, upload/replace/delete
  calls, referenced-delete warning, busy-state blocking.

### Stage 3 — Settings, storage, and effective defaults

- Extend `SettingsScreen.vue` with sections that do not duplicate the AI profile UI:
  - **Storage** — visible `BookTailor/` root, selected-book relative path when
    available, a Files link, upload-size limit, and the chapter image format.
  - **Defaults** — effective editor (Nextcloud Text), composition output
    (Markdown in `compositions/`), and AI transport (JSON, no streaming); clearly
    labelled as current behavior until preferences exist.
  - **About** — app version, Nextcloud version, and release-channel information.
- Add `lib/Service/SettingsService.php` and `lib/Controller/SettingsController.php` for
  `GET /api/v1/settings` returning `storage`, `limits`, `defaults`, and `app`; do not
  return paths outside the user folder, secrets, or provider URLs.
- Reuse `IConfig`/Nextcloud upload limits and `IURLGenerator`; add a Files-folder link
  built from the user folder path.
- Tests: service values, no secret leakage, API shape, Settings rendering with and
  without a selected book, and disabled/empty states.

### Stage 4 — Internationalization

- Add `@nextcloud/l10n` as a direct dependency and replace user-facing literals in
  `src/` with `t('booktailor', ...)` / `n(...)`; use placeholders for interpolation.
- Inject `IL10N` into controllers or a small helper for user-facing error messages
  returned by the API. Keep exception/log text stable for tests and support.
- Add `l10n/` scaffolding and document extraction/import in `build-deploy.md` using the
  Nextcloud translation tool. Do not hand-maintain translated language files.
- Add a small test or lint guard that fails on obvious new untranslated UI literals in
  the critical components; include RTL-safe layout checks in the accessibility pass.
- Scope: navigation, tree, asset manager, image gallery, dialogs, settings, chat, and
  API validation messages. Technical logs and provider errors stay English.

### Stage 5 — Accessibility

- Audit and fix the critical screens: navigation, chapter tree, asset list, image
  gallery, dialogs, Settings, and AI chat.
- Keyboard: tree arrow keys already exist; add gallery keyboard order, dialog focus
  trapping/return, Escape handling, and visible focus on all custom buttons.
- Semantics: labels for icon-only actions, `aria-label`/`aria-describedby` for upload and
  search fields, live regions for loading/errors, and heading order on each screen.
- Contrast: check tag chips, muted text, disabled states, and destructive actions
  against both Nextcloud themes.
- Add component tests for roles/labels/focus return; add an axe check to the Playwright
  critical-flow specs (or a small `@axe-core/playwright` helper).

### Stage 6 — End-to-end tests

- Add `@playwright/test`, `playwright.config.ts`, `npm run test:e2e`, and
  `npm run test:e2e:install`. Exclude `tests/e2e/**` from Vitest.
- Default to `BOOKTAILOR_BASE_URL=http://nextcloud.local` and
  `BOOKTAILOR_ADMIN_USER/PASSWORD=admin/admin`, with env overrides. Create a unique test
  user/book per run and clean up through the OCS API or `occ`; never depend on existing
  user data.
- Specs:
  - create book → root chapter → subchapter → asset → open editor;
  - switch structure, verify numbering and included chapters;
  - edit, check in a version, restore a previous version;
  - compose by tag, wait for success, download and verify the merged Markdown;
  - upload an image, insert the snippet, verify preview/render, replace, and delete
    (including the referenced-delete warning);
  - AI proposal confirmation. Prefer an env-gated stub provider so the real
    `/ai-actions/{id}/confirm` path runs; if that seam is rejected, intercept
    `/ai/chat` in Playwright and rely on `AiProposalTest` for the backend apply.
- Add `tests/e2e/README.md` with dev-container prerequisites and a troubleshooting note
  for the `--force` NC35 app enable. Keep E2E out of the default `npm run check`.

### Stage 7 — Performance and security review

Performance:

- Seed a fixture with ~200 chapters and ~1000 assets, then measure tree load, chapter
  switch, asset listing, search/filter, image gallery, and composition.
- Budgets: initial tree under 2 s on the dev stack, chapter/asset switch under 500 ms,
  and composition of 100 assets under 30 s. Record actual numbers in the outcome.
- Review serializers and list endpoints for N+1 queries; keep asset/composition/action
  pagination and add limits where a new list can grow (images per chapter).
- Verify AI context building still batches reads and respects the character cap with a
  large tree.

Security:

- Ownership tests for every new chapter/image/settings route, including guessed file
  ids and cross-user chapter ids.
- Uploads: MIME sniffing, extension/name sanitization, path traversal, double
  extensions, SVG/XSS, decompression/size abuse, and File API temp-file cleanup.
- Confirm CSRF/request-token behavior on the new upload routes and that OCS responses do
  not include decrypted AI keys or server paths.
- Run a repository secret scan and confirm `deploy/nextcloud-langfuse.env` and other
  local secret files remain untracked; never log provider keys or request bodies.
- Review `npm audit`/Composer advisories and record accepted/unaccepted findings.

### Stage 8 — Release and documentation

- Bump `appinfo/info.xml` to `1.5.0` and align `package.json`/lock metadata; update
  `CHANGELOG.md` with images, Settings, i18n, accessibility, E2E, and release notes.
- Verify install/upgrade on clean Nextcloud 31 and 34 instances (plus the NC35 dev stack
  with `--force`), including migrations from 1.4.0, app enable/disable, and the default
  navigation entry.
- Add a packaging step or script that builds `js/`/`css/`, installs production
  dependencies, and excludes `node_modules`, `vendor-bin/*/vendor`, tests, caches, and
  local env files. Document the tarball/App Store flow in `build-deploy.md`.
- Regenerate `openapi.json`/update `openapi.draft.yaml`; review the route diff.
- Update this ticket's Outcome, append decisions, log the work, and regenerate
  `wiki/index.md`.

### Verification

1. In the dev container: `composer run lint`, `composer run cs:check`,
   `composer run test:unit`, `composer run psalm`; apply migrations with `occ upgrade`.
2. `npm run lint`, `npm run stylelint`, `npm run typecheck`, `npm test`,
   `npm run build`.
3. Start the dev Nextcloud and run `npm run test:e2e` for the critical flows.
4. Manual: upload/replace/delete an image; verify editor insertion and composition;
   open Settings and confirm no secrets or absolute paths leak; keyboard-only pass;
   screen-reader spot check; large-tree timing; clean-install smoke on NC 31/34.
5. Secret scan and dependency audit; confirm no generated build files or secrets are
   left untracked/staged.

### Risks and mitigations

| Risk | Mitigation |
|---|---|
| Text editor stores images outside the chapter folder | Verify in Stage 2; keep snippet flow as fallback and document the behavior |
| Image deletion breaks existing Markdown | Refuse referenced deletion and return the referencing assets |
| Upload bypass via MIME/extension tricks | Sniff content, allowlist raster types, sanitize names, reject SVG, cap size |
| i18n touches nearly every component | Slice by screen, land translation wrappers with each feature, lint critical files |
| E2E needs a live Nextcloud and AI provider | Env-driven Playwright config, unique test data, guarded stub provider, keep E2E out of `npm run check` |
| Settings grows into preference storage without behavior | Show effective defaults only; add persistence only with a real consumer |
| Performance regressions on large books | Fixture-based budgets, query review, pagination for new lists |

### Resolved questions

- Referenced image deletion is blocked (D-72); SVG is rejected (D-71).
- Settings shows read-only effective defaults; no new preferences (D-73).
- E2E uses Playwright and an env-gated AI stub provider (D-74); i18n scope is UI plus
  user-facing API messages (D-75).
- Release version is `1.5.0` (D-76).

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

Implemented on 2026-10-29 and packaged as `1.5.0`.

### Shipped

- **Chapter images**: `ImageService`, `ImageController`, gallery UI in the chapter asset
  manager, upload, in-place replace, reference-safe delete, copyable Markdown snippets,
  previews, reference counts, MIME sniffing, SVG rejection, size caps, and owner scoping.
- **Settings**: the existing screen now shows storage location and Files link, upload and
  image limits, effective editor/composition/AI defaults, and app/Nextcloud versions
  from `GET /settings`.
- **API contract**: image routes and `/settings` are documented in `openapi.draft.yaml`.
- **i18n**: `@nextcloud/l10n` is a direct dependency; T7 surfaces plus App navigation,
  asset list, and dialogs use `t('booktailor', ...)`; image service/controller messages
  use `IL10N`; `l10n/` scaffolding and extraction notes are in
  [build-deploy](../build-deploy.md).
- **Accessibility**: labelled image actions, dialog-based delete, keyboard-safe default
  controls, headings, and live loading/error states on the new screens.
- **E2E**: Playwright covers authoring/navigation, image upload/replace/delete, blocked
  referenced-image deletion, and the Settings screen.
- **Release**: version bumped to `1.5.0` in `appinfo/info.xml` and `package.json`;
  `CHANGELOG.md` updated; `scripts/package.sh` builds
  `build/booktailor-1.5.0.tar.gz` with runtime files only.
- **Test harness**: `composer run test:unit` now uses a 1G memory limit because the full
  integration suite exceeds PHP's default 512M.

### Verification

- PHP: 97 tests, 356 assertions (`composer run test:unit`).
- Frontend: 100 Vitest tests; `npm run lint`, `npm run stylelint`,
  `npm run typecheck`, and `npm run build` pass.
- E2E: 4 Playwright tests pass against the NC35 dev stack.
- Psalm: no errors. PHP CS Fixer: no fixes required.
- Composer audit: no advisories. `npm audit --omit=dev`: 0 vulnerabilities.
- Package: `build/booktailor-1.5.0.tar.gz` (1.4 MB) contains only runtime files.

### Deviations

- E2E currently covers authoring, images, and Settings. Structure switching, version
  restore, composition/download, and AI proposal confirmation remain covered by the
  integration/unit suites rather than Playwright.
- The full i18n sweep is not complete: legacy AI/chat, structure, composition, tag, and
  version components still contain English literals. New T7 surfaces and core navigation
  are translated and the catalog scaffolding is in place.
- The large-tree performance budgets were not measured with a seeded
  ~200-chapter/1000-asset fixture. Existing pagination and batched reads were reviewed,
  but no timing numbers are recorded.
- Clean installs on Nextcloud 31 and 34 were not available in this environment; all
  verification ran on the NC35 dev stack with `occ upgrade` and `--force` enable.
- The Nextcloud Text attachment destination was not re-verified in this environment. The
  copy-snippet flow is the documented insertion path in the UI.
- `npm audit` still reports 10 dev-only advisories (7 low, 3 moderate); production
  dependencies are clean.
