---
name: bitrix-landing
description: 'Landing (Sites24) module — Site, Landing, Block, custom and repo blocks, publication, preview, hooks. Use when creating sites, pages or blocks from PHP, writing /local/blocks, publishing, adding counters/meta, or landing.repo REST.'
---

# Landing sites (`landing`)

Baseline: main 23.0+ · Verified: landing 26.1200.0, main 26.800.0

Block-based sites: Sites24, stores, knowledge bases, Vibe. Classic site templates, menus and includes: `bitrix-cms-basics`.

| Task | Read |
| --- | --- |
| Site types, pages, blocks from PHP, custom blocks, URLs in content | `rules/pages-blocks.md` |
| Publish, preview links, soft delete, hooks (counters, meta), rights | `rules/publish-hooks.md` |
| Repo blocks via REST, PublicAction batch, Disk files, pictures | `rules/rest-security.md` |

## Invariants

- `Loader::includeModule('landing')`; change data through `Site` / `Landing` / `Block`, never SQL on `b_landing_*`.
- Own blocks live in `/local/blocks/<namespace>/<code>/`; never edit `/bitrix/blocks/` or the module.
- Links in block content need an allow-listed scheme, otherwise they are saved as `''` without an error (**Since landing 26.1000**).
- Methods that write return `false`/`Result` and fill `$landing->getError()`; check them, they rarely throw.
- Don't subclass landing classes to override protected methods: their signatures change in minor releases.
- Deep PHP page logic belongs in a component on a classic page (`bitrix-components`), not in a landing block.
