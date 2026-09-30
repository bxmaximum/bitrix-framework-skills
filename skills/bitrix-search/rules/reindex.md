# Reindex

## Make your module reindexable (`search:OnReindex`)

Admin "Reindex" and `CSearch::ReIndexAll()` / `ReindexModule()` call every `OnReindex` handler with `($NS, $callback, 'Index')`. The indexed `MODULE_ID` is the handler's `TO_MODULE_ID`, so register the handler **persistently with your module id** in the installer — a runtime `addEventHandler()` has no module id and items get an empty `MODULE_ID`.

```php
// install/index.php DoInstall(); mirror with unRegisterEventHandler() on uninstall
\Bitrix\Main\EventManager::getInstance()->registerEventHandlerCompatible(
    'search', 'OnReindex', 'vendor.docs', \Vendor\Docs\Search\Indexer::class, 'onReindex'
);
```

```php
public static function onReindex($NS = [], $callback = null, $callbackMethod = '')
{
    $lastId = (($NS['MODULE'] ?? '') === 'vendor.docs') ? (int)$NS['ID'] : 0;
    $rows = DocumentTable::getList([
        'filter' => ['>ID' => $lastId],
        'order' => ['ID' => 'ASC'],
    ]);
    while ($row = $rows->fetch())
    {
        $item = ['ID' => (string)$row['ID']] + self::buildFields($row); // same fields as CSearch::Index()
        if (!call_user_func([$callback, $callbackMethod], $item))
        {
            return (string)$row['ID']; // time is up: resume from here on the next step
        }
    }
    return false; // done
}
```

- `$item['ID']` is the `ITEM_ID`; the rest are `CSearch::Index()` fields.
- The callback returns `false` when the step time is over — after indexing the current item.
- Return: `false` = finished; string = last id (resume point, honoured only for stepped runs); array of items = legacy mode.
- `$NS` keys: `MODULE` (whose step it is), `ID` (your last id), `SITE_ID`, `MODULE_ID`, `CNT`, `SESS_ID`, `CLEAR`.
- Reference implementation: `CIBlock::OnSearchReindex()`.
- The callback indexes with `$bOverWrite = false`: items whose `DATE_CHANGE` didn't change keep old text unless the run is full.

## Run it

| Way | Call |
| --- | --- |
| Admin | `/bitrix/admin/search_reindex.php` (stepped, `max_execution_time` option, default 20 s) |
| One module | `CSearch::ReindexModule('vendor.docs', true)` — `true` deletes the module's items first |
| Everything | `CSearch::ReIndexAll(true, 0)` — **DANGER**: `$bFull = true` truncates the whole index first |
| Stepped | `$NS = CSearch::ReIndexAll(false, 20, $NS)` until it returns an int (count) |

Signatures: `ReIndexAll($bFull = false, $max_execution_time = 0, $NS = [], $clear_suggest = false)` (array = continue, int = done); `ReindexModule($MODULE_ID, $bFull = false)`.

- No console command or agent for reindex exists in the kernel. Wrap `ReindexModule()` in your own console command (`bitrix-console-commands`) or agent (`bitrix-background-jobs`).
- Step time counts from `START_EXEC_TIME` (request start). Run one step per request/process; in a loop inside one CLI process every later step indexes ~1 item. For CLI use `0` (one shot).
- A `ReIndexAll()` call runs in one DB transaction; a one-shot full run on a large site holds it for the whole run.
- Non-full `ReIndexAll()` deletes items not touched in this session (`DeleteOld`) — your handler must emit every live item.
- `ReindexModule()` with a callback-style handler returns right after the handler; stale items are not deleted — use `$bFull = true`.
- After switching the full-text engine, stemming or `letters` option, run a full reindex.
