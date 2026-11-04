# Индексация своего содержимого

Только `CSearch`, без D7 и ORM. Оберни сервисом, вызывай из событий ORM / таблетов либо сервиса записи.

```php
if (\Bitrix\Main\Loader::includeModule('search'))
{
    CSearch::Index('vendor.docs', (string)$doc->getId(), [
        'TITLE' => $doc->getTitle(),
        'BODY' => CSearch::KillTags($doc->getHtml()), // BODY сам не очищается
        'TAGS' => 'contract, 2026',
        'SITE_ID' => ['s1' => '/docs/' . $doc->getId() . '/'],
        'URL' => '/docs/' . $doc->getId() . '/',
        'PERMISSIONS' => [2], // ID групп либо коды доступа
        'PARAM1' => 'contract',
        'PARAM2' => (string)$doc->getSectionId(),
        'DATE_CHANGE' => $doc->getUpdatedAt()->format('d.m.Y H:i:s'),
    ], true);
}
```

`CSearch::Index($MODULE_ID, $ITEM_ID, $arFields, $bOverWrite = false, $SEARCH_SESS_ID = '')` → ID индекса, 0 (ничего не проиндексировано / удалено) либо `false`. Уникальный ключ — (`MODULE_ID`, `ITEM_ID`).

## Поля

| Ключ | Правило |
| --- | --- |
| `TITLE`, `BODY`, `TAGS` | Текст без HTML: `CSearch::KillTags()`. Если `TITLE` и `BODY` пусты, новая запись пропускается, существующая **удаляется** |
| `SITE_ID` | `'s1'`, `['s1', 's2']` либо `['s1' => '/url/']` (адрес сайта приоритетнее). Пустой массив ничего не индексирует. ID сайта — 2 символа |
| `URL` | `#SITE_DIR#`, `#SERVER_NAME#`, `#LANG#` заменяются при чтении. Значение с `=` разрешается через `OnSearchGetURL` |
| `DATE_CHANGE` | Всегда `DD.MM.YYYY HH:MI:SS` (`d.m.Y H:i:s`), либо `LAST_MODIFIED` в формате даты сайта. Одно обязательно |
| `DATE_FROM`, `DATE_TO` | Формат даты сайта либо `false`; применяются с `CHECK_DATES = 'Y'` |
| `PERMISSIONS` | `[2]` → G2 (все, включая гостей); строки сохраняются: AU, U5, G12, коды `OnSearchCheckPermissions` |
| `PARAM1`, `PARAM2` | Ключи своего фильтра (тип, раздел); участвуют и в правилах ранга |
| `PARAMS` | `['brand' => 'x', 'color' => ['red', 'blue']]`, значения до 100 символов, доступны фильтру |
| `USER_ID`, `ENTITY_TYPE_ID`, `ENTITY_ID`, `CUSTOM_RANK` | Сохраняемые столбцы |
| `INDEX_TITLE` | `false` исключает из поиска по заголовку |

Остальные ключи молча отбрасываются.

## Обновление

- При `$bOverWrite = false` и неизменном `DATE_CHANGE` обновляются только права / сайты / параметры, не заголовок, тело, теги. При смене содержимого передавай `true` либо новую дату.
- Пустой `PERMISSIONS` не очищает права при обновлении. Для прав — `CSearch::ChangePermission($MODULE_ID, $arGroups, $ITEM_ID)`; `[]` очищает, искать сможет только администратор. Аргумент `$SITE_ID` не передавай: ломает запрос.
- Только метаданные: `CSearch::ChangeIndex($MODULE_ID, $arFields, $ITEM_ID)`, `CSearch::ChangeSite($MODULE_ID, ['s1' => '/url/'], $ITEM_ID)`.
- Передавай документ целиком: индексы заголовков и тегов читают `SITE_ID` того же вызова.
- search < 25.200 не обновлял текст существующей записи на стандартном / mysql / pgsql движке; там вызывай `DeleteIndex()`, затем `Index()`.

## Удаление

```php
CSearch::DeleteIndex('vendor.docs', (string)$id); // одна запись
CSearch::DeleteIndex('vendor.docs', false, 'contract'); // по PARAM1
// ОПАСНО: CSearch::DeleteIndex('vendor.docs') удалит весь индекс модуля
```

`DeleteIndex($MODULE_ID, $ITEM_ID = false, $PARAM1 = false, $PARAM2 = false, $SITE_ID = false)`; `%` в `$ITEM_ID` превращается в LIKE.

## Изменение записи другого модуля (`BeforeIndex`)

`search:BeforeIndex` получает `$arFields` с `MODULE_ID`, `ITEM_ID`; верни изменённый массив. Сначала отфильтруй модуль:

```php
public static function onBeforeIndex(array $fields): array
{
    if (($fields['MODULE_ID'] ?? '') === 'iblock' && (int)($fields['PARAM2'] ?? 0) === self::NEWS_IBLOCK_ID)
    {
        $fields['BODY'] .= ' ' . self::extraText((int)$fields['ITEM_ID']);
    }
    return $fields;
}
```

Инфоблок: `ITEM_ID` — ID элемента либо `S<id>` раздела; `PARAM1` — тип, `PARAM2` — ID инфоблока. Индексация только с `INDEX_ELEMENT`/`INDEX_SECTION = 'Y'`.

Проверка сохранённого: `CSearch::GetIndex($MODULE_ID, $ITEM_ID)`.

## Изменения search 26.0

- `CSearch::AddPermission($moduleId, $accessCode, $filter)` / `DeletePermission(...)` меняют один код доступа для выбранных записей, без переиндекса всего текста; Sphinx/OpenSearch синхронизируются очередью search_sync_perm. На старом модуле используй ChangePermission() для каждого ITEM_ID.
- UpdateSite() больше не переписывает URL существующей привязки сайта. После смены адресов проверь фактическую выдачу и выполни полный переиндекс; простая повторная передача SITE_ID => [site => url] не обновит ссылку. На старом модуле проверь прежнее поведение.
