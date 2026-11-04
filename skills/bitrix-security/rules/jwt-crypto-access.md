# Права, JWT, шифрование и авторизация

## Проверки прав

```php
use Bitrix\Main\Engine\CurrentUser;

$user = CurrentUser::get();
if (!$user->getId()) { /* гость */ }
if ($user->isAdmin()) { /* ... */ }
if ($user->canDoOperation('edit_own_profile')) { /* ... */ }

$right = \CMain::GetUserRight('vendor.blog', $user->getUserGroups()); // 'D' < 'R' < 'W'; группы — ID
if ($right < 'W') { /* отказ */ }
```

- Сложные ACL: схема модуля `access` (`Bitrix\Main\Access\BaseAccessController`, `AccessibleItem`, таблицы ролей/разрешений); списки фильтруются `getEntityFilter()`.
- Контроллеры: `#[ActionAccess]` (**Since main 26.600\***), если у модуля есть контроллер доступа; иначе небольшой подкласс `ActionFilter\Base` (`bitrix-controllers` → `rules/filters.md`). Не копируй проверки `$USER` по действиям.
- Проверяй доступ по записи: загрузи по ID, возьми тип/владельца/раздел из БД, затем проверь. Тип от клиента рядом с ID позволяет запись в чужой тип и проверку существования. Ошибки отсутствия и запрета делай одинаковыми.
- REST v3 (**Since rest 26.200\***): области метода — все точечные префиксы его имени плюс `#[Scope]`; токену достаточно любой. `#[Scope(\CRestUtil::GLOBAL_SCOPE)]` допускает любого авторизованного вызывающего: права проверяй внутри. `Idempotency-Key` не гарантирует уникальность: ограничивай её в БД.

## JWT / JWK

Используй `Bitrix\Main\Web\JWT` / `JWK`, не реализуй подпись вручную.

```php
use Bitrix\Main\Web\JWK;
use Bitrix\Main\Web\JWT;

$token = JWT::encode(['sub' => $userId, 'iat' => time(), 'exp' => time() + 3600], $secret, 'HS256');
$claims = JWT::decode($token, $secret, ['HS256']);      // всегда перечисляй разрешённые алгоритмы

$claims = JWT::decode($idToken, JWK::parseKeySet($jwks), ['RS256']); // ключ выбирается по kid
```

- Всегда задавай `exp` и `iat`; содержимое — скаляры/массивы JSON.
- Base64url: `JWT::urlsafeB64Encode()` / `urlsafeB64Decode()`.
- Секреты бери из env или `.settings_extra.php`, не из git, настроек Option или миграций.

## Шифрование

- ORM `CryptoField('SECRET')` шифрует хранимое значение ключом `crypto.crypto_key` из `.settings.php` (либо `crypto_key` в параметрах поля).
- `SecretField('TOKEN')` — `CryptoField`, автоматически создающий случайный двоичный секрет (`secret_length`, по умолчанию 20 байт), хранимый в base64.
- Другие данные: `Bitrix\Main\Security\Cipher`, методы `encrypt($data, $key)` / `decrypt($data, $key)`.
- Cookies токенов: `HttpOnly` + `Secure` + `SameSite`, `CryptoCookie` для чувствительных значений (`bitrix-request-response`). Не храни токены доступа в `localStorage`.

## Настройки авторизации

- 2FA модуля `security` по умолчанию выключена (`otp_enabled=N`); при задаче на усиление доступа включи и сделай обязательной для администраторов. В security 26.200 добавлен резервный канал e-mail, по умолчанию выключен (`otp_allow_email`). Не наследуй `Bitrix\Security\Mfa\Otp`: сигнатуры меняются между версиями.
- Проактивный фильтр, защита перенаправлений и фреймов (`X-Frame-Options: SAMEORIGIN`) находятся в `security`. Не отключай без причины.
- Публичные формы: `CCaptcha`.


Требования PHP/Tokenizer и проверка окружения перед обновлением описаны в `bitrix-project-structure`. Отсутствие расширения нельзя обходить отключением проактивной защиты. Формат и совместимость зашифрованных cookies v2 — `bitrix-sessions`.
