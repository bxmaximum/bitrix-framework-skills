# GeoIP

Always through `Bitrix\Main\Service\GeoIp\Manager`, never a handler class directly.

| Need | API | Miss |
| --- | --- | --- |
| One attribute | `getCountryCode()`, `getCountryName()`, `getCityName()`, `getTimezoneName()`, … | `''` |
| Coordinates | `getGeoPosition()` | `null` |
| Several fields, handler info | `getDataResult($ip, $lang, $required)` | `null` |
| Client IP | `getRealIp()` | |

```php
use Bitrix\Main\Service\GeoIp\Manager;

$result = Manager::getDataResult($storedIp, 'en', ['cityName', 'latitude']);
if ($result && $result->isSuccess())
{
    $geo = $result->getGeoData();        // GeoIp\Data: countryCode, cityName, latitude, timezone, …
}
```

- Empty `$ip` means the current request (`getRealIp()`); pass stored or proxied addresses explicitly.
- `$required` (names of `GeoIp\Data` properties) skips handlers that can't provide them.
- `getRealIp()` takes the first public IPv4 from `X-Forwarded-For` as sent by the client: fine for geo hints, never for access control or rate limits.
- Results are cached in managed cache (`geoip_manager`, TTL `cache_flags.geoip_manager`, default a week); reset with `Manager::cleanCache()`.
- Own provider: event `main:onMainGeoIpHandlersBuildList` + subclass of `GeoIp\Base`; post-process results in `main:onGeoIpGetResult`. Logger id: `main.GeoIpManager`.
