# Rights, JWT, encryption, auth settings

## Rights checks

```php
use Bitrix\Main\Engine\CurrentUser;

$user = CurrentUser::get();
if (!$user->getId()) { /* guest */ }
if ($user->isAdmin()) { /* ... */ }
if ($user->canDoOperation('edit_own_profile')) { /* ... */ }

$right = \CMain::GetUserRight('vendor.blog', $user->getUserGroups()); // 'D' < 'R' < 'W'; groups are IDs
if ($right < 'W') { /* deny */ }
```

- Complex ACL: the `access` module pattern (`Bitrix\Main\Access\BaseAccessController`, `AccessibleItem`, role/permission tables); lists filtered with `getEntityFilter()`.
- Controllers: `#[ActionAccess]` (**Since main 26.600\***) when the module has an access controller, otherwise a small `ActionFilter\Base` subclass: skill `bitrix-controllers` → `rules/filters.md`. Don't copy `$USER` checks into every action.
- Authorize on the record: load it by id, take its type/owner/section from the DB, then check. A client-sent type next to an id allows cross-type writes and existence probing. Answer "not found" and "forbidden" with the same error.
- REST v3 (**Since rest 26.200\***): a method's scopes are every dot-prefix of its name plus `#[Scope]`; a token with any of them passes. `#[Scope(\CRestUtil::GLOBAL_SCOPE)]` means any authenticated caller, so check rights inside. `Idempotency-Key` is not a uniqueness guard: enforce uniqueness in the DB.

## JWT / JWK

Use `Bitrix\Main\Web\JWT` / `JWK`, not hand-rolled signing.

```php
use Bitrix\Main\Web\JWK;
use Bitrix\Main\Web\JWT;

$token = JWT::encode(['sub' => $userId, 'iat' => time(), 'exp' => time() + 3600], $secret, 'HS256');
$claims = JWT::decode($token, $secret, ['HS256']);      // always pass the allowed algorithms

$claims = JWT::decode($idToken, JWK::parseKeySet($jwks), ['RS256']); // key picked by `kid`
```

- Always set `exp` and `iat`; payload is JSON scalars/arrays.
- Base64url: `JWT::urlsafeB64Encode()` / `urlsafeB64Decode()`.
- Secrets from env or `.settings_extra.php`, never in git, options or migrations.

## Encryption

- ORM `CryptoField('SECRET')`: encrypted at rest with `crypto.crypto_key` from `.settings.php` (or `crypto_key` in field params).
- `SecretField('TOKEN')`: a `CryptoField` that auto-fills a random binary secret (`secret_length`, default 20 bytes), stored base64.
- Other data: `Bitrix\Main\Security\Cipher` `encrypt($data, $key)` / `decrypt($data, $key)`.
- Token cookies: `HttpOnly` + `Secure` + `SameSite`, `CryptoCookie` for sensitive values (`bitrix-request-response`). Never keep access tokens in `localStorage`.

## Auth settings

- 2FA (`security` module) is off by default (`otp_enabled` = N): enable it and make it mandatory for admins. security 26.200 adds an e-mail backup channel, off by default (`otp_allow_email`). Don't extend `Bitrix\Security\Mfa\Otp`: its signatures change between versions.
- Proactive filter, redirect protection and frame protection (`X-Frame-Options: SAMEORIGIN`) live in the `security` module. Don't disable them without reason.
- Public forms: `CCaptcha`.
