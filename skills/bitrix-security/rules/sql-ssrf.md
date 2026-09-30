# SQL injection, SSRF, files

## Raw SQL

```php
$connection = \Bitrix\Main\Application::getConnection();
$helper = $connection->getSqlHelper();

$id = (int)$userId;
$login = $helper->forSql($userLogin);
$connection->queryExecute("UPDATE b_user SET LOGIN = '{$login}' WHERE ID = {$id}");

[$columns, $values] = $helper->prepareInsert('b_vendor_log', $fields);
$update = $helper->prepareUpdate('b_vendor_log', $fields); // [$setSql, $binds]
```

## ORM

- `select`, `order`, `group` and filter keys are field paths plus operators: taken from the request they reach any field or relation (`AUTHOR.PASSWORD`). Whitelist names and operators, never pass request keys through.

```php
$order = in_array($sort = strtoupper((string)$sort), ['ID', 'CREATED_AT', 'TITLE'], true) ? $sort : 'ID';
PostTable::getList(['order' => [$order => 'DESC']]);
```

- Filter values are parameterized. With a user filter and a rights filter, nest them (`[$userFilter, $rightsFilter]`) instead of `array_merge`, so `'LOGIC' => 'OR'` can't widen the result.
- `SqlExpression`: placeholders only. `?#` is an identifier, `?s` a string, `?i` an int, `?f` a float.

```php
new \Bitrix\Main\DB\SqlExpression('?# = ?s', $whitelistedColumn, $userValue);
```

- `ExpressionField` / `runtime`: the expression is inserted as is and goes through `sprintf`. Prefer concatenating in PHP. When a literal is unavoidable:

```php
use Bitrix\Main\ORM\Fields\ExpressionField;

$lit = str_replace('%', '%%', $helper->convertToDbString($separator)); // quoted + escaped, % doubled for sprintf
new ExpressionField('FULL_NAME', $helper->getConcatFunction('%s', $lit, '%s'), ['LAST_NAME', 'NAME']);
```

- Values read from options, the DB or imports are untrusted too: whitelist SQL function/operator names taken from them (e.g. an aggregate stored in report settings).
- Stored serialized data: `unserialize($s, ['allowed_classes' => false])`; prefer JSON.

## SSRF

```php
$client = new \Bitrix\Main\Web\HttpClient([
    'socketTimeout' => 5,
    'streamTimeout' => 10,
    'privateIp' => false, // default true = private/loopback/link-local allowed
]);
```

- With `privateIp => false` every redirect hop is re-checked. Also allow-list the scheme and host for user-supplied URLs; sign webhooks.
- `CFile::MakeFileArray()` takes b_file IDs, local paths (any readable file) and URLs: never pass user input (arbitrary file read + SSRF). Details: `bitrix-files` → `rules/storage.md`.
- User-supplied paths: `(new \Bitrix\Main\Web\Uri($path))->isPathTraversal()`, then check the directory prefix.

## Uploads

- Accept only file IDs this session uploaded (legacy `main.file.input`: `\Bitrix\Main\UI\FileInputUtility::instance()->checkFiles($controlId, $ids)`), otherwise any `b_file` id can be attached (IDOR).
- `ui.uploader` controllers check real rights in `canUpload()`/`canView()`/`canRemove()`/`verifyFileOwner()`; with presigned upload (ui 26.600+) re-validate the file after commit.
- Details: `bitrix-files` → `rules/uploads.md`, `rules/ui-uploader.md`.
