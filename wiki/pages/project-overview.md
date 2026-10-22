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
| Frontend tooling | ESLint (`@nextcloud/eslint-config`), Stylelint, TypeScript |

## Architecture

- **App bootstrap** — `lib/AppInfo/Application.php` registers the app under
  `booktailor` and implements `IBootstrap` (currently empty hooks).
- **Page controller** — `lib/Controller/PageController.php` serves the frontend at
  route `/` (`booktailor.page.index`) and renders `templates/index.php`.
- **API controller** — `lib/Controller/ApiController.php` is an `OCSController`
  exposing `GET /api` (`NoAdminRequired`), currently returning a hello-world payload.
  The API is described by `openapi.json`.
- **Frontend shell** — `src/main.ts` mounts `src/App.vue` into `#booktailor`.
  `App.vue` renders a Nextcloud `NcContent` / `NcAppContent` layout.
- **Asset wiring** — `templates/index.php` loads the Vite-built `main` script and style
  through `Util::addScript` / `Util::addStyle`, and provides the `#booktailor` mount
  point.
- **Navigation** — `appinfo/info.xml` registers the app in Nextcloud navigation with the
  `booktailor.page.index` route.

## Folder structure

```
appinfo/info.xml          App metadata, version, Nextcloud compatibility, navigation
lib/AppInfo/Application.php  App registration and bootstrap
lib/Controller/           Page + OCS API controllers
templates/index.php       Server-rendered page shell / asset loading
src/main.ts               Vue entry point
src/App.vue               Root Vue component
tests/unit/               PHPUnit tests (controller-level)
tests/bootstrap.php       PHPUnit bootstrap, loads the Nextcloud app
openapi.json              Generated OpenAPI description of the OCS API
vite.config.ts            Vite/Nextcloud build configuration
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
