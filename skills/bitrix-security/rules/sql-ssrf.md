# SQL-инъекции, SSRF и файлы

## Прямой SQL

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

- `select`, `order`, `group` и ключи фильтра — пути полей с операторами: из запроса позволяют обратиться к любому полю/связи (`AUTHOR.PASSWORD`). Проверяй имена и операторы по разрешённому списку, не передавай ключи запроса напрямую.

```php
$order = in_array($sort = strtoupper((string)$sort), ['ID', 'CREATED_AT', 'TITLE'], true) ? $sort : 'ID';
PostTable::getList(['order' => [$order => 'DESC']]);
```

- Значения фильтров подставляются безопасно. Пользовательский фильтр и фильтр прав вкладывай раздельно (`[$userFilter, $rightsFilter]`), не через `array_merge`, чтобы `'LOGIC' => 'OR'` не расширял результат.
- `SqlExpression`: только плейсхолдеры. `?#` — идентификатор, `?s` — строка, `?i` — int, `?f` — float.

```php
new \Bitrix\Main\DB\SqlExpression('?# = ?s', $whitelistedColumn, $userValue);
```

- `ExpressionField` / `runtime`: выражение вставляется как есть и проходит `sprintf`. Предпочитай склейку в PHP. Если литерал необходим:

```php
use Bitrix\Main\ORM\Fields\ExpressionField;

$lit = str_replace('%', '%%', $helper->convertToDbString($separator)); // кавычки, экранирование и удвоение % для sprintf
new ExpressionField('FULL_NAME', $helper->getConcatFunction('%s', $lit, '%s'), ['LAST_NAME', 'NAME']);
```

- Значения из настроек, БД и импортов тоже недоверенные: имена SQL-функций/операторов проверяй по списку, например агрегат из настроек отчёта.
- Сериализованные данные: `unserialize($s, ['allowed_classes' => false])`; предпочитай JSON.

## SSRF

```php
$client = new \Bitrix\Main\Web\HttpClient([
    'socketTimeout' => 5,
    'streamTimeout' => 10,
    'privateIp' => false, // по умолчанию true разрешает частные/локальные/link-local адреса
]);
```

- С `privateIp => false` проверяется каждый переход. Для пользовательских URL также ограничивай схему и хост, вебхуки подписывай.
- `CFile::MakeFileArray()` принимает ID b_file, локальные пути (любые читаемые файлы) и URL. Не передавай пользовательский ввод: чтение чужих файлов и SSRF. Подробнее: `bitrix-files` → `rules/storage.md`.
- Пути от пользователя: `(new \Bitrix\Main\Web\Uri($path))->isPathTraversal()`, затем проверка префикса каталога.

## Загрузки

- Принимай только ID файлов, загруженных этой сессией (старый `main.file.input`: `\Bitrix\Main\UI\FileInputUtility::instance()->checkFiles($controlId, $ids)`), иначе можно прикрепить любой `b_file` (IDOR).
- Контроллеры `ui.uploader` проверяют права в `canUpload()`/`canView()`/`canRemove()`/`verifyFileOwner()`; при presigned-загрузке (ui 26.600+) повторно проверяй файл после фиксации.
- Подробнее: `bitrix-files` → `rules/uploads.md`, `rules/ui-uploader.md`.
