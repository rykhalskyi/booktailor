# Wiki Schema

Full conventions for this wiki. `AGENTS.md` is the short always-loaded summary; read
this only when you need detail.

## Token discipline

1. **Git is the history.** No `## Updates` sections, no creation dates in index
   entries, no restating superseded content. Use `git log -p wiki/pages/<file>`.
2. **One page per work item by default.** A ticket carries `## Spec`, `## Plan`, and
   `## Outcome`. Separate `specs/` + `plans/` pages only for large/ambiguous epics
   shared by several tickets.
3. **Decisions get a one-liner** in `decisions.md`, referenced by id (`D-07`) from the
   ticket rather than re-explained.
4. **Minimal frontmatter:** `created`, `type`, `status`, `summary`. No `tags`, no
   `related` — body links and `grep` cover those.
5. **Never hand-edit `index.md`.** Regenerate with `node scripts/wiki.mjs index`.
   Append `log.md` / `decisions.md` via `wiki.mjs log` / `wiki.mjs decision`.
6. **`AGENTS.md` is auto-loaded every session — keep it short.** Detail goes here.
7. **Batch writes** at the end of a work session.

## Architecture

```
wiki/
├── AGENTS.md      # short always-loaded summary
├── SCHEMA.md      # this file
├── index.md       # generated catalog — never edit by hand
├── log.md         # append-only activity log
├── decisions.md   # append-only decision record (ADR-lite)
└── pages/
    ├── project-overview.md
    ├── build-deploy.md
    ├── debugging.md          # optional
    ├── epics/                # large features broken into tickets (optional)
    ├── tickets/              # default artifact per work item
    ├── specs/                # only for large/ambiguous epics
    ├── plans/                # only for large/ambiguous epics
    └── research/             # investigations and analysis
```

Helper script: `scripts/wiki.mjs` (also exposed as `npm run wiki`).

## Page conventions

```yaml
---
created: YYYY-MM-DD
type: overview | build-deploy | epic | ticket | spec | plan | research
status: proposed | in-progress | implemented | superseded
summary: One-line description used to build index.md
---
```

- `created` never changes after creation.
- `summary` is required so `wiki.mjs index` can catalog without reading the body.
- Do not add `tags` or `related`.

### Links

Relative markdown links only — never wikilinks. Paths are relative to the file
containing the link:

| From | To | Link |
|---|---|---|
| `wiki/index.md` | any page | `pages/<folder>/<slug>.md` |
| `pages/<sub>/x.md` | `pages/<other>/y.md` | `../<other>/y.md` |
| `pages/<sub>/x.md` | `pages/root-page.md` | `../root-page.md` |
| `pages/root-page.md` | `pages/<folder>/y.md` | `<folder>/y.md` |

### Slugs

- Tickets: `<ticket-id>-<brief>` (e.g. `ticket-15-localization-infra`, or
  `issue-11-dual-price` when sourced from a GitHub issue). Brief = 2-4 lowercase,
  hyphen-separated words.
- Specs and plans share one `<feature-slug>` so they cross-link.
- Research: `<topic>`, lowercase, hyphen-separated.

## Workflow

### Ticket (default)

```markdown
# T<n> — <Title>

Part of the [epic](../epics/<slug>.md).   # if applicable

## Spec
What and why: requirements, scope, design decisions, constraints.

## Plan
Ordered steps, files to create/modify, migrations, tests, risks.

## Outcome
What shipped, deviations, files changed.
```

Flip `status:` to `implemented` when done. Log with
`node scripts/wiki.mjs log ticket "<title>"`.

### Large epic: optional spec + plan

Only when genuinely large/ambiguous, or one spec is shared by several tickets, create
`pages/specs/<slug>.md` and `pages/plans/<slug>.md` and link the tickets to them.

### Research

`pages/research/<slug>.md`: context, findings, conclusions, recommended actions.

## Operations

| Operation | Command |
|---|---|
| Regenerate index | `node scripts/wiki.mjs index` |
| Append log | `node scripts/wiki.mjs log <action> "<description>"` |
| Append decision | `node scripts/wiki.mjs decision "<choice> — because <reason>"` |
| Lint | `node scripts/wiki.mjs lint` |

Log actions: `init`, `ticket`, `spec`, `plan`, `research`, `update`, `decision`.

Lint checks: missing `created`/`type`, dangling relative links, pages absent from the
index.
