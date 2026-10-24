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
`bamarni/composer-bin-plugin` hooks.

## Frontend

```bash
npm run build      # production Vite build
npm run dev        # development build
npm run watch      # rebuild on change
npm run lint       # ESLint over src/
npm run stylelint  # Stylelint over Vue/SCSS/CSS
```

Build output goes to `js/` and `css/` (git-ignored) and is loaded by
`templates/index.php`.

## PHP

```bash
composer run lint       # php -l on all non-vendor PHP files
composer run cs:check   # php-cs-fixer dry run
composer run cs:fix     # apply code style fixes
composer run psalm      # static analysis
composer run test:unit  # PHPUnit, tests/phpunit.xml
composer run rector     # Rector + cs:fix
composer run openapi    # regenerate openapi.json from route docblocks
```

Run a single test:

```bash
phpunit tests/unit/Controller/ApiTest.php -c tests/phpunit.xml
```

The PHPUnit bootstrap expects the app to be loadable by a Nextcloud instance (see
`tests/bootstrap.php`), so unit tests normally run inside a Nextcloud dev
environment with the app mounted.

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

## Verify loop

1. `npm run lint` (and `npm run stylelint` if styles changed).
2. `composer run lint` and `composer run cs:check`.
3. `composer run test:unit`.
4. `composer run psalm` when types/docblocks changed.
5. `composer run openapi` when routes or API docblocks changed, then review the
   `openapi.json` diff.

## Deploy / release

- Bump `<version>` in `appinfo/info.xml` (and `package.json` / `composer.json` version
  where relevant) and update `CHANGELOG.md`.
- Build frontend assets with `npm run build`; they must ship with the app.
- Package the app directory (without `node_modules`, `vendor-bin/*/vendor`,
  `tests` caches) and install it into Nextcloud, or publish via the Nextcloud App
  Store flow described in `README.md`.
