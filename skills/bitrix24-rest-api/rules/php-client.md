# Минимальный REST-клиент внутри коробки

Перед этим PHP-кодом один раз прочитай `bitrix-framework`, если ещё не читал. URL вебхука бери из env; полный API клиента — `bitrix-http-client`.

```php
<?php declare(strict_types=1);

namespace Vendor\Integration\Bitrix24;

use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;

final class RestClient
{
    private const RETRYABLE = ['QUERY_LIMIT_EXCEEDED'];

    public function __construct(private readonly string $baseUrl) // getenv('B24_WEBHOOK_URL'), адрес заканчивается '/'
    {
        if (parse_url($baseUrl, PHP_URL_SCHEME) !== 'https' || !parse_url($baseUrl, PHP_URL_HOST))
        {
            throw new \InvalidArgumentException('Нужен базовый HTTPS URL');
        }
    }

    public function call(string $method, array $params = [], int $attempt = 0): array
    {
        $http = new HttpClient(['socketTimeout' => 10, 'streamTimeout' => 60, 'redirect' => false]);
        $http->setHeader('Content-Type', 'application/json');
        $http->setHeader('Accept', 'application/json');

        $raw = $http->post($this->baseUrl . $method, Json::encode($params));
        $data = is_string($raw) && $raw !== '' ? Json::decode($raw) : null;

        if (!is_array($data))
        {
            $transport = implode('; ', $http->getError()); // пусто, если ответ пришёл, но это не JSON
            throw new \RuntimeException('HTTP ' . $http->getStatus() . ' from ' . $method . ($transport !== '' ? ': ' . $transport : ''));
        }

        if (array_key_exists('error', $data))
        {
            $code = (string)$data['error'];
            if (in_array($code, self::RETRYABLE, true) && $attempt < 5)
            {
                usleep((2 ** $attempt) * 500_000 + random_int(0, 250_000));

                return $this->call($method, $params, $attempt + 1);
            }

            throw new \RuntimeException($method . ': ' . $code . ' ' . ($data['error_description'] ?? ''));
        }

        if ($http->getStatus() < 200 || $http->getStatus() >= 300)
        {
            throw new \RuntimeException('HTTP ' . $http->getStatus() . ' from ' . $method);
        }

        return $data;
    }
}
```

`Json::decode()` бросает `Bitrix\Main\ArgumentException` на не-JSON, например странице ошибки прокси. Обрабатывай вместе с транспортными ошибками. Длинный экспорт переноси в агент/очередь (`bitrix-background-jobs`).
