# Indexing custom content

Legacy API only: `CSearch` (no D7 class, no ORM tablet). Wrap it in your own service; call it from ORM/tablet events or your write service.

```php
if (\Bitrix\Main\Loader::includeModule('search'))
{
    CSearch::Index('vendor.docs', (string)$doc->getId(), [
        'TITLE' => $doc->getTitle(),
        'BODY' => CSearch::KillTags($doc->getHtml()),       // BODY is not stripped for you
        'TAGS' => 'contract, 2026',
        'SITE_ID' => ['s1' => '/docs/' . $doc->getId() . '/'],
        'URL' => '/docs/' . $doc->getId() . '/',
        'PERMISSIONS' => [2],                                // group ids or access codes
        'PARAM1' => 'contract',
        'PARAM2' => (string)$doc->getSectionId(),
        'DATE_CHANGE' => $doc->getUpdatedAt()->format('d.m.Y H:i:s'),
    ], true);
}
```

Signature: `CSearch::Index($MODULE_ID, $ITEM_ID, $arFields, $bOverWrite = false, $SEARCH_SESS_ID = '')` → index id, `0` (nothing indexed or deleted) or `false`. Unique key is (`MODULE_ID`, `ITEM_ID`).

## Fields

| Key | Rule |
| --- | --- |
| `TITLE`, `BODY`, `TAGS` | Plain text; HTML → `CSearch::KillTags()`. Both `TITLE` and `BODY` empty → a new item is skipped, an existing one is **deleted** |
| `SITE_ID` | `'s1'`, `['s1', 's2']` or `['s1' => '/url/']` (per-site URL wins). Empty array → nothing indexed. Site ids are 2 chars |
| `URL` | `#SITE_DIR#`, `#SERVER_NAME#`, `#LANG#` are replaced on read. A value starting with `=` is resolved by `OnSearchGetURL` |
| `DATE_CHANGE` | Always `DD.MM.YYYY HH:MI:SS` (`d.m.Y H:i:s`). Or `LAST_MODIFIED` in site date format. One of them is required |
| `DATE_FROM`, `DATE_TO` | Site date format or `false`; applied when searching with `CHECK_DATES = 'Y'` |
| `PERMISSIONS` | `[2]` → `G2` (everyone, incl. anonymous); strings kept: `AU`, `U5`, `G12`, codes added by `OnSearchCheckPermissions` |
| `PARAM1`, `PARAM2` | Your filter keys (type, section). Also used by custom-rank rules |
| `PARAMS` | `['brand' => 'x', 'color' => ['red', 'blue']]`, values ≤ 100 chars, filterable |
| `USER_ID`, `ENTITY_TYPE_ID`, `ENTITY_ID`, `CUSTOM_RANK` | Stored columns |
| `INDEX_TITLE` | `false` keeps the item out of title search |

Other keys are dropped silently.

## Update semantics

- With `$bOverWrite = false` and an unchanged `DATE_CHANGE`, `Index()` updates only permissions/sites/params and skips title, body, tags. Pass `true` (or a new date) when content changes.
- Empty `PERMISSIONS` never clears rights on update. To change rights only: `CSearch::ChangePermission($MODULE_ID, $arGroups, $ITEM_ID)`; `[]` clears (then only admins find it). Don't pass its `$SITE_ID` argument — the query breaks.
- Metadata only (no text rebuild): `CSearch::ChangeIndex($MODULE_ID, $arFields, $ITEM_ID)`, `CSearch::ChangeSite($MODULE_ID, ['s1' => '/url/'], $ITEM_ID)`.
- Always send the full document: title and tag indexes read `SITE_ID` from the same call.
- search < 25.200 did not refresh the text of an existing item on the default, `mysql` and `pgsql` engines; there: `DeleteIndex()` then `Index()`.

## Delete

```php
CSearch::DeleteIndex('vendor.docs', (string)$id);          // one item
CSearch::DeleteIndex('vendor.docs', false, 'contract');    // by PARAM1
// DANGER: CSearch::DeleteIndex('vendor.docs') wipes the whole module index
```

Signature: `DeleteIndex($MODULE_ID, $ITEM_ID = false, $PARAM1 = false, $PARAM2 = false, $SITE_ID = false)`; `%` in `$ITEM_ID` becomes `LIKE`.

## Adjust other modules' items (`BeforeIndex`)

`search:BeforeIndex` receives `$arFields` (with `MODULE_ID`, `ITEM_ID`); return the modified array. Filter by module first:

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

Iblock items: `ITEM_ID` = element id or `S<id>` (section), `PARAM1` = iblock type, `PARAM2` = iblock id; indexed only when the iblock has `INDEX_ELEMENT`/`INDEX_SECTION = 'Y'`.

Debug a stored item: `CSearch::GetIndex($MODULE_ID, $ITEM_ID)`.
