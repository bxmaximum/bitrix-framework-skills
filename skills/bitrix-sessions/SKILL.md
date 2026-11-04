---
name: bitrix-sessions
description: 'Используй для сессий Битрикса: Application::getSession(), getLocalSession(), параллельный AJAX и блокировки. Хранилище, BX_SECURITY_SESSION_READONLY и разделённый режим.'
---

# Сессии

Baseline: main 23.0+ · Verified: main 26.1000.0

Перед PHP-кодом прочитай `bitrix-framework`, если ещё не читал его в этой задаче.

Не обращайся к `$_SESSION`: это обходит отложенный запуск и режимы только чтения/виртуальной сессии.

```php
use Bitrix\Main\Application;

$session = Application::getInstance()->getSession(); // SessionInterface + ArrayAccess
$session->set('vendor.wizard', ['step' => 2]);
$state = $session->get('vendor.wizard');              // null при отсутствии
if ($session->has('vendor.flash')) { $msg = $session->get('vendor.flash'); $session->remove('vendor.flash'); }
$session->regenerateId();                             // после входа или изменения привилегий
```

- Добавляй к ключам префикс модуля; храни только скаляры/массивы.
- Сессия записывается и закрывается до фоновых заданий и агентов: записи из `addBackgroundJob()` теряются (`bitrix-background-jobs`). В CLI, агентах и Messenger сессии нет.

## Локальная сессия — «кеш сессии»

```php
$local = Application::getInstance()->getLocalSession('vendor_cart'); // отдельный контейнер для каждого имени
$local->set('productIds', [1, 2, 3]);
$ids = $local['productIds'] ?? [];
```

- Хранится в движке `cache` (Redis/Memcache/APC) по ID сессии, записывается в конце хита; при файловом кеше переходит на `$_SESSION`.
- TTL: `.settings.php` → `'session_local_storage' => ['value' => ['ttl' => 86400]]`.
- Подходит для корзин, фильтров, черновиков мастера: больших/часто меняющихся данных, которым не следует раздувать и блокировать основную сессию.

## Сессия ядра

`Application::getInstance()->getKernelSession()` хранит данные ядра (авторизация, `sessid`). В режиме `default` это основная сессия; в `separated` — зашифрованный cookie (`crypto_key`), без объектов. Не храни там данные приложения.

**Since main 26.900** зашифрованное значение cookie имеет внутреннюю версию `v2:` и HMAC-подпись. Старые зашифрованные cookies не проходят проверку: ожидай повторный вход и сброс сессий в `separated` при обновлении. Не отключай проверку для сохранения старого cookie. До 26.900 формат старый; повторная авторизация безопаснее самодельного переноса cookie.

## Режимы (определи до `prolog_before.php`)

- `define('BX_SECURITY_SESSION_READONLY', true);`: обработчики читают без блокировки и не пишут. Для параллельного AJAX только чтения (подсказки, счётчики); изменения отбрасываются.
- `define('BX_SECURITY_SESSION_VIRTUAL', true);`: сессия в памяти, без cookie и сохранения. Для REST/вебхуков с токенами.
- `define('BX_FORCE_DISABLE_SEPARATED_SESSION_MODE', true);`: принудительный `default` для скрипта.

## Секция `session` (`bitrix-settings`)

```php
'session' => [
    'value' => [
        'mode' => 'separated',              // 'default' | 'separated'
        'lifetime' => 14400,                // срок cookie ядра, только разделённый режим
        'handlers' => [
            'kernel' => 'encrypted_cookies',  // единственный допустимый обработчик ядра
            'general' => [
                'type' => 'redis',            // file | database | redis | memcache | null
                'host' => '127.0.0.1',
                'port' => 6379,
                'keyPrefix' => 'site1',       // по умолчанию 'BX'; уникален для сайта на общем сервере
            ],
        ],
        'regenerateIdAfterLogin' => true,
        'ignoreSessionStartErrors' => false, // true: продолжить хит при недоступном хранилище
    ],
    'readonly' => true,
],
```

- `separated`: данные ядра в cookie, общая сессия запускается при первом обращении. Анонимные хиты, не использующие её, не обращаются к хранилищу.
- TTL общей сессии Redis/Memcache задаётся `session.gc_maxlifetime` в `php.ini`, не `lifetime`.
- Redis: `host`, `port`, `password`, `servers` (список `['host'=>…, 'port'=>…]` для кластера), `serializer`, `persistent`, `failover`, `timeout`, `readTimeout`, `keyPrefix`, `exclusiveLock` (значение блокировки — запрошенная страница, видна при ошибке таймаута). Memcache: `host`, `port`, `servers`, `connectionTimeout`, `keyPrefix`, `exclusiveLock`. Файлы: `savePath`. БД: таблица `b_user_session`, дополнительных ключей нет.
- Если секции `session` нет, а `session.save_handler` в `php.ini` не равен `files`, обработчик PHP используется без изменений.
- Сессия с записью блокируется на весь запрос (Redis/Memcache ждут около 60 секунд, затем фатальная ошибка): параллельные AJAX одного пользователя идут последовательно. Где нет записи, используй `BX_SECURITY_SESSION_READONLY`.

## Безопасность

- Cookie сессии всегда `HttpOnly`; `Secure`/`SameSite` берутся из `session.cookie_secure` / `session.cookie_samesite` в `php.ini`.
- Обновляй ID после входа и изменения привилегий (`regenerateIdAfterLogin` или `regenerateId()`).
- Не храни секреты и полные объекты пользователей в сессии; на каждом запросе проверяй права заново (`bitrix-security`).
