# `smtp` section

Without the section (or with `enabled` not strictly `true`) `bxmail()` calls `custom_mail()` if defined, else PHP `mail()`.

## 1. Enable per-sender SMTP connections

Connections are created in *Settings → Product settings → Mail and SMS events → SMTP settings*; they are used only when:

```php
'smtp' => [
    'value' => [
        'enabled' => true,                       // boolean true, not 'Y' or 1
        'debug' => true,                         // optional: log the SMTP dialogue
        'log_file' => '/var/log/site/mailer.log', // optional, this key for per-sender mode
    ],
    'readonly' => true,
],
```

Which connection a message uses (by `From`/identity; rule changed in main 26.800), queue and templates: `bitrix-mail`.

## 2. Default SMTP server for all mail

```php
'smtp' => [
    'value' => [
        'enabled' => true,
        'host' => 'smtp.example.com',
        'port' => 587,                   // default 465
        'login' => 'robot@example.com',
        'password' => getenv('SMTP_PASSWORD'),
        'from' => 'robot@example.com',
        'encryption_type' => 'smtps',   // 'smtps' → SMTPS on 465, STARTTLS on other ports; 'smtp' → none
        'connection_timeout' => 10,     // seconds, default 30
        'force_from' => false,          // true: rewrite every From: to 'from'
        'debug' => false,
        'logFile' => '/var/log/site/mailer.log', // camelCase in this mode
    ],
    'readonly' => true,
],
```

- Used only when both `host` and `login` are non-empty.
- Log key differs by mode: per-sender connection reads `log_file`, the default server reads `logFile`; missing → `mailer.log` in `DOCUMENT_ROOT`. Set both if unsure.
- No `'starttls'` value exists; STARTTLS = `'smtps'` with a non-465 port.
- TLS needs a CA-signed certificate matching `host`; self-signed fails verification.
- Keep the password in `/local/.settings_extra.php` or env, not in git.
