## [2026-10-22] init | Wiki created
## [2026-10-22] update | BookTailor epic created
## [2026-10-22] spec | BookTailor MVP specification
## [2026-10-22] plan | BookTailor MVP delivery plan
## [2026-10-22] ticket | T0 — Foundation and de-risking
## [2026-10-22] ticket | T1 — Core domain and storage
## [2026-10-22] ticket | T2 — Main UI
## [2026-10-22] ticket | T3 — Structures and numbering
## [2026-10-22] ticket | T4 — Editing, versions, and tags
## [2026-10-22] ticket | T5 — Markdown composition
## [2026-10-22] ticket | T6 — AI assistant
## [2026-10-22] ticket | T7 — Images, settings, and release
## [2026-10-22] decision | Recorded D-01 through D-12 for the BookTailor MVP
## [2026-10-23] update | Local Nextcloud dev setup: bind mount, vite entry fix, NC35 --force note
## [2026-10-23] update | T0 schema, glossary, and storage layout; decisions D-13..D-18
## [2026-10-23] update | T0 file-operations spike: stable IDs, idempotent create, stale nodes, native trash
## [2026-10-23] update | T0 versions spike: native metadata for check-in messages, D-19
## [2026-10-23] update | T0 editor spike: Text app launch, NodeWrittenEvent saves, current-revision check-in (D-20/D-21)
## [2026-10-24] research | T0 background-job spike: queued execution, cron drain, and status persistence
## [2026-10-24] research | T0 Langfuse/BYOK spike: OTLP tracing, prompt links, and encrypted per-user keys
## [2026-10-24] research | T0 compose-job contract and compositions.started_at schema amendment
## [2026-10-24] research | T0 OpenAPI draft covering the MVP route surface
## [2026-10-24] update | T0 test harness: PHPUnit and Vitest verified; Composer install fixed
## [2026-10-24] decision | Recorded D-22 through D-26 for Langfuse/BYOK, composition jobs, and API versioning
## [2026-10-24] update | Project overview: Vitest, Langfuse, and the T0 API draft
## [2026-10-24] update | T0 complete: OpenAPI draft reviewed and accepted as the T1 baseline
## [2026-10-24] ticket | T1 — Core domain and storage implemented
## [2026-10-24] update | T1 API: owner-scoped OCS v1 books, chapters, structures, assets, tags, and versions
## [2026-10-24] update | T1 tests: integration lifecycle, ownership, collisions, cascades, and version restore
## [2026-10-24] update | Build/deploy: T1 migrations, isolated test bootstrap, Psalm 6 stubs, and API route prefix
## [2026-10-24] decision | Recorded D-27 through D-30 for T1 ordering, test isolation, deferred endpoints, and the API draft
## [2026-10-24] update | Versions spike: T1 found getVersionsForFile must materialize the current revision before setMetadataValue
## [2026-10-24] ticket | T2 — Main UI implemented
## [2026-10-24] update | T2 frontend: book navigation, chapter tree, structure selector, asset panel, and dialogs
## [2026-10-24] update | T2 verification: lint, stylelint, tests, build, and headless Chromium smoke
## [2026-10-24] decision | Recorded D-31 through D-34 for T2 layout, state, dialogs, and the API client
## [2026-10-24] update | T2 smoke: subchapter, asset upload, confirmation deletes, and cascade verified headlessly
## [2026-10-24] update | T2 review fixes: stale-response guards, per-chapter loading, router-based file links, single-submit dialogs, and mutation-time action blocking
## [2026-10-24] update | T2 review fixes: ARIA tree semantics and arrow-key navigation; added vue-tsc typecheck and review tests
## [2026-10-25] update | T3 Slice 1: extracted structure tree builder, added numbering service, structure tree numbers, and exclusion/order tests
## [2026-10-25] update | T3 Slice 2: structure CRUD with default-structure protection, copied entries, owner scoping, and API client methods
## [2026-10-25] update | T3 Slice 3: inclusion and ordering writes, position normalization, default invariants, parent-based new-chapter inclusion, and entries API
## [2026-10-25] update | T3 Slice 4: validated chapter promotion, demotion, and parent-sibling attachment with folder moves, structure reordering, rollback, and API client support
## [2026-10-25] update | T3 Slice 5: structure manager with CRUD, inclusion, sibling reorder, allowed-target chapter moves, and numbering preview; T3 complete
## [2026-10-25] update | T3 review fixes: editor readiness guard, slot-preserving orphan ordering, structure timestamps, explicit move targets, and frontend/backend coverage
## [2026-10-25] ticket | T4 — Editing, versions, and tags implemented
## [2026-10-25] update | T4 backend: asset and version content endpoints, capabilities, asset path, and NodeWrittenEvent metadata sync
## [2026-10-25] update | T4 frontend: Files/Text editor launch, history dialog with check-in/preview/restore, tag manager, assignment, and filtering
## [2026-10-25] update | T4 verification: 36 PHP tests, 57 JS tests, psalm, cs, lint, build, and HTTP smoke
## [2026-10-25] update | Tag colors: 10-color palette, tag edit dialog, colored chips, migration and app version 1.2.0
## [2026-10-25] update | UI: Tags moved to navigation below books; chapter asset pane restored with a visible Tags action per asset
## [2026-10-25] update | Assets: dedicated chapter asset manager view with back button and Edit assets menu action; tree-only main screen
## [2026-10-25] update | Chapter tree: asset chips show a colored dot per tag
## [2026-10-25] update | Asset view: tag filter moved to its own row below the header so it no longer overlaps New asset
