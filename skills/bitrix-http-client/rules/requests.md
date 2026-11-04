# Запросы

## Старый режим

```php
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;

$http = new HttpClient(['socketTimeout' => 5, 'streamTimeout' => 15, 'headers' => ['User-Agent' => 'VendorBot/1.0']]);

$body = $http->get('https://api.example.com/items');
if ($body === false || $http->getStatus() !== 200)
{
    throw new \RuntimeException('Ошибка HTTP: ' . implode('; ', $http->getError()) . ' статус ' . $http->getStatus());
}
$items = Json::decode($body);

$http->post('https://api.example.com/form', ['login' => $login]);                        // форма urlencoded
$http->setHeader('Content-Type', 'application/json');
$http->setHeader('Authorization', 'Bearer ' . $token);
$http->post('https://api.example.com/users', Json::encode(['name' => 'Ivan']));         // тело без преобразования
$http->post($url, ['file' => ['resource' => $fh, 'filename' => 'a.pdf']], true);        // multipart
$http->download('https://files.example.com/r.csv', $_SERVER['DOCUMENT_ROOT'] . '/upload/tmp/r.csv');
```

- `getError()` — массив `[code => message]` (`NETWORK`, `REDIRECT`, `PRIVATE_IP`, `URI_SCHEME`, …). `getHeaders()` возвращает `HttpHeaders`, `getCookies()` — `HttpCookies`, `getEffectiveUrl()` — URL после переходов.
- В старом режиме переходы выполняются автоматически (`redirect`, `redirectMax` = 5); каждый адрес повторно проверяется по `privateIp`.
- Заданные заголовки сохраняются для следующих запросов объекта: выделяй отдельный клиент каждому API.
- Чтение тела по условию: `$http->shouldFetchBody(fn (\Bitrix\Main\Web\Http\Response $r) => $r->getStatusCode() === 200)` (**Since main 23.300**; раньше сначала `head()`).

## Режим PSR-18

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
catch (NetworkException $e) { /* подключение/таймаут */ }
catch (ClientException $e) { /* неверный запрос, частный IP и т. п. */ }
```

- Объекты PSR-7 неизменяемы: `withHeader()`, `withUri()` возвращают копии.
- Автоматических переходов нет: обрабатывай `Location` циклом и повторно проверяй адрес.
- Multipart: `new \Bitrix\Main\Web\Http\MultipartStream(['title' => 'x', 'file' => ['resource' => $fh, 'filename' => 'r.pdf', 'contentType' => 'application/pdf']])` с заголовком `'multipart/form-data; boundary=' . $stream->getBoundary()`; для urlencoded — `FormStream`.

## Асинхронные запросы

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
        $responses[$key] = $promise->wait();       // выбрасывает ClientException неуспешного запроса
    }
    catch (ClientException $e)
    {
        $errors[$key] = $e->getMessage();
    }
}
```

`$http->wait()` ждёт всю очередь и возвращает ответы с ключами ID обещаний. `$promise->then($onFulfilled, $onRejected)` регистрирует обработчики. Метода `Promise::all()` нет. <!-- verify:negative -->
