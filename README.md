# Book Tailor

AI-driven organizer of Markdown files. Automate learning material creation flow.

## Development setup (nextcloud-docker-dev)

This project is developed against the [nextcloud-docker-dev](https://github.com/nextcloud/nextcloud-docker-dev)
environment. The following steps assume you have a running instance in `/home/admin/Source/nextcloud-docker-dev`.

### 1. Bind the project folder to the container

The `docker-compose.override.yml` in the nextcloud-docker-dev directory is merged automatically with
`docker-compose.yml`. Create it (or extend it) to bind the project folder into the container's `apps-extra`
directory, so the app lives at `/var/www/html/apps-extra/booktailor`:

```yaml
# /home/admin/Source/nextcloud-docker-dev/docker-compose.override.yml
services:
  nextcloud:
    volumes:
      - /home/admin/Source/booktailor:/var/www/html/apps-extra/booktailor
```

Afterwards apply the change and start the container:

```bash
cd /home/admin/Source/nextcloud-docker-dev
docker compose up -d nextcloud
```

### 2. Add and enable the app in Nextcloud

The app is then picked up automatically from the `apps-extra` directory. To enable it either use `occ`:

```bash
cd /home/admin/Source/nextcloud-docker-dev
./scripts/occ.sh app:enable booktailor
# or directly:
docker compose exec --user www-data nextcloud ./occ app:enable booktailor
```

The dev server currently runs Nextcloud 35 (beta), while `appinfo/info.xml` declares support for 31–34.
For local testing enable the app with `--force`:

```bash
docker exec -u www-data master-nextcloud-1 php occ app:enable --force booktailor
```

The app is then available at [http://nextcloud.local/index.php/apps/booktailor/](http://nextcloud.local/index.php/apps/booktailor/)
(user `admin`, password `admin`).

### 3. Build the frontend after changes

The frontend is compiled with Vite into the `js/` and `css/` directories. After modifying `src/`, rebuild the assets:

```bash
cd /home/admin/Source/booktailor
npm install        # only needed once / after dependency changes
npm run build
```

Because the project folder is bind-mounted, the freshly built assets are instantly available inside the container.
For development use `npm run watch` to rebuild automatically on every change, then reload the app page (disable
the HTTP cache in your browser or press `Ctrl+Shift+R`).

PHP changes under `lib/`, `templates/` and `appinfo/` do not need a build step - just reload the page.

## Release

The app ships as a GitHub Release tarball consumed by the homelab Nextcloud image
pipeline. Bump the version, commit, then tag:

```bash
./scripts/set-version.sh 1.7.1
git add appinfo/info.xml package.json package-lock.json
git commit -m "Release 1.7.1"
git tag v1.7.1
git push origin main
git push origin v1.7.1
```

`.github/workflows/release.yml` validates that the tag matches `appinfo/info.xml`
and `package.json`, builds the frontend, and publishes
`booktailor-<version>.tar.gz` plus its `.sha256` checksum. The tarball contains a
top-level `booktailor/` directory for direct extraction into the Nextcloud image
build context.

## Usage

- To get started easily use the [Appstore App generator](https://apps.nextcloud.com/developer/apps/generate) to
  dynamically generate an App based on this repository with all the constants prefilled.
- Alternatively you can use the "Use this template" button on the top of this page to create a new repository based on
  this repository. Afterwards adjust all the necessary constants like App ID, namespace, descriptions etc.

Once your app is ready follow the [instructions](https://nextcloudappstore.readthedocs.io/en/latest/developer.html) to
upload it to the Appstore.

## Resources

### Documentation for developers:

- General documentation and tutorials: https://nextcloud.com/developer
- Technical documentation: https://docs.nextcloud.com/server/latest/developer_manual

### Help for developers:

- Official community chat: https://cloud.nextcloud.com/call/xs25tz5y
- Official community forum: https://help.nextcloud.com/c/dev/11
