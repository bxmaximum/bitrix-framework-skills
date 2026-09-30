---
name: bitrix-controllers
description: 'Engine\Controller actions for AJAX and routes — naming, autowired params, filter attributes, #[ActionAccess], JSON envelope, render helpers. Use when writing or debugging BX.ajax.runAction endpoints or routed controllers.'
---

# Bitrix Controllers

Baseline: main 23.0+ · Verified: main 26.800.0

| Task | Read |
| --- | --- |
| Files, action names, minimal controller, autowired params, PageNavigation | `rules/basics.md` |
| Default prefilters, filter attributes, `configureActions()`, `#[ActionAccess]`, custom filter | `rules/filters.md` |
| Errors, JSON envelope, response classes, `renderView()`, scopes, REST v3 | `rules/errors-response.md` |

## Invariants

- Actions only orchestrate: input → service → `Result` → data or `addErrors()`. Services come in as action params (DI canon: `bitrix-framework`).
- Filter attributes are **Since main 26.250\***; on older kernels use `configureActions()`. Never both for one action (`ActionConfigurationException`).
- `METHOD_*` constants live on `ActionFilter\HttpMethod`, not on the attribute `Attribute\Rule\HttpMethod`. Referencing them on the attribute throws while the controller is built, so every action of that controller fails.
- Rule attributes append to the defaults (`Authentication`, `HttpMethod([GET, POST])`, `Csrf`). Adding `#[Authentication]` or `#[Csrf]` again runs the filter twice.
- With PascalCase files write the controller segment in class case: `vendor:module.Post.create`. Lower-case `post` resolves to `lib/infrastructure/controller/post.php` and fails on Linux. REST lower-cases method names, so a PascalCase controller is unreachable over classic REST (`bitrix-rest`).
- HTML (`renderView`/`renderComponent`) only in controllers reached by routes; `BX.ajax.runAction()` expects JSON.
