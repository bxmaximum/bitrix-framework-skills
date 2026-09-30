---
name: bitrix-components
description: 'Bitrix components — class.php, params, template overrides, result_modifier/component_epilog, result cache, paged lists, SEF, Controllerable AJAX. Use when building, overriding or caching components in /local.'
---

# Bitrix components

Baseline: main 23.0+ · Verified: main 26.800.0

| Task | Read |
| --- | --- |
| Placement, scaffold, `class.php`, params, `.description.php`, `.parameters.php` | `rules/structure.md` |
| Template search order and overrides, `template.php`, `result_modifier.php`, `component_epilog.php` | `rules/template.md` |
| Result cache, paged list, complex SEF, AJAX actions | `rules/cache-sef-ajax.md` |
| `PageNavigation`, pager templates | `bitrix-cms-basics` |
| Grid, filter, toolbar in templates | `bitrix-ui` |

## Invariants

- Own components in `/local/components/<vendor>/`; kernel components are changed only by copying their template to `/local/templates/<site>/components/…`.
- Logic in `class.php` and services; templates only render `$arResult`, escaping output.
- `onPrepareComponentParams()` casts every param; `~KEY` is the raw value.
- Everything that changes output is in the cache key (user groups, page number, filter).
- Nested components get `$component` as the 4th `IncludeComponent()` argument.
- AJAX through `Controllerable` actions with prefilters and signed parameters.
