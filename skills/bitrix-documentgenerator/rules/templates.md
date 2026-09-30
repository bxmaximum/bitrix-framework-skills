# Templates and placeholders

A template is a DOCX file (`Body\Docx`, default) or plain text (`Body\PlainText`) with `{Placeholder~modifiers}` tokens, bound to a module (`MODULE_ID`) and to one or more providers.

## Placeholders

- Canonical form is CamelCase of the field chain of the source provider: `NUMBER` → `{Number}`, `CLIENT.NAME` → `{ClientName}`, `ITEMS.ITEM.NAME` → `{ItemsItemName}` (`DataProviderManager::valueToPlaceholder()`). Document fields: `{DocumentNumber}`, `{DocumentTitle}`, `{DocumentCreateTime}`.
- The dotted form `{SOURCE.NUMBER}` also resolves (`SOURCE` = `Template::MAIN_PROVIDER_PLACEHOLDER`, `DOCUMENT` = `Template::DOCUMENT_PROVIDER_PLACEHOLDER`).
- Placeholder list of a provider: `DataProviderManager::getInstance()->getProviderPlaceholders(OrderProvider::class)`; UI catalog: component `bitrix:documentgenerator.placeholders`.
- Since documentgenerator 26.200 DOCX placeholders are replaced only in text runs (`w:t`) and in picture alt text/name. Placeholders in field codes or other attributes stay as literal `{...}`.

## Modifiers (`{Name~key=value,key2=value2}`)

| Modifier | Applies to |
| --- | --- |
| `format=d.m.Y` or bare `~d.m.Y` | dates; passed to PHP-style `FormatDate()` (not `DD.MM.YYYY`) |
| `index=N`, `all` | array fields: one item / all items |
| `letterCase=lower\|upper\|title` | strings |
| `mseparator=1\|2`, `mfirst=Y` | multiple values |
| `format=…`, `case=…` | person names |

`Y`/`N` values become booleans.

## Lists and tables

- A DOCX table row that contains item placeholders (`{ItemsItemName}`) is repeated for every item — no markers needed.
- Other repeated blocks: wrap them in `{ITEMS.BLOCK_START}` … `{ITEMS.BLOCK_END}` using the array field code without `SOURCE.` (end marker ≤ 20 sibling nodes after start).
- `ArrayDataProvider` adds `INDEX` (1-based row number) and `NUMBER` (item count).

## Images

Put the placeholder in the picture's alt text (description) or name in Word. The value must be a path under `/upload` (e.g. `\CFile::GetPath($fileId)`), a temp-file path or an http(s) URL — a raw `b_file` id does not work. Field `TYPE`: `DataProvider::FIELD_TYPE_IMAGE` / `FIELD_TYPE_STAMP`.

## Creating a template from code

There is no service API; the kernel's own template controller writes the tables. Needs the DOCX file, your installed module id and a registered provider (`rules/providers.md`).

```php
$file = \CFile::MakeFileArray($absPathToDocx);
$file['isTemplate'] = true;                          // Disk storage: templates folder
$fileResult = \Bitrix\DocumentGenerator\Model\FileTable::saveFile($file);   // default storage
if (!$fileResult->isSuccess()) { return $fileResult; }

$tplResult = \Bitrix\DocumentGenerator\Model\TemplateTable::add([
    'NAME' => 'Order invoice',                       // 1–100 chars
    'MODULE_ID' => 'vendor.orders',                  // must match the provider's MODULE
    'FILE_ID' => $fileResult->getId(),               // b_documentgenerator_file.ID
    'BODY_TYPE' => \Bitrix\DocumentGenerator\Body\Docx::class,
    'REGION' => 'ru',
    'NUMERATOR_ID' => $numeratorId,                  // optional, see rules/generation.md
]);
if (!$tplResult->isSuccess()) { return $tplResult; }
$templateId = $tplResult->getId();

\Bitrix\DocumentGenerator\Model\TemplateProviderTable::add(['TEMPLATE_ID' => $templateId, 'PROVIDER' => OrderProvider::class]);
\Bitrix\DocumentGenerator\Model\TemplateUserTable::add([
    'TEMPLATE_ID' => $templateId,
    'ACCESS_CODE' => \Bitrix\DocumentGenerator\Model\TemplateUserTable::ALL_USERS,
]);
```

- `TemplateUserTable` rows (`UA` = all users, or `U<id>`, `D<id>`, …) decide who sees the template in lists.
- On add the kernel normalizes the DOCX (merges placeholders split across Word runs) and enforces the file-size limit.
- `TemplateTable::delete($id)` only soft-deletes (`IS_DELETED = 'Y'`) when documents exist; documents of a deleted template can't be updated.
- Read: `Template::loadById($id)` (no rights check), `$template->isDeleted()`, `$template->getFields()`.
