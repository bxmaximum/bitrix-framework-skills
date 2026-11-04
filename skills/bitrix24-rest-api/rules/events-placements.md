# События и встройки

## Подписка

| Вариант | Настройка | События |
| --- | --- | --- |
| Приложение | `event.bind`, только OAuth; вебхук получает `WRONG_AUTH_TYPE` | все, включая офлайн |
| Исходящий вебхук | события и URL обработчика в «Ресурсах разработчика» | онлайн-события списка интерфейса |

```json
{"event": "ONCRMDEALUPDATE", "handler": "https://app.example.com/b24/events", "auth": "…"}
```

- Регистрируй обработчики при установке каждой версии: обновление/удаление приложения снимает их.
- `auth_type` задаёт ID пользователя выдаваемых токенов; обычный пользователь может регистрировать только себя. Для офлайн-событий нужен администратор.
- Доставка начинается после завершения установки.
- URL обработчика — публичный HTTPS, без `localhost`, частных адресов и самоподписанных сертификатов.

## Входящие данные

POST `application/x-www-form-urlencoded`: `event`, `event_handler_id`, `data` (обычно `data[FIELDS][ID]`), `ts`, `auth`.

- У приложения `auth` содержит `access_token`, `expires_in`, `scope`, `domain`, `client_endpoint`, `server_endpoint`, `member_id`, `application_token`. `refresh_token` приходит только в долгоживущих событиях, например `ONAPPINSTALL`, `onOfflineEvent`.
- У исходящего вебхука в `auth` только `domain`, `client_endpoint`, `server_endpoint`, `member_id`, `application_token`.
- При изменении из робота, БП или агента токенов может не быть. Используй сохранённые при установке.
- Событие сообщает об изменении, а не гарантирует текущее состояние. Перед действием перечитай объект (`crm.item.get` …).

## Обработчик

- Сохраняй `application_token` по `member_id` на `ONAPPINSTALL`, заменяй на `ONAPPUPDATE`. Для исходящего вебхука бери значение из его формы.
- Сравнивай через `hash_equals()`. При несовпадении отвечай 403 и прекращай обработку. Для `ONAPPUNINSTALL` это единственная доступная проверка: токенов доступа нет.
- Штатная доставка не гарантирует повтор при отказе/задержке обработчика. Сохрани данные, ответь 200 и обработай очередью (`bitrix-background-jobs`); медленный обработчик замедляет вызовы очереди.
- Массовое изменение даёт вызов на каждый объект. Делай upsert после чтения состояния и защиту от повторов по `member_id` + `event` + ID объекта + `ts`.
- Не записывай данные, повторно вызывающие то же событие, без защиты от цикла.

```php
$request = \Bitrix\Main\Context::getCurrent()->getRequest();
$auth = $request->getPost('auth');
$memberId = is_array($auth) ? (string)($auth['member_id'] ?? '') : '';
$token = is_array($auth) ? (string)($auth['application_token'] ?? '') : '';
$stored = $this->tokens->findApplicationToken($memberId); // своё хранилище

if ($stored === null || !hash_equals($stored, $token))
{
    // Ответить 403; журналировать только имя события и member_id.
}
$this->queue->push($request->getPostList()->toArray()); // затем ответить 200
```

Для PHP коробки публикуй маршрут/действие без сессионного и CSRF-фильтра (`bitrix-routing`, `bitrix-controllers`); аутентификация здесь — проверка `application_token`.

## Офлайн-события без входящего HTTP

- `event.bind` с `event_type: offline` сохраняет изменения на стороне Битрикс24. Только администратор в контексте приложения.
- Очередь хранит последнюю запись объекта по `MESSAGE_ID`, а не каждое изменение.
- Опрос: `event.offline.get`, `limit` по умолчанию 50. `clear: 1` сразу удаляет выданные записи: сбой клиента их теряет. Предпочтительно `clear: 0`, затем `process_id` и `event.offline.clear` после обработки либо `event.offline.error` при ошибке. В облаке нужна возможность `rest_offline_extended` (`feature.get`); тарифное ограничение не относится к коробке, где `feature.get` возвращает `<language>_selfhosted`.
- `auth_connector` исключает собственные изменения приложения из очереди, предотвращая эхо синхронизации.
- `onOfflineEvent` уведомляет о непустой очереди; `options.minTimeout` по умолчанию 1 секунда.

## Встройки

- `placement.bind {PLACEMENT, HANDLER, TITLE, LANG_ALL, OPTIONS}`, обычно при установке. Только контекст приложения.
- Нужен `placement`, для многих точек также scope инструмента (`crm` для CRM, `task` для задач). Проверяй страницу встройки.
- **Since rest 26.600** регистрировать/снимать встройку может также сотрудник с правом создания общих приложений для доступного локального приложения либо владелец своего персонального приложения. Маркет-приложения — администратор. `USER_ID` только свой либо 0; для других — `ACCESS_DENIED`. Проверки: `Bitrix\Rest\Api\Placement::checkPermission()`, `AppAccessChecker`. На старой версии используй администратора.
- Серверный обработчик получает POST iframe (`AUTH_ID`, `REFRESH_ID`, `APPLICATION_TOKEN`, `member_id`, `PLACEMENT`) и JSON-строку контекста `PLACEMENT_OPTIONS`, например ID сделки. Проверяй данные до использования. Статическая страница после `BX24.init` читает контекст через `BX24.placement.info()`.
- Разреши iframe: без `X-Frame-Options: DENY` и блокирующего `CSP frame-ancestors`.
- Встройки скрыты до завершения установки; затем видны пользователям с доступом к приложению.
- Ошибки: `ERROR_PLACEMENT_NOT_FOUND` (неверный код/нет scope инструмента), `ERROR_WRONG_HANDLER_URL`, `ERROR_PLACEMENT_MAX_COUNT`. У статического приложения после замены ZIP меняется папка HTML: перепривяжи обработчики к новому URL, иначе будет 404. `install.html` повторно не запускается.
