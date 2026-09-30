# Responses

## HttpResponse

```php
use Bitrix\Main\HttpResponse;
use Bitrix\Main\Web\Json;

$response = new HttpResponse(); // no ctor args
$response->setStatus('201 Created');
$response->addHeader('Content-Type', 'application/json; charset=UTF-8');
$response->setContent(Json::encode(['ok' => true]));
return $response;
```

Also: `getStatus()`, `setHeaders(HttpHeaders)`, `getHeaders()`, `addCookie(Cookie $c, $replace = true, $checkExpires = true)`, `setLastModified(DateTime)`, `flush()`, `send()`. Outside a returned response (component, page) use `Context::getCurrent()->getResponse()`.

## `Bitrix\Main\Engine\Response\*`

| Need | Class |
| --- | --- |
| Controller data | return `array`/`null`: the engine wraps it in `AjaxJson` (`bitrix-controllers`) |
| JSON without envelope | `Json` |
| `status`/`data`/`errors` envelope by hand | `AjaxJson` |
| Redirect | `Redirect` / `$this->redirectTo()` |
| HTML page in the site template | `Render\View` / `Render\Component` / `Render\Extension` (**Since main 25.700**; controllers: `renderView()` …) |
| Component HTML + assets for AJAX | `Component` (`renderComponentAjax()`) |
| Files | `BFile`, `File`, `ResizedImage`, `Zip\Archive` |

There is no `Engine\Response\Html`; for a raw HTML string use `HttpResponse::setContent()`.

### JSON

```php
use Bitrix\Main\Engine\Response\AjaxJson;
use Bitrix\Main\Engine\Response\Json;
use Bitrix\Main\Error;
use Bitrix\Main\ErrorCollection;

return new Json(['id' => 42]);
return AjaxJson::createSuccess(['id' => 42]);
// {"status":"success","data":{"id":42},"errors":[]}
return AjaxJson::createError(new ErrorCollection([new Error('Forbidden', 'ACCESS_DENIED')]));
// {"status":"error","data":null,"errors":[{"message":"Forbidden","code":"ACCESS_DENIED","customData":null}]}
```

`createError()` / `createDenied()` take an `ErrorCollection`, not an `Error`.

### Redirect

```php
use Bitrix\Main\Engine\Response\Redirect;

$redirect = new Redirect('/auth/'); // ($url, bool $skipSecurity = false), status 302
$redirect->setStatus('301 Moved Permanently');
return $redirect;
```

`Redirect` strips CR/LF and makes relative URLs absolute. It does not block external hosts: that is done only by the `security` module's redirect protection, when enabled, and `skipSecurity: true` bypasses it. For a user-supplied `backurl` accept only local paths: starts with `/`, not with `//` (`bitrix-security`).

### Files

```php
use Bitrix\Main\Engine\Response\BFile;
use Bitrix\Main\Engine\Response\ResizedImage;
use Bitrix\Main\Engine\Response\Zip\Archive;
use Bitrix\Main\Engine\Response\Zip\EntryBuilder;

return BFile::createByFileId($fileId);                 // b_file record
return ResizedImage::createByImageId($fileId, 300, 300); // whitelist sizes, never raw request values

$archive = new Archive('report.zip');                  // needs nginx mod_zip
$entry = (new EntryBuilder())->createFromFileId($fileId);
if ($entry)
{
    $archive->addEntry($entry);                        // ArchiveEntry is deprecated
}
return $archive;
```

Check the user's right to the file before returning it.

## Cookies

```php
use Bitrix\Main\Context;
use Bitrix\Main\Web\CryptoCookie;

$cookie = new CryptoCookie('vendor_token', $token, time() + 86400); // name gets the site prefix
$cookie->setHttpOnly(true);
$cookie->setSecure(true);
$cookie->setSameSite('Lax');
Context::getCurrent()->getResponse()->addCookie($cookie);
```

- `CryptoCookie` encrypts the value. It needs `'crypto' => ['value' => ['crypto_key' => '…']]` in `.settings.php`, kept out of git. `$request->getCookie('vendor_token')` decrypts transparently.
- Plain `Web\Cookie` takes the same flags. Default spread is `SPREAD_DOMAIN | SPREAD_SITES`: the cookie is set for the matching domain from the sites' domain lists (its subdomains share it; host-only if none matches) and copied to the other sites' domains. `setSpread(Cookie::SPREAD_DOMAIN)` keeps it off other sites; without `SPREAD_DOMAIN` it is not set on the current domain at all.

## Converter

`Engine\Response\Converter` recases strings or array keys/values. Flags: `TO_SNAKE`, `TO_SNAKE_DIGIT`, `TO_CAMEL`, `TO_UPPER`, `TO_LOWER`, `LC_FIRST`, `UC_FIRST`, `KEYS`, `VALUES`, `RECURSIVE`.

```php
use Bitrix\Main\Engine\Response\Converter;

(new Converter(Converter::TO_CAMEL | Converter::LC_FIRST))->process('la_la_land'); // laLaLand
Converter::toJson()->process(['CATEGORIES' => [['ID' => 1, 'NAME' => 'Foods']]]);
// ['categories' => [['id' => 1, 'name' => 'Foods']]], values untouched
```

`Converter::toJson()` = `OUTPUT_JSON_FORMAT` (`KEYS | RECURSIVE | TO_CAMEL | LC_FIRST`). In controllers: `$this->convertKeysToCamelCase($data)`.
