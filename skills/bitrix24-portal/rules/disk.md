# Диск для интеграций

Подключи `\Bitrix\Main\Loader::requireModule('disk')`. Точка входа: `\Bitrix\Disk\Driver::getInstance()`. Примеры добавляют ошибки в существующий `$result` (`Bitrix\Main\Result`).

## Хранилища

```php
$driver = \Bitrix\Disk\Driver::getInstance();
$storage = $driver->getStorageByUserId($userId)        // ?Storage; null, если ещё не создано
    ?? $driver->addUserStorage($userId);               // нужен socialnetwork
$groupStorage = $driver->getStorageByGroupId($groupId); // диск группы/проекта
$common = $driver->getStorageByCommonId('shared_files_s1'); // общий диск сайта s1
```

## Загрузка файла

```php
$securityContext = $storage->getSecurityContext($userId);
$folder = $storage->getFolderForUploadedFiles();       // «Загруженные файлы»; либо $storage->getRootObject()
if (!$folder || !$folder->canAdd($securityContext))
{
    return $result->addError(new \Bitrix\Main\Error('Доступ запрещён'));
}

$file = $folder->uploadFile(
    \CFile::MakeFileArray($localPath),                  // массив формата $_FILES
    ['NAME' => 'report.pdf', 'CREATED_BY' => $userId],  // CREATED_BY обязателен
    [],                                                 // дополнительные права: RightsManager
    true,                                               // уникальное имя при совпадении
);
if (!$file)
{
    return $result->addErrors($folder->getErrors());
}
$diskFileId = $file->getId();       // ID объекта Диска
$bFileId = $file->getFileId();      // ID в b_file
```

- `uploadFile()` не проверяет права: сначала `canAdd()`.
- `$storage->uploadFile()` загружает в корневую папку.
- Чтение: `\Bitrix\Disk\File::loadById($id)`, затем `$file->canRead($storage->getSecurityContext($userId))`. Для скачивания отдельно проверяй `$file->canDownload($storage->getSecurityContext($userId))`: право чтения не гарантирует скачивание (**Since disk 26.1300**). На старом модуле проверяй доступный ему набор прав.
- Ссылки: `$driver->getUrlManager()->getUrlForDownloadFile($file)`, `getPathFileDetail($file)`.

## Прикрепление к своей сущности: UF `disk_file`

Создай поле с `USER_TYPE_ID => 'disk_file'` (`\Bitrix\Disk\Uf\FileUserType::USER_TYPE_ID`), обычно `MULTIPLE => 'Y'`, у своей сущности `ENTITY_ID`. Значения:

| Значение | Значение операции |
| --- | --- |
| `'n' . $diskFileId` | прикрепить файл Диска, создавая `AttachedObject` |
| `$attachedObjectId` (int) | сохранить существующую привязку |

```php
global $USER_FIELD_MANAGER, $APPLICATION;
$fields = ['UF_VENDOR_FILES' => ['n' . $diskFileId]];
if (!$USER_FIELD_MANAGER->CheckFields('VENDOR_REPORT', $reportId, $fields, $userId))
{
    return $result->addError(new \Bitrix\Main\Error($APPLICATION->GetException()->GetString()));
}
$USER_FIELD_MANAGER->Update('VENDOR_REPORT', $reportId, $fields, $userId);
```

- Только `CheckFields()` проверяет чтение файла пользователем; `Update()` создаёт привязку без этой проверки. В агенте/CLI передавай `$userId`: `false` означает текущего пользователя.
- Значения, отсутствующие в новом массиве, открепляются. При изменении передавай полный список.
- Обратное чтение: `$driver->getUserFieldManager()->getAttachedObjectById($id)?->getFile()`.

## Доступ к вложениям

`ENTITY_ID` пользовательского поля определяет класс коннектора. Неизвестная сущность получает `Bitrix\Disk\Uf\StubConnector`: действуют собственные права файла. Поэтому читатель сущности может не видеть её файлов. Для доступа по правам сущности обрабатывай `disk:onBuildAdditionalConnectorList`:

```php
public static function onBuildAdditionalConnectorList(): \Bitrix\Main\EventResult
{
    return new \Bitrix\Main\EventResult(\Bitrix\Main\EventResult::SUCCESS, [[
        'ENTITY_TYPE' => 'vendor_report',        // ENTITY_ID поля, без учёта регистра
        'MODULE_ID' => 'vendor.reports',
        'CLASS' => ReportConnector::class,       // наследник \Bitrix\Disk\Uf\Connector
    ]]);
}
```

Коннектор получает ID сущности в конструкторе. Переопредели `canRead($userId)` и `canUpdate($userId)` (базовые возвращают `false`) с проверкой прав своей сущности.
