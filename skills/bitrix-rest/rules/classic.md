# Engine-контроллеры и классические методы

База адреса: `CRestUtil::getEndpoint()` — сайт и опция `rest:rest_server_path` (по умолчанию `/rest`).

## Engine-контроллер как REST-метод

`/local/modules/vendor.module/.settings.php`:

```php
return [
    'controllers' => [
        'value' => [
            'defaultNamespace' => '\\Vendor\\Module\\Controller',
            'restIntegration' => [
                'enabled' => true,
                // 'hideModuleScope' => true, // не публиковать ID модуля как scope
                // 'scopes' => ['vendor.extra'], // дополнительные scopes модуля
            ],
        ],
        'readonly' => true,
    ],
];
```

`Bitrix\Rest\Engine\RestManager::onFindMethodDescription()` публикует действия только модулей с `restIntegration.enabled`. Для `lib/controller/post.php` имя действия в REST — `vendor.module.post.create`, в AJAX — `vendor:module.post.create`.

В `rest` 26.900.0 и 26.1300.0 штатный компонент `bitrix:rest.server` при прямом запросе сначала ищет метод по строчному имени. При `ERROR_METHOD_NOT_FOUND` он повторяет поиск с регистром из запроса. Для `lib/Controller/Post.php` передавай `vendor.module.Post.create` и проверь вызов на целевой версии `rest` с регистрозависимой ФС. Правила разрешения имён — в `bitrix-controllers`.

Ограничь действие REST либо исключи его из REST фильтром области:

```php
use Bitrix\Main\Engine\ActionFilter;

new ActionFilter\Scope(ActionFilter\Scope::REST);     // только REST
new ActionFilter\Scope(ActionFilter\Scope::NOT_REST); // только AJAX/CLI
```

Стандартный фильтр `Csrf` пропускается в области REST.

## Классическая карта

Регистрируй обработчик при установке, удаляй при деинсталляции (`bitrix-modules`):

```php
\Bitrix\Main\EventManager::getInstance()->registerEventHandler(
    'rest',
    'OnRestServiceBuildDescription',
    'vendor.module',
    \Vendor\Module\Rest\Service::class,
    'onRestServiceBuildDescription',
);
```

```php
namespace Vendor\Module\Rest;

final class Service
{
    public static function onRestServiceBuildDescription(): array
    {
        return [
            'vendor.module' => [ // scope
                'vendor.module.item.get' => [self::class, 'itemGet'],
                // \CRestUtil::EVENTS => [...], \CRestUtil::PLACEMENTS => [...]
            ],
        ];
    }

    public static function itemGet(array $query, int $start, \CRestServer $server): array
    {
        $id = (int)($query['id'] ?? 0);
        if ($id <= 0) {
            throw new \Bitrix\Rest\RestException('Требуется id', \Bitrix\Rest\RestException::ERROR_ARGUMENT);
        }

        return ['id' => $id];
    }
}
```

- Аргументы обработчика: параметры запроса, смещение `start`, сервер. Верхние ключи — scopes. `\CRestUtil::GLOBAL_SCOPE` (`_global`) не требует scope; используй только при обоснованной необходимости.
- `CRestProvider` собирает все обработчики события. Имена методов глобально уникальны; добавляй префикс ID модуля.
- Образец событий в карте: `main/lib/Rest/Handlers.php`.

## Scopes и встройки

- `Bitrix\Rest\Engine\ScopeManager` собирает scopes из ключей карты, `restIntegration` и V3 `rest`, кеширует на 7 дней. После добавления scope вызывай `ScopeManager::cleanCache()`, например в установщике.
- **Since rest 26.600** `placement.bind/unbind/get` доступны обычным пользователям при разрешённом доступе к приложению; `USER_ID` — только свой ID либо `0`. Наследник `Bitrix\Rest\Api\Placement` обязан сохранить `protected static function checkPermission(\CRestServer $server, ?string $permission = null)`, иначе фатальная ошибка. На старой версии выполняй регистрацию администратором.
- **Since rest 26.650** на облачном тарифе без возможности события локальных приложений и пользовательских вебхуков могут не отправляться, а авторизация отвечает `FEATURE_NOT_AVAILABLE_ON_CURRENT_PLAN` (403). Сообщи администратору; повтор не исправит тариф. На старой версии встречается `ACCESS_DENIED`.


## Своя точка входа REST

**Since rest 26.1000** `CRestServer::output()` возвращает `Main\HttpResponse|string|null`, а `outputJson()` — `Bitrix\Rest\Internal\Response\StreamedJsonResponse`. Старый наследник с `outputJson(): string` несовместим. Не выполняй `echo $server->output($data)`: у ответа нет строкового преобразования. Как в штатном `rest.server`, для `HttpResponse` вызывай `sendHeadersAdditional()` и `send()`, для строки — `sendHeaders()` и `echo`. На старом ядре сохраняй прежнюю строковую ветку.

Потоковый JSON не гарантирует `Content-Length`; `send()` завершает запрос и очищает буферы. Доработки, меняющие тело ответа через эпилог, перенеси в обработчик до отправки. Предпочитай штатную точку входа вместо собственной.
