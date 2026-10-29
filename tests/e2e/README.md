# End-to-end tests

Playwright exercises the app against a running Nextcloud instance.

## Prerequisites

- The Nextcloud dev stack from `../../wiki/pages/build-deploy.md`.
- The app enabled and served at `http://nextcloud.local`.
- Admin credentials (`admin` / `admin` by default).
- The Nextcloud Text app if a test opens the editor.

```bash
npm run test:e2e:install
npm run test:e2e
```

Environment overrides:

```bash
BOOKTAILOR_BASE_URL=http://nextcloud.local \
BOOKTAILOR_ADMIN_USER=admin \
BOOKTAILOR_ADMIN_PASSWORD=admin \
npm run test:e2e
```

Each test creates a uniquely named book through the OCS API and deletes it in
`finally`. If the instance was started with `occ app:enable --force booktailor`
on Nextcloud 35, that is expected and only needed because `info.xml` declares
31–34.
