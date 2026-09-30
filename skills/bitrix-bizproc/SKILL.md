---
name: bitrix-bizproc
description: 'Business processes (bizproc) — CBPDocument, Starter, custom activities and triggers in /local/activities, template rights. Use for approvals, document workflows, robots/activities, starting or stopping workflows from PHP, or bizproc agents.'
---

# Business processes (`bizproc`)

Baseline: main 23.0+ · Verified: bizproc 26.1400.0, bizprocdesigner 26.800.0, main 26.800.0

Runtime, documents and activities are in `bizproc`; the template designer UI is `bizprocdesigner` (needs feature `BizProc`). Load `bizproc` and the document owner module (`crm`, `lists`, …).

| Task | Read |
| --- | --- |
| Start, terminate, kill, external events, document ids, `Starter` | `rules/start-stop.md` |
| Custom activity: folder, `.description.php`, class, reserved names, waiting for events | `rules/custom-activities.md` |
| Custom triggers for the Nodes designer | `rules/triggers.md` |
| Template rights, designer controllers, import, template service | `rules/templates-access.md` |
| Stuck pauses, fault limit, tracking, storage cleanup, agents vs Messenger | `rules/runtime-ops.md` |

## Invariants

- A document id is `[moduleId, entity, documentId]`, a type `[moduleId, entity, documentType]`. Copy triples from the owner module or an existing template; never invent them.
- `CBPDocument` methods report through `$errors` (by reference), not exceptions: check it after every call.
- Check rights before a manual start and in every custom controller (`CBPDocument::canUserOperateDocument()` / `canUserOperateDocumentType()`).
- Approvals and document state machines → bizproc. Periodic sync and heavy I/O → agents or Messenger (`bitrix-background-jobs`), never inside `execute()`.
- Custom activity classes and helpers are vendor-prefixed and never reuse `CBPActivity` member names.
