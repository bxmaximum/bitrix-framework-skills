# Disk for integrations

Load `\Bitrix\Main\Loader::requireModule('disk')`. Entry point: `\Bitrix\Disk\Driver::getInstance()`. Snippets return errors into an existing `$result` (`Bitrix\Main\Result`).

## Storages

```php
$driver = \Bitrix\Disk\Driver::getInstance();
$storage = $driver->getStorageByUserId($userId)        // ?Storage, null if not created yet
    ?? $driver->addUserStorage($userId);               // needs socialnetwork
$groupStorage = $driver->getStorageByGroupId($groupId); // workgroup/project drive
$common = $driver->getStorageByCommonId('shared_files_s1'); // "Company drive" of site s1
```

## Upload a file

```php
$securityContext = $storage->getSecurityContext($userId);
$folder = $storage->getFolderForUploadedFiles();       // "Uploaded files"; or $storage->getRootObject()
if (!$folder || !$folder->canAdd($securityContext))
{
    return $result->addError(new \Bitrix\Main\Error('Access denied'));
}

$file = $folder->uploadFile(
    \CFile::MakeFileArray($localPath),                  // $_FILES-like array
    ['NAME' => 'report.pdf', 'CREATED_BY' => $userId],  // CREATED_BY is required
    [],                                                 // extra rights, see RightsManager
    true,                                               // generate unique name on conflict
);
if (!$file)
{
    return $result->addErrors($folder->getErrors());
}
$diskFileId = $file->getId();       // disk object id
$bFileId = $file->getFileId();      // b_file id
```

- `uploadFile()` does not check rights — call `canAdd()` first.
- `$storage->uploadFile()` is the same call on the root folder.
- Read: `\Bitrix\Disk\File::loadById($id)`, then `$file->canRead($storage->getSecurityContext($userId))`.
- Links: `$driver->getUrlManager()->getUrlForDownloadFile($file)`, `getPathFileDetail($file)`.

## Attach disk files to your entity (UF `disk_file`)

Create a user field with `USER_TYPE_ID => 'disk_file'` (`\Bitrix\Disk\Uf\FileUserType::USER_TYPE_ID`), usually `MULTIPLE => 'Y'`, on your entity id (`ENTITY_ID`). Values:

| Value | Meaning |
| --- | --- |
| `'n' . $diskFileId` | attach a disk file (creates an `AttachedObject`) |
| `$attachedObjectId` (int) | keep an already attached object |

```php
global $USER_FIELD_MANAGER, $APPLICATION;
$fields = ['UF_VENDOR_FILES' => ['n' . $diskFileId]];
if (!$USER_FIELD_MANAGER->CheckFields('VENDOR_REPORT', $reportId, $fields, $userId))
{
    return $result->addError(new \Bitrix\Main\Error($APPLICATION->GetException()->GetString()));
}
$USER_FIELD_MANAGER->Update('VENDOR_REPORT', $reportId, $fields, $userId);
```

- Only `CheckFields()` verifies that the user can read the disk file; `Update()` attaches without that check. Pass `$userId` in agents/CLI — `false` means the current user.
- Values missing from the new array are detached. Send the full list on update.
- Read back: `$driver->getUserFieldManager()->getAttachedObjectById($id)?->getFile()`.

## Who can see attachments

The UF `ENTITY_ID` maps to a connector class. Unknown entity types get `Bitrix\Disk\Uf\StubConnector`: access follows the file's own disk rights, so users who can see your entity may not see its files. For entity-based access, handle `disk:onBuildAdditionalConnectorList`:

```php
public static function onBuildAdditionalConnectorList(): \Bitrix\Main\EventResult
{
    return new \Bitrix\Main\EventResult(\Bitrix\Main\EventResult::SUCCESS, [[
        'ENTITY_TYPE' => 'vendor_report',        // UF ENTITY_ID, case-insensitive
        'MODULE_ID' => 'vendor.reports',
        'CLASS' => ReportConnector::class,       // extends \Bitrix\Disk\Uf\Connector
    ]]);
}
```

The connector gets the entity id in the constructor; override `canRead($userId)` and `canUpdate($userId)` (base returns `false`) and check your entity rights there.
