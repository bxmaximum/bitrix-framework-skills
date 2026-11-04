# CRUD записей

```php
<?php declare(strict_types=1);

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;

Loader::includeModule('highloadblock');

$entity = HighloadBlockTable::compileEntity('ProductColor'); // массив, ID либо NAME; неизвестный блок — исключение
/** @var class-string<\Bitrix\Highloadblock\DataManager> $dataClass */
$dataClass = $entity->getDataClass();

$data = ['UF_NAME' => 'Красный', 'UF_CODE' => 'red', 'UF_TAGS' => ['a', 'b']];
foreach (array_keys($data) as $code) {
    $entity->hasField($code) or throw new \LogicException("Неизвестное поле $code"); // add() молча отбросил бы его
}
$add = $dataClass::add($data);
if (!$add->isSuccess()) { /* $add->getErrorMessages() */ }

$row = $dataClass::getRow(['select' => ['ID', 'UF_NAME'], 'filter' => ['=UF_CODE' => 'red']]); // array|null
$list = $dataClass::getList([
    'select' => ['ID', 'UF_NAME'], 'filter' => ['=UF_ACTIVE' => 1],
    'order' => ['ID' => 'ASC'], 'limit' => 20, 'count_total' => true,
]);
$total = $list->getCount();

if ($row !== null) {
    $update = $dataClass::update((int)$row['ID'], ['UF_NAME' => 'Тёмно-красный']); // только изменённые поля
    if (!$update->isSuccess()) { throw new \RuntimeException(implode('; ', $update->getErrorMessages())); }
    // ОПАСНО: удаление записи, множественных значений и файлов UF необратимо.
    $delete = $dataClass::delete((int)$row['ID']);
    if (!$delete->isSuccess()) { throw new \RuntimeException(implode('; ', $delete->getErrorMessages())); }
}
```

## Поведение API

- Неизвестные ключи `add()`/`update()` **молча отбрасываются**: опечатка теряет данные. Неизвестные поля `getList` в `select`/`filter` вызывают исключение.
- Проверки UF (обязательность, тип) выполняются в `add()`/`update()`, ошибки попадают в `Result`. `OnBefore*` выполняются раньше, результат их `modifyFields()` тоже проверяется.
- `update()` отсутствующего ID успешен (0 строк). `getAffectedRowsCount()` равен 0 и без изменения значений: сначала прочитай по ID. `delete()` отсутствующего ID даёт PHP warning и успех.
- `add()` не атомарен: вставляет пустую строку, обновляет значения, добавляет множественные. Для атомарности оберни в `Application::getConnection()->startTransaction()` / `commitTransaction()` / `rollbackTransaction()`. Удалённые файлы не восстанавливаются.
- Оптимистической блокировки нет: `update()` перезаписывает параллельные изменения. При необходимости блокируй в приложении.
- Объектный API: `fetchObject()`/`fetchCollection()`, `$obj->get('UF_NAME')`, `$obj->set(...)`, `$obj->save()`. Большой набор читай циклом `fetch()`; `fetchAll()` загружает целиком.

## Форматы значений

- Обновление множественного поля **заменяет** набор, `[]` очищает. Одиночное необязательное значение очищается через `null`.
- Фильтр одного множественного значения: `'=UF_TAGS_SINGLE' => 'a'`; JOIN может дублировать строки.
- Файл при чтении: ID (множественное — массив ID) → `\CFile::GetPath($id)`, `\CFile::GetByID($id)->Fetch()`.
- Файл при записи: `\CFile::MakeFileArray($path)`. Замена: добавь `'old_id' => $oldId`. Очистка: `['name' => '', 'type' => '', 'tmp_name' => '', 'size' => 0, 'error' => UPLOAD_ERR_NO_FILE, 'del' => true, 'old_id' => $oldId]`.
- Множественные файлы: массив с записью удаления каждого старого ID и `MakeFileArray()` каждого нового. Старые файлы удаляются до завершения операции, откат БД их не вернёт.

## Идемпотентный импорт

`getRow(['filter' => ['=UF_CODE' => $code]])` → `update()` либо `add()`. Безопасно только последовательно. Для параллельных запусков нужен уникальный индекс (MySQL хранит `string` как `TEXT`, см. `rules/performance.md`). Ключ импорта — стабильный внешний код, не числовой ID.
