# Uri и UuidGenerator

## Uri

Для чтения и пересборки URL предпочитай `Bitrix\Main\Web\Uri` комбинации `parse_url()` и склейки строк.

```php
use Bitrix\Main\Web\Uri;

$uri = new Uri('/company/personal/user/15/?tab=tasks');
$uri->addParams(['from' => 'invite', 'success' => 'Y']);
$url = (string)$uri;

// Сохранить имена параметров с точками/пробелами:
$uri->addParams(['a.b' => '1'], preserveDots: true);
$uri->deleteParams(['utm_source']);

$absolute = (new Uri('/path/'))->toAbsolute();
```

Также доступны `resolveRelativeUri()`, IDN через `convertToPunycode()` / `convertToUnicode()`, `isPathTraversal()` для пользовательских путей. Открытого `getQueryParams()` нет: бери `getQuery()` и `parse_str`, только если нужен массив. Для исходных данных `foo=1&bar=2` без частей URL можно обойтись без `Uri`.

## UuidGenerator

Для новых случайных непрозрачных идентификаторов (ID корреляции, токен загрузки, публичный ID прокси) используй `Bitrix\Main\UuidGenerator::generateV4()`, а не `uniqid()`, сборку из `random_bytes` или свой помощник.

```php
use Bitrix\Main\UuidGenerator;

$id = UuidGenerator::generateV4(); // нижний регистр, 36 символов, с дефисами
```

- Не подходит для детерминированных ID из предметных данных.
- Старые обёртки `{uuid}`: генерируй через `generateV4()`, оборачивай только на границе интеграции.
- Внешние UUID для поиска/доступа проверяй отдельно: генерация не означает доверие.
- `uniqid()` допустим только для локальных ID страницы/запроса вроде DOM, без требований криптостойкости/уникальности.

Хранение ключей с TTL, использующих UUID: `bitrix-storage`.
