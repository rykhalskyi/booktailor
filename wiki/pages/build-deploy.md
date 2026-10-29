---
created: 2026-10-22
type: build-deploy
status: in-progress
summary: Frontend build, PHP/JS linting, unit tests, static analysis, and deployment notes.
---

# Build & Deploy

## Prerequisites

- PHP 8.1+ and Composer (or run PHP/Composer inside a Nextcloud dev container).
- Node.js `^24` and npm `^11` (see `.nvmrc`).
- A Nextcloud 31–34 instance with the app placed in its `apps/` (or `apps-extra/`)
  directory for manual testing.

## Install dependencies

```bash
composer install
npm ci
```

`composer install` also installs the tools in `vendor-bin/` via the
`bamarni/composer-bin-plugin` hooks. Commit `composer.lock` and the
`vendor-bin/*/composer.lock` files so CI resolves the same versions.

`roave/security-advisories` was removed from `require-dev`: its `dev-latest`
constraint conflicts with the pinned PHP 8.1 platform. Run Composer's audit or a
separate advisory check in CI instead.

Apply schema changes in the dev instance after pulling a migration:

```bash
docker exec -u www-data master-nextcloud-1 php occ upgrade
# or, for a single app:
docker exec -u www-data master-nextcloud-1 php occ migrations:migrate booktailor
```

## Frontend

```bash
npm run build      # production Vite build
npm run dev        # development build
npm run watch      # rebuild on change
npm run lint       # ESLint over src/
npm run stylelint  # Stylelint over Vue/SCSS/CSS
npm run typecheck  # vue-tsc, no emit
npm test           # Vitest (tests/js/**/*.spec.ts)
npm run test:watch # Vitest in watch mode
npm run check      # lint + stylelint + typecheck + tests
```

Build output goes to `js/` and `css/` (git-ignored) and is loaded by
`templates/index.php`.

## PHP

```bash
composer run lint       # php -l on all non-vendor PHP files
composer run cs:check   # php-cs-fixer dry run
composer run cs:fix     # apply code style fixes
composer run psalm      # static analysis
composer run test:unit  # PHPUnit, tests/phpunit.xml (1G memory limit)
composer run rector     # Rector + cs:fix
composer run openapi    # regenerate openapi.json from route docblocks
```

The reviewed API contract is [openapi.draft.yaml](../../openapi.draft.yaml). The T1
routes are live under `/ocs/v2.php/apps/booktailor/api/v1`. `openapi.json` is still the
generated skeleton because the extractor requires full per-route docblocks; until those
are added, treat the draft as the source of truth and keep it in sync with route changes.

Run a single test:

```bash
phpunit tests/unit/Controller/ApiTest.php -c tests/phpunit.xml
```

The PHPUnit bootstrap expects the app to be loadable by a Nextcloud instance (see
`tests/bootstrap.php`), so tests run inside a Nextcloud dev environment with the app
mounted. It sets `TEST_DONT_LOAD_APPS=1` and loads only BookTailor and `files_versions`
so unrelated apps with incomplete schemas do not break the suite.

`composer run test:unit` runs with a 1G PHP memory limit because the full integration suite
exceeds PHP's default 512M. It includes tests that create temporary Nextcloud users,
exercise the real DB and file tree, and delete the users afterwards. The app tables must
be migrated first (`occ upgrade` or `occ migrations:migrate booktailor`). The version
tests skip themselves if `files_versions` is unavailable.

Psalm runs as Psalm 6 and uses `build/stubs/files_versions.php` plus `doctrine/dbal` for
classes that are not part of the `nextcloud/ocp` stubs.

### Using a Nextcloud Docker dev stack

The local environment is `~/Source/nextcloud-docker-dev`. Bind the app into
`apps-extra` by adding an entry to its `docker-compose.override.yml` (the file is
merged automatically):

```yaml
services:
  nextcloud:
    volumes:
      - /home/admin/Source/booktailor:/var/www/html/apps-extra/booktailor
```

Recreate the container and enable the app:

```bash
cd ~/Source/nextcloud-docker-dev
docker compose up -d nextcloud
docker exec -u www-data master-nextcloud-1 php occ app:enable --force booktailor
```

`--force` is only needed because the dev stack tracks Nextcloud 35 beta while
`appinfo/info.xml` declares 31–34. The app is then served at
`http://nextcloud.local/index.php/apps/booktailor/` (user `admin`, password `admin`);
assets are read live from the bind mount.

Markdown editing needs the Nextcloud Text app, which the server master tree does not
bundle. Install the stable35 branch into the dev instance once:

```bash
cd ~/Source/nextcloud-docker-dev/workspace/server/apps-extra
git clone --depth 1 --branch stable35 https://github.com/nextcloud/text.git text
docker exec -u www-data master-nextcloud-1 php occ app:enable text
```

If PHP is not installed on the host, run the PHP commands inside the Nextcloud app
container, from the app directory (`/var/www/html/apps-extra/booktailor`), e.g.:

```bash
docker exec master-nextcloud-1 bash -c \
  'cd /var/www/html/apps-extra/booktailor && composer run test:unit'
```

## Translations

