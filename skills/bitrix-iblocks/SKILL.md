---
name: bitrix-iblocks
description: 'Инфоблоки Битрикс: CIBlock, CIBlockElement, ORM-классы элементов по API_CODE, разделы, свойства и права. Используй для настройки инфоблока, выбора ORM или старого API и SEO-шаблонов.'
---

# Информационные блоки (`iblock`)

Baseline: main 23.0+ · Verified: iblock 26.100.0, main 26.1000.0

Перед PHP-кодом прочитай `bitrix-framework`, если ещё не читал его в этой задаче. По таблице открой только нужный файл правил.

| Задача | Читать |
| --- | --- |
| ID, границы ORM и классического API, создание, версия хранения, права | `rules/basics.md` |
| Компилируемые классы элементов / разделов, значения свойств, побочные действия ORM | `rules/orm-entities.md` |
| Свойства: список, справочник, свой тип; запись разделов / элементов, `CODE` | `rules/properties-sections-elements.md` |
| Выборки, фильтры свойств, SEO-значения, производительность | `rules/query-seo-perf.md` |

## Общие правила

- `Loader::includeModule('iblock')`. Для ORM со свойствами нужен `API_CODE`: `Element{ApiCode}Table`, `Model\Section::compileEntityByIblock()`. Запись через `ElementTable` всегда ошибочна.
- Структура (тип, инфоблок, свойство): `CIBlockType`/`CIBlock`/`CIBlockProperty`. Элементы / разделы: компилируемый ORM; для индекса поиска, пересчёта SEO, прав, документооборота, авто-`CODE` — `CIBlockElement`/`CIBlockSection`.
- ORM игнорирует права инфоблока: проверяй сам либо используй `CIBlockElement::GetList` с серверным `CHECK_PERMISSIONS = 'Y'`.
- ID инфоблоков, свойств, значений списка различаются между средами: находи по `CODE`/`API_CODE`/`XML_ID`.
- Справочники HL: `bitrix-highloadblock`; цены, SKU, остатки: `bitrix-catalog`.
