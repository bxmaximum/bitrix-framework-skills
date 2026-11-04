# GeoIP

Используй только `Bitrix\Main\Service\GeoIp\Manager`, без прямого обращения к классу обработчика.

| Задача | API | При отсутствии |
| --- | --- | --- |
| Один атрибут | `getCountryCode()`, `getCountryName()`, `getCityName()`, `getTimezoneName()`, … | `''` |
| Координаты | `getGeoPosition()` | `null` |
| Несколько полей и сведения об обработчике | `getDataResult($ip, $lang, $required)` | `null` |
| IP клиента | `getRealIp()` | |

```php
use Bitrix\Main\Service\GeoIp\Manager;

$result = Manager::getDataResult($storedIp, 'en', ['cityName', 'latitude']);
if ($result && $result->isSuccess())
{
    $geo = $result->getGeoData();        // GeoIp\Data: countryCode, cityName, latitude, timezone, …
}
```

- Пустой `$ip` означает текущий запрос (`getRealIp()`); сохранённые адреса или адреса за прокси передавай явно.
- `$required` (имена свойств `GeoIp\Data`) исключает обработчики, не умеющие их вернуть.
- `getRealIp()` берёт первый публичный IPv4 из присланного клиентом `X-Forwarded-For`: пригоден для геоподсказок, но не для контроля доступа и лимитов.
- Результаты хранятся в управляемом кеше (`geoip_manager`, TTL `cache_flags.geoip_manager`, по умолчанию неделя); сброс через `Manager::cleanCache()`.
- Свой провайдер: событие `main:onMainGeoIpHandlersBuildList` и наследник `GeoIp\Base`; последующая обработка результата — `main:onGeoIpGetResult`. ID логгера: `main.GeoIpManager`.
