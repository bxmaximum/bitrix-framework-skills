---
name: bitrix-security
description: 'Bitrix security patterns — CSRF, XSS and link sanitizing, open redirects, SQL injection in ORM/ExpressionField, SSRF and local-file reads, uploads, rights checks, JWT, encrypted fields. Use when handling user input or auditing code.'
---

# Security

Baseline: main 23.0+ · Verified: main 26.800.0

General defaults (no superglobals, keep default prefilters, escape output) live in `bitrix-framework`.

| Task | Read |
| --- | --- |
| CSRF, escaping, HTML sanitizing, link and redirect targets, raw-HTML sinks | `rules/csrf-xss.md` |
| SQL injection (raw, ORM, `ExpressionField`), SSRF, local files, uploads, stored settings | `rules/sql-ssrf.md` |
| Rights checks, `#[ActionAccess]`, REST scopes, JWT/JWK, encryption, 2FA | `rules/jwt-crypto-access.md` |

## Invariants

- Untrusted: request data, and also DB-stored settings, imports, REST payloads. The type and owner of an entity come from its DB record, never from the request.
- Whitelist whatever becomes SQL structure (field names, operators, functions, `order` keys), whatever its source.
- Escape for the output context: HTML `htmlspecialcharsbx()`, JS `Json::encode()`, URLs through a scheme allow-list.
- User input never goes unchecked into `CFile::MakeFileArray()`, `file_get_contents()`, `unserialize()`, redirect targets.
- `Authentication`/`Csrf` filters don't authorize: check rights on the object in every action.
