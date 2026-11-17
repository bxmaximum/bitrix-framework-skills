---
name: bitrix-controllers
description: 'Используй для AJAX-действий и контроллеров маршрутов Битрикса: Engine\Controller, параметры, фильтры, #[ActionAccess] и ответы. Вызовы BX.ajax.runAction и диагностика ошибок.'
---

# Контроллеры Битрикса

Baseline: main 23.0+ · Verified: main 26.1000.0

Перед PHP-кодом прочитай `bitrix-framework`, если ещё не читал его в этой задаче.
В таблице выбери только нужный `rules/*.md`; пути относительны каталогу скилла.

| Задача | Читать |
| --- | --- |
| Файлы, имена действий, минимальный контроллер, параметры DI, PageNavigation | `rules/basics.md` |
| Стандартные фильтры, атрибуты, `configureActions()`, `#[ActionAccess]`, свой фильтр | `rules/filters.md` |
| Ошибки, JSON-обёртка, классы ответов, `renderView()`, подпись экспорта, REST v3 | `rules/errors-response.md` |

## Обязательные правила

- Действие только связывает ввод → сервис → `Result` → данные или `addErrors()`. Сервисы передаются параметрами действия (канон DI: `bitrix-framework`).
- Атрибуты фильтров доступны **Since main 26.250\***; раньше используй `configureActions()`. Не совмещай оба способа для одного действия: `ActionConfigurationException`.
- Константы `METHOD_*` принадлежат `ActionFilter\HttpMethod`, а не атрибуту `Attribute\Rule\HttpMethod`. Обращение к ним на атрибуте ломает создание контроллера и все его действия.
- Атрибуты правил добавляют фильтры к стандартным (`Authentication`, `HttpMethod([GET, POST])`, `Csrf`). Повторный `#[Authentication]` или `#[Csrf]` запускает фильтр дважды.
- Регистр имён действий в классическом REST см. в `bitrix-rest`.
- HTML (`renderView`/`renderComponent`) возвращай только через маршруты; `BX.ajax.runAction()` ожидает JSON.