UI strings use `t('booktailor', '...')` and PHP user-facing messages use `IL10N`.
`l10n/` holds generated translation files; do not hand-edit them. Extract the source
catalog with the Nextcloud translation tool, translate through Transifex/Weblate, then
convert the downloaded catalogs back into `l10n/*.json` and `l10n/*.js`:

```bash
php translationtool.phar create-pot-files
# after downloading translations:
php translationtool.phar convert-po-files
```

English is the source fallback, so missing translations render the source string.
Technical logs and internal exception messages stay English.

## AI configuration

AI provider keys are **per user** and live in the in-app **Settings** screen. They are
encrypted with Nextcloud's `ICrypto` and only a last-four mask is returned by the API.
Never put provider keys in `config.php`, `.env`, or the repository.

Langfuse is app-level configuration. There are two supported ways to provide it.

**Option A — app config via `occ`:**

```bash
docker exec -u www-data master-nextcloud-1 php occ config:app:set booktailor langfuse_url --value="http://langfuse.homelab.home.arpa"
docker exec -u www-data master-nextcloud-1 php occ config:app:set booktailor langfuse_public_key --value="pk-lf-..."
docker exec -u www-data master-nextcloud-1 php occ config:app:set booktailor langfuse_secret_key --value="sk-lf-..."
# Optional; defaults to production
docker exec -u www-data master-nextcloud-1 php occ config:app:set booktailor langfuse_prompt_label --value="production"
```

**Option B — environment variables (no `occ`):**

App config values win when present; otherwise BookTailor reads:

```bash
BOOKTAILOR_LANGFUSE_URL=http://langfuse.homelab.home.arpa
BOOKTAILOR_LANGFUSE_PUBLIC_KEY=pk-lf-...
BOOKTAILOR_LANGFUSE_SECRET_KEY=sk-lf-...
BOOKTAILOR_LANGFUSE_PROMPT_LABEL=production
```

The standard `LANGFUSE_URL`, `LANGFUSE_HOST`, `LANGFUSE_PUBLIC_KEY`,
`LANGFUSE_SECRET_KEY`, and `LANGFUSE_PROMPT_LABEL` names are accepted as aliases.
A template is in `deploy/nextcloud-langfuse.env.example`. For Docker Compose, add
those variables to the Nextcloud service or use an `env_file`. For Kubernetes, put them
in a Secret and reference it from the Nextcloud HelmRelease `extraEnv`, for example:

```yaml
nextcloud:
  extraEnv:
    - name: BOOKTAILOR_LANGFUSE_URL
      valueFrom:
        secretKeyRef:
          name: booktailor-langfuse
          key: BOOKTAILOR_LANGFUSE_URL
    - name: BOOKTAILOR_LANGFUSE_PUBLIC_KEY
      valueFrom:
        secretKeyRef:
          name: booktailor-langfuse
          key: BOOKTAILOR_LANGFUSE_PUBLIC_KEY
    - name: BOOKTAILOR_LANGFUSE_SECRET_KEY
      valueFrom:
        secretKeyRef:
          name: booktailor-langfuse
          key: BOOKTAILOR_LANGFUSE_SECRET_KEY
```

Create the Secret from an env file:

```bash
kubectl -n homelab create secret generic booktailor-langfuse \
  --from-env-file=deploy/nextcloud-langfuse.env
```

Langfuse is best-effort: prompt fetch and OTLP tracing failures never block chat; local
prompt fallbacks are used. Prompt/response content is sent to Langfuse only when the user
opts in under **Settings → Privacy and observability**. Langfuse credentials and keys are
never logged.

Apply the T6 migration and verify the routes:

```bash
docker exec -u www-data master-nextcloud-1 php occ migrations:migrate booktailor
curl -u admin:admin -H 'OCS-APIRequest: true' -H 'Accept: application/json' \
  http://nextcloud.local/ocs/v2.php/apps/booktailor/api/v1/ai/settings
```

AI provider setup needs an active profile; without one the chat endpoint returns 422 with
a settings hint. DeepSeek and SiliconFlow are supported through OpenAI-compatible
endpoints. The MVP chat transport is normal JSON (no SSE); the client shows progress and
supports abort/retry.

## Verify loop

1. `npm run lint` and `npm run stylelint` when Vue/CSS changed.
2. `npm run typecheck` when TypeScript or Vue SFCs changed.
3. `npm test` for frontend unit tests.
4. `composer run lint` and `composer run cs:check`.
5. `composer run test:unit`.
6. `composer run psalm` when types/docblocks changed.
7. `composer run openapi` when routes or API docblocks changed, then review the
   `openapi.json` diff.

## Deploy / release

- Bump `<version>` in `appinfo/info.xml` (and `package.json` / `composer.json` version
  where relevant) and update `CHANGELOG.md`.
- Build frontend assets with `npm run build`; they must ship with the app.
- Run `scripts/package.sh` to build `build/booktailor-<version>.tar.gz` with only the
  runtime files (`appinfo`, `lib`, `l10n`, `img`, `templates`, `js`, `css`, docs, and
  `openapi.json`). Install that tarball into Nextcloud or publish it through the
  Nextcloud App Store flow described in `README.md`.
- Do not include `node_modules`, `vendor-bin/*/vendor`, tests, caches, `src`, or local
  env files in a release archive.
