---
name: bitrix-rest
description: 'Серверный REST своего PHP-модуля коробки: REST 3.0, Engine restIntegration, OnRestServiceBuildDescription, события и scopes. Для публикации методов; клиентские вызовы — bitrix24-rest-api.'
---

# Серверный REST (`rest`)

Baseline: main 23.0+ · Verified: rest 26.1100.0, main 26.1000.0

Перед PHP-кодом один раз прочитай `bitrix-framework`. Здесь публикуют методы своего модуля. Клиентские вызовы (вебхуки, OAuth, batch, лимиты, ошибки): `bitrix24-rest-api`.

| Вариант | Регистрация | Открыть |
| --- | --- | --- |
| REST 3.0 (`/rest/api/…`, DTO, JSON) | секция `rest` в `.settings.php` модуля | `rules/v3.md` |
| Идемпотентность V3, динамические перечисления, отложенный batch | атрибуты и очередь Messenger | `rules/v3-runtime.md` |
| Engine-контроллер, общий с AJAX | `.settings.php` → `controllers.restIntegration.enabled` | `rules/classic.md` |
| Классическая карта методов, REST-события и встройки | событие `rest:OnRestServiceBuildDescription` | `rules/classic.md` |

Новые API публикуй через REST 3.0 (**Since rest 26.200\***); на старом модуле используй Engine + `restIntegration`. Классическая карта нужна для событий, встроек и явных имён методов.

## Общие правила

- Подключи `Loader::includeModule('rest')`. Код размещай в установленном `/local/modules/<vendor.module>/`.
- REST не использует CSRF: запрос авторизует токен приложения или вебхука. В каждом действии проверяй права пользователя на данные.
- Scopes определяют разрешения приложения. Сохраняй стабильные имена и документируй их.
- Возвращай REST-ошибки: исключения V3, `addError` контроллера, `\Bitrix\Rest\RestException`. Не выводи HTML и не вызывай `die()`.
- Для собственных методов наследуй документированные базовые контроллеры. Не наследуй внутренние сервисы и не реализуй `Bitrix\Rest\Internal\*`: их сигнатуры меняются даже в промежуточном релизе (26.650: `Engine\Access`, `Api\User`, `IncomingWebhookRepositoryInterface`). Используй композицию.
- Блоки Landing и встройки через REST: `bitrix-landing`.
