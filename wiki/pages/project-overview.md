---
created: 2026-10-22
type: overview
status: in-progress
summary: Nextcloud app that turns a set of Markdown files into one organized, AI-assisted document.
---

# Book Tailor — Project Overview

Book Tailor is a Nextcloud app for organizing a set of Markdown files into a single
document. It is intended to automate the learning-material creation flow with
AI-driven organization.

The repository is currently based on the official Nextcloud app template: the backend
bootstrap, routes, API skeleton, frontend shell, and tooling are in place, while the
actual Markdown assembly logic is not implemented yet.

## Tech stack

| Layer | Technology |
|---|---|
| Runtime | Nextcloud 31–34, PHP `^8.1` |
| Backend | Nextcloud App Framework (`OCP\AppFramework`), OCS API, attributes-based routing |
| Frontend | Vue 3 (`<script setup lang="ts">`), TypeScript, `@nextcloud/vue` |
| Build | Vite via `@nextcloud/vite-config` |
| PHP tooling | PHPUnit, Psalm, php-cs-fixer, Rector, `nextcloud/ocp` stubs |
| Frontend tooling | ESLint (`@nextcloud/eslint-config`), Stylelint, TypeScript, Vitest + `@vue/test-utils` |
| AI observability | Langfuse 4 via OTLP (D-22), best-effort and opt-in |

## Architecture

- **App bootstrap** — `lib/AppInfo/Application.php` registers the app under
  `booktailor` and implements `IBootstrap` (currently empty hooks).
- **Page controller** — `lib/Controller/PageController.php` serves the frontend at
  route `/` (`booktailor.page.index`) and renders `templates/index.php`.
- **API controllers** — `lib/Controller/ApiController.php` is the legacy hello-world
  route. The domain controllers (`BookController`, `ChapterController`,
  `StructureController`, `AssetController`, `TagController`, `VersionController`,
  `AiSettingsController`, `AiChatController`, `AiActionController`, and
  `AiPromptController`) expose the owner-scoped OCS API under
  `/ocs/v2.php/apps/booktailor/api/v1` (D-26).
  `openapi.draft.yaml` is the reviewed contract; `openapi.json` is still the generated
  skeleton until full route docblocks are added.
- **Frontend shell** — `src/main.ts` mounts `src/App.vue` into `#booktailor`.
  `App.vue` renders the book navigation, structure selector, recursive chapter tree,
  asset panel, settings view, optional AI chat panel, and dialogs. State lives in
  `src/stores/useLibrary.ts`; AI chat session state lives in `src/composables/useAiChat.ts`;
  API calls go through `src/api/client.ts`.
- **Asset wiring** — `templates/index.php` loads the Vite-built `main` script and style
  through `Util::addScript` / `Util::addStyle`, and provides the `#booktailor` mount
  point.
- **Navigation** — `appinfo/info.xml` registers the app in Nextcloud navigation with the
  `booktailor.page.index` route.

## Folder structure

```
appinfo/info.xml          App metadata, version, Nextcloud compatibility, navigation
lib/AppInfo/Application.php  App registration and bootstrap
lib/Controller/           Page + owner-scoped OCS v1 controllers
lib/Db/                   Query-builder mappers
lib/Entity/               Book, chapter, structure, asset, and tag entities
lib/Exception/            API exceptions mapped to OCS status codes
lib/Migration/            Database schema migrations
lib/Service/              Storage, domain, tag, version, and AI services
lib/Service/Ai/           Providers, context, chat, proposals, Langfuse, prompts
templates/index.php       Server-rendered page shell / asset loading
src/main.ts               Vue entry point
src/App.vue               App shell, book navigation, and main layout
src/api/                  OCS API client
src/components/           Chapter tree, asset list, toolbar, settings, AI chat, and dialogs
src/components/settings/  AI profile and saved-prompt cards/dialogs
src/stores/               Module-level library state composable
src/utils/                Tree helpers
tests/unit/               PHPUnit tests (controller-level)
tests/integration/        Real DB/file lifecycle tests with temporary users
tests/js/                 Vitest frontend tests
tests/bootstrap.php       PHPUnit bootstrap, loads only needed apps
build/stubs/              Psalm stubs for files_versions classes
openapi.json              Generated skeleton for the legacy OCS route
openapi.draft.yaml        Reviewed MVP API contract
vite.config.ts            Vite/Nextcloud build configuration
vitest.config.ts          Vitest configuration
composer.json             PHP dependencies + lint/test/static-analysis scripts
package.json              Frontend dependencies + build/lint scripts
```

## Key concepts

- **Frontpage route vs OCS API route** — UI routes use `#[FrontpageRoute]`; JSON APIs
  use `#[ApiRoute]` on an `OCSController` and carry `#[NoAdminRequired]` where users
  (not only admins) may call them.
- **Server-rendered shell** — Nextcloud renders `templates/index.php`; Vite output is
  injected there, so the Vue app is not a standalone SPA.
- **App ID is a constant** — `Application::APP_ID` (`booktailor`) is reused for asset
  loading, navigation, and test bootstrap.

## Related documentation

- [BookTailor epic](epics/booktailor.md) — product vision, glossary, and scope.
- [MVP specification](specs/booktailor-mvp.md) — requirements, domain model, and API.
- [Delivery plan](plans/booktailor-mvp.md) — phased implementation and tickets.
