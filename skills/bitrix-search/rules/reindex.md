# Переиндексация

## Поддержка переиндекса своего модуля (`search:OnReindex`)

Административная переиндексация и `CSearch::ReIndexAll()` / `ReindexModule()` вызывают `OnReindex` с `($NS, $callback, 'Index')`. `MODULE_ID` берётся из `TO_MODULE_ID` обработчика: регистрируй **постоянно под ID своего модуля** в установщике. У `addEventHandler()` нет ID модуля, записи получат пустой `MODULE_ID`.

```php
// DoInstall() в install/index.php; при удалении unRegisterEventHandler()
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
        $item = ['ID' => (string)$row['ID']] + self::buildFields($row); // поля CSearch::Index()
        if (!call_user_func([$callback, $callbackMethod], $item))
        {
            return (string)$row['ID']; // время вышло: продолжить с этого ID на следующем шаге
        }
    }
    return false; // готово
}
```

- `$item['ID']` — `ITEM_ID`, остальные — поля `CSearch::Index()`.
- Обработчик обратного вызова возвращает `false` по истечении времени, уже после индексации текущей записи.
- Результат: `false` — конец; строка — последний ID для продолжения, только пошагово; массив записей — старый режим.
- Ключи `$NS`: `MODULE` (модуль шага), `ID` (последний ID), `SITE_ID`, `MODULE_ID`, `CNT`, `SESS_ID`, `CLEAR`.
- Эталон: `CIBlock::OnSearchReindex()`.
- Индексация выполняется с `$bOverWrite = false`: без изменения `DATE_CHANGE` остаётся старый текст, кроме полного запуска.

## Запуск

| Способ | Вызов |
| --- | --- |
| Административный интерфейс | `/bitrix/admin/search_reindex.php`, шаги, опция `max_execution_time`, по умолчанию 20 с |
| Один модуль | `CSearch::ReindexModule('vendor.docs', true)`: `true` сначала удаляет записи модуля |
| Всё | `CSearch::ReIndexAll(true, 0)`; **ОПАСНО:** `$bFull = true` сначала очищает весь индекс |
| По шагам | `$NS = CSearch::ReIndexAll(false, 20, $NS)` до возврата int (количества) |

Сигнатуры: `ReIndexAll($bFull = false, $max_execution_time = 0, $NS = [], $clear_suggest = false)` (массив — продолжить, int — готово); `ReindexModule($MODULE_ID, $bFull = false)`.

- **Since search 26.0** команда `php bitrix/bitrix.php search:reindex --step-duration=20` выполняет пошаговый переиндекс. `--full` сначала очищает индекс, `--continue` продолжает сохранённое состояние (срок сутки). Есть `--site`, `--module`, `--clear-suggest`; `--trace` выводит SQL, только для диагностики. На старом модуле оберни ReindexModule() своей командой. Агента переиндекса нет.
- Время callback-шага считается от `CSearchCallback::$startTime` (начально START_EXEC_TIME). Штатная search:reindex сбрасывает его на каждом шаге. Самодельный цикл без сброса после первого шага обрабатывает около одной записи за итерацию; используй штатную команду либо однократный ReIndexAll с временем 0.
- `ReIndexAll()` выполняется в транзакции БД; полный запуск большого сайта удерживает её до конца.
- Неполный `ReIndexAll()` удаляет записи, не затронутые текущей сессией (`DeleteOld`): обработчик должен выдать все живые записи.
- `ReindexModule()` с callback-обработчиком завершается сразу после него, старые записи не удаляет: используй `$bFull = true`.
- После смены движка, морфологии, `letters` выполни полный переиндекс.
