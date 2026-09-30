# Requests

## Legacy mode

```php
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;

$http = new HttpClient(['socketTimeout' => 5, 'streamTimeout' => 15, 'headers' => ['User-Agent' => 'VendorBot/1.0']]);

$body = $http->get('https://api.example.com/items');
if ($body === false || $http->getStatus() !== 200)
{
    throw new \RuntimeException('HTTP error: ' . implode('; ', $http->getError()) . ' status ' . $http->getStatus());
}
$items = Json::decode($body);

$http->post('https://api.example.com/form', ['login' => $login]);                        // urlencoded form
$http->setHeader('Content-Type', 'application/json');
$http->setHeader('Authorization', 'Bearer ' . $token);
$http->post('https://api.example.com/users', Json::encode(['name' => 'Ivan']));         // raw body
$http->post($url, ['file' => ['resource' => $fh, 'filename' => 'a.pdf']], true);        // multipart
$http->download('https://files.example.com/r.csv', $_SERVER['DOCUMENT_ROOT'] . '/upload/tmp/r.csv');
```

- `getError()` is `[code => message]` (`NETWORK`, `REDIRECT`, `PRIVATE_IP`, `URI_SCHEME`, …). `getHeaders()` → `HttpHeaders`, `getCookies()` → `HttpCookies`, `getEffectiveUrl()` after redirects.
- Redirects are followed in legacy mode (`redirect`, `redirectMax` = 5); each hop is re-checked against `privateIp`.
- Headers set on the object stay for its next requests: use one client per API.
- Body only when needed: `$http->shouldFetchBody(fn (\Bitrix\Main\Web\Http\Response $r) => $r->getStatusCode() === 200)` (**Since main 23.300**; before: `head()` first).

## PSR-18 mode

```php
use Bitrix\Main\Web\Http\{Request, Method, Stream, ClientException, NetworkException};
use Bitrix\Main\Web\Uri;

$body = new Stream('php://temp', 'r+');
$body->write(Json::encode(['id' => 42]));
$body->rewind();

$request = new Request(
    Method::POST,
    new Uri('https://api.example.com/items'),
    ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $token],
    $body,
);

try
{
    $response = (new HttpClient(['useCurl' => true]))->sendRequest($request);
    $data = Json::decode((string)$response->getBody());
}
catch (NetworkException $e) { /* connect/timeout */ }
catch (ClientException $e) { /* bad request, private IP, ... */ }
```

- PSR-7 objects are immutable: `withHeader()`, `withUri()` return copies.
- No redirects: loop on `Location` yourself (and re-validate the target).
- Multipart: `new \Bitrix\Main\Web\Http\MultipartStream(['title' => 'x', 'file' => ['resource' => $fh, 'filename' => 'r.pdf', 'contentType' => 'application/pdf']])` with header `'multipart/form-data; boundary=' . $stream->getBoundary()`; `FormStream` for urlencoded.

## Async

```php
$http = new HttpClient(['useCurl' => true, 'socketTimeout' => 5, 'streamTimeout' => 10]);
$promises = [];
foreach ($urls as $key => $url)
{
    $promises[$key] = $http->sendAsyncRequest(new Request(Method::GET, new Uri($url)));
}

foreach ($promises as $key => $promise)
{
    try
    {
        $responses[$key] = $promise->wait();       // throws the ClientException of a failed request
    }
    catch (ClientException $e)
    {
        $errors[$key] = $e->getMessage();
    }
}
```

`$http->wait()` waits for the whole queue and returns responses keyed by promise ID. `$promise->then($onFulfilled, $onRejected)` registers callbacks. There is no `Promise::all()`.
