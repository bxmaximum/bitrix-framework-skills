# Ответы

## HttpResponse

```php
use Bitrix\Main\HttpResponse;
use Bitrix\Main\Web\Json;

$response = new HttpResponse(); // без аргументов конструктора
$response->setStatus('201 Created');
$response->addHeader('Content-Type', 'application/json; charset=UTF-8');
$response->setContent(Json::encode(['ok' => true]));
return $response;
```

Также доступны `getStatus()`, `setHeaders(HttpHeaders)`, `getHeaders()`, `addCookie(Cookie $c, $replace = true, $checkExpires = true)`, `setLastModified(DateTime)`, `flush()`, `send()`. В компоненте/странице, где ответ не возвращается, используй `Context::getCurrent()->getResponse()`.

## `Bitrix\Main\Engine\Response\*`

| Задача | Класс |
| --- | --- |
| Данные контроллера | Верни `array`/`null`: движок обернёт в `AjaxJson` (`bitrix-controllers`) |
| JSON без обёртки | `Json` |
| Обёртка `status`/`data`/`errors` вручную | `AjaxJson` |
| Перенаправление | `Redirect` / `$this->redirectTo()` |
| HTML-страница внутри шаблона сайта | `Render\View` / `Render\Component` / `Render\Extension` (**Since main 25.700**; контроллеры: `renderView()` …) |
| HTML компонента и ресурсы для AJAX | `Component` (`renderComponentAjax()`) |
| Файлы | `BFile`, `File`, `ResizedImage`, `Zip\Archive` |

Класса `Engine\Response\Html` нет; для строки HTML используй `HttpResponse::setContent()`.

### JSON

```php
use Bitrix\Main\Engine\Response\AjaxJson;
use Bitrix\Main\Engine\Response\Json;
use Bitrix\Main\Error;
use Bitrix\Main\ErrorCollection;

return new Json(['id' => 42]);
return AjaxJson::createSuccess(['id' => 42]);
// {"status":"success","data":{"id":42},"errors":[]}
return AjaxJson::createError(new ErrorCollection([new Error('Доступ запрещён', 'ACCESS_DENIED')]));
// {"status":"error","data":null,"errors":[{"message":"Доступ запрещён","code":"ACCESS_DENIED","customData":null}]}
```

`createError()` / `createDenied()` принимают `ErrorCollection`, не `Error`.

### Перенаправление

```php
use Bitrix\Main\Engine\Response\Redirect;

$redirect = new Redirect('/auth/'); // ($url, bool $skipSecurity = false), статус 302
$redirect->setStatus('301 Moved Permanently');
return $redirect;
```

`Redirect` удаляет CR/LF и делает относительные URL абсолютными. Внешние хосты не блокирует: это делает включённая защита перенаправлений модуля `security`, а `skipSecurity: true` обходит её. В пользовательском `backurl` разрешай только локальные пути: начало `/`, но не `//` (`bitrix-security`).

### Файлы

```php
use Bitrix\Main\Engine\Response\BFile;
use Bitrix\Main\Engine\Response\ResizedImage;
use Bitrix\Main\Engine\Response\Zip\Archive;
use Bitrix\Main\Engine\Response\Zip\EntryBuilder;

return BFile::createByFileId($fileId);                 // запись b_file
return ResizedImage::createByImageId($fileId, 300, 300); // размеры из разрешённого списка, не из запроса

$archive = new Archive('report.zip');                  // нужен nginx mod_zip
$entry = (new EntryBuilder())->createFromFileId($fileId);
if ($entry)
{
    $archive->addEntry($entry);                        // ArchiveEntry устарел
}
return $archive;
```

Перед возвратом файла проверяй право пользователя на него.

## Cookies

```php
use Bitrix\Main\Context;
use Bitrix\Main\Web\CryptoCookie;

$cookie = new CryptoCookie('vendor_token', $token, time() + 86400); // имя получает префикс сайта
$cookie->setHttpOnly(true);
$cookie->setSecure(true);
$cookie->setSameSite('Lax');
Context::getCurrent()->getResponse()->addCookie($cookie);
```

- `CryptoCookie` шифрует значение. Нужна секция `'crypto' => ['value' => ['crypto_key' => '…']]` в `.settings.php`, вне git. `$request->getCookie('vendor_token')` расшифровывает автоматически.
- Обычный `Web\Cookie` принимает те же флаги. По умолчанию `SPREAD_DOMAIN | SPREAD_SITES`: cookie ставится для подходящего домена из списков доменов сайтов (общий для поддоменов; без совпадения — только текущий хост) и копируется на домены других сайтов. `setSpread(Cookie::SPREAD_DOMAIN)` отключает копирование другим сайтам; без `SPREAD_DOMAIN` cookie вообще не ставится на текущем домене.

Совместимость encrypted cookies v2 и сброс разделённой сессии при обновлении main 26.900: `bitrix-sessions`. Формат шифрования не реализуй самостоятельно.

## Converter

`Engine\Response\Converter` меняет регистр строк или ключей/значений массивов. Флаги: `TO_SNAKE`, `TO_SNAKE_DIGIT`, `TO_CAMEL`, `TO_UPPER`, `TO_LOWER`, `LC_FIRST`, `UC_FIRST`, `KEYS`, `VALUES`, `RECURSIVE`.

```php
use Bitrix\Main\Engine\Response\Converter;

(new Converter(Converter::TO_CAMEL | Converter::LC_FIRST))->process('la_la_land'); // laLaLand
Converter::toJson()->process(['CATEGORIES' => [['ID' => 1, 'NAME' => 'Foods']]]);
// ['categories' => [['id' => 1, 'name' => 'Foods']]], значения не меняются
```

`Converter::toJson()` — `OUTPUT_JSON_FORMAT` (`KEYS | RECURSIVE | TO_CAMEL | LC_FIRST`). В контроллерах: `$this->convertKeysToCamelCase($data)`.
