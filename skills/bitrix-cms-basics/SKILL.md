---
name: bitrix-cms-basics
description: 'Classic CMS layer — sites, site templates, CSS placement, page properties, menus, include areas, PageNavigation and main.pagenavigation, user fields (CUserTypeEntity, custom types, rendering). Use for site structure, pagination or UF tasks.'
---

# CMS basics

Baseline: main 23.0+ · Verified: main 26.800.0

| Task | Read |
| --- | --- |
| Sites, site template layout, `#WORK_AREA#` | `rules/sites-templates.md` |
| Where CSS goes, `Asset::addCss()`, CSS merging | `rules/styles.md` |
| Page/directory properties, `.section.php`, menus, include areas, breadcrumbs, users | `rules/pages.md` |
| Pagination: `PageNavigation`, reverse/admin variants, `bitrix:main.pagenavigation` | `rules/page-navigation.md` |
| User fields: schema, values, public and admin output | `rules/user-fields.md` |
| Custom UF type, UF render component | `rules/user-field-types.md` |
| Landing / Sites24 pages | `bitrix-landing` |
| Mail events and templates | `bitrix-mail` |
| Files, uploads | `bitrix-files` |

## Invariants

- Templates, components and include files live in `/local/`; kernel templates are overridden by copies, never edited.
- Pagination: count with the same filter as the data query, set it before `initFromUri()`, never `allowAllRecords(true)` on large tables.
- UF schema only via `CUserTypeEntity`; `UserFieldTable` and `UserTable` are read-only.
- Escape everything a template prints (`htmlspecialcharsbx`).
- New code: `Asset::addCss()` / `Extension::load()` instead of `SetAdditionalCSS()`; D7 tablets (`SiteTable`, `UserTable`) for reads.
