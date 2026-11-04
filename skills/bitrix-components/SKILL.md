---
name: bitrix-components
description: 'Компоненты Битрикс в /local: class.php, шаблоны, result_modifier, кеш, ЧПУ и AJAX через Controllerable. Используй при создании компонента, переопределении шаблона и постраничном выводе.'
---

# Компоненты Битрикс

Baseline: main 23.0+ · Verified: main 26.1000.0

Перед PHP-кодом прочитай `bitrix-framework`, если ещё не читал его в этой задаче. По таблице открой только нужный файл правил.

| Задача | Читать |
| --- | --- |
| Размещение, заготовка, `class.php`, параметры, `.description.php`, `.parameters.php` | `rules/structure.md` |
| Поиск и переопределение шаблона, `template.php`, `result_modifier.php`, `component_epilog.php` | `rules/template.md` |
| Кеш результата, постраничный список, комплексный ЧПУ, AJAX-действия | `rules/cache-sef-ajax.md` |
| `PageNavigation`, шаблоны навигации | `bitrix-cms-basics` |
| Грид, фильтр, панель инструментов в шаблонах | `bitrix-ui` |

## Общие правила

- Свои компоненты — в `/local/components/<vendor>/`; компонент ядра меняй копией шаблона в `/local/templates/<site>/components/…`.
- Логика — в `class.php` и сервисах; шаблон только выводит `$arResult`, экранируя значения.
- `onPrepareComponentParams()` приводит тип каждого параметра; `~KEY` содержит исходное значение.
- Всё влияющее на вывод входит в ключ кеша: группы пользователя, страница, фильтр.
- Вложенному компоненту передавай `$component` четвёртым аргументом `IncludeComponent()`.
- AJAX — через действия `Controllerable` с предварительными фильтрами и подписанными параметрами.
