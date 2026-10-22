# Wiki

Project knowledge base lives here. `index.md` is generated — never edit by hand.

- Read `index.md` first, then the relevant page.
- One page per work item: `pages/tickets/<slug>.md` (`## Spec`, `## Plan`, `## Outcome`).
- Decisions: append with `node scripts/wiki.mjs decision "<choice> — because <reason>"`.
- After writing pages: `node scripts/wiki.mjs index`.
- Full conventions: `SCHEMA.md`.
