# Records CRUD

```php
<?php declare(strict_types=1);

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;

Loader::includeModule('highloadblock');

$entity = HighloadBlockTable::compileEntity('ProductColor'); // array, ID or NAME; throws if unknown
/** @var class-string<\Bitrix\Highloadblock\DataManager> $dataClass */
$dataClass = $entity->getDataClass();

$data = ['UF_NAME' => 'Red', 'UF_CODE' => 'red', 'UF_TAGS' => ['a', 'b']];
foreach (array_keys($data) as $code) {
    $entity->hasField($code) or throw new \LogicException("Unknown field $code"); // add() would drop it silently
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
    $dataClass::update((int)$row['ID'], ['UF_NAME' => 'Dark red']); // pass only changed fields
    $dataClass::delete((int)$row['ID']);                            // also removes multiple values and UF files
}
```

## Behaviour to rely on

- Unknown keys in `add()`/`update()` are **dropped without error** (a typo = silent data loss). Unknown fields in `getList` `select`/`filter` do throw.
- UF checks (mandatory, type) run inside `add()`/`update()`; failures land in the `Result`. `OnBefore*` handlers run first, so their `modifyFields()` output is validated too.
- `update()` of a missing ID returns success (0 rows). `getAffectedRowsCount()` is also 0 when values did not change, so read by ID first. `delete()` of a missing ID emits a PHP warning and returns success.
- `add()` is not atomic: it inserts an empty row, then `UPDATE`s values, then inserts multiple values. For all-or-nothing wrap it in `Application::getConnection()->startTransaction()` / `commitTransaction()` / `rollbackTransaction()`. Deleted files are never restored.
- No optimistic locking: `update()` overwrites concurrent changes. Lock at application level if needed.
- Object API: `fetchObject()`/`fetchCollection()`, `$obj->get('UF_NAME')`, `$obj->set(...)`, `$obj->save()`. For big sets loop `fetch()`; `fetchAll()` loads everything.

## Value formats

- Multiple field: `update()` **replaces** the whole set; `[]` clears it. Clear a single optional value with `null`.
- Filter by one value of a multiple field: `'=UF_TAGS_SINGLE' => 'a'` (JOIN, may duplicate rows).
- File field, read: file ID (multiple → array of IDs) → `\CFile::GetPath($id)`, `\CFile::GetByID($id)->Fetch()`.
- File field, write: `\CFile::MakeFileArray($path)`. Replace: add `'old_id' => $oldId` to the new array. Clear: `['name' => '', 'type' => '', 'tmp_name' => '', 'size' => 0, 'error' => UPLOAD_ERR_NO_FILE, 'del' => true, 'old_id' => $oldId]`.
- Multiple files: one array with a clear entry for each old ID to drop plus `MakeFileArray()` for each new file. Old files are deleted before the operation finishes; a DB rollback does not bring them back.

## Idempotent import

`getRow(['filter' => ['=UF_CODE' => $code]])` → `update()` or `add()`. Safe for sequential runs only. For parallel safety add a unique index (on MySQL a `string` UF is `TEXT`, see `rules/performance.md`). Keep external codes stable; never key imports by numeric `ID`.
