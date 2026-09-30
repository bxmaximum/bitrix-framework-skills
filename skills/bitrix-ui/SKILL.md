---
name: bitrix-ui
description: 'Bitrix UI kit (ui/main JS extensions, PHP facades): popups, side panels, dialogs, buttons, Toolbar, grid and filter, entity selector, forms, alerts, counters, uploader, viewer, timeline. Use when building admin, portal or component UI.'
---

# Bitrix UI

Baseline: main 23.0+ · Verified: ui 26.687.0, main 26.800.0

| Task | Read |
| --- | --- |
| `main.popup`, `Menu`, side panel (slider) pages | `rules/popups-sidepanel.md` |
| Modal dialog, confirm, system menu, popover | `rules/dialogs-menus.md` |
| Buttons in JS and PHP, button menus | `rules/buttons.md` |
| Page header: title, buttons, filter | `rules/toolbar.md` |
| List page: grid, filter, group actions | `rules/grid-filter.md` |
| Pick users/entities, provider, catalog picker | `rules/selectors.md` |
| Form markup, inputs, select, color picker | `rules/forms-inputs.md` |
| Alerts, toasts, hints, user tooltip, skeleton | `rules/feedback.md` |
| Counters, status labels, badges | `rules/counters-labels.md` |
| Stage bar, history feed, BBCode text | `rules/stageflow-timeline.md` |
| File upload field | `rules/uploader.md` |
| Open files in the viewer | `rules/viewer.md` |
| Icons, typography, Lottie, focus/a11y | `rules/icons-typography-a11y.md` |

## Invariants

- Load by extension id: `Extension::load('ui.x')` in PHP, `import { X } from 'ui.x'` in bundles. Never include kernel JS/CSS by file path: files move between versions.
- New design: `ui.system.*` (**Since ui 26.200\***). Older kernels: `main.popup`, `ui.alerts`, `ui.dialogs.messagebox`. `Since` legend: `bitrix-framework`.
- String `content`, `text`, `html`, `description` options and PHP HTML slots are often raw HTML: pass DOM nodes or escape (`Text.encode()`, `htmlspecialcharsbx()`).
- Widgets render and store UI state only. Data, rights and CSRF stay on the server (`bitrix-controllers`).
- Public API only: no `_private` fields, no overriding internal methods.
- Vue: keep widget instances out of reactive state (`markRaw()`), see `bitrix-vue`.
