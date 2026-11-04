# Секция `smtp`

Без секции (или если `enabled` не строго `true`) `bxmail()` вызывает `custom_mail()`, если она определена, иначе PHP `mail()`.

## 1. SMTP-подключения отправителей

Подключения создаются в «Настройки → Настройки продукта → Почтовые и СМС события → Настройки SMTP» и используются только при:

```php
'smtp' => [
    'value' => [
        'enabled' => true,                       // именно true, не 'Y' и не 1
        'debug' => false,                        // включай временно только для безопасной диагностики
        'log_file' => '/var/log/site/mailer.log', // необязательно; ключ режима подключений отправителей
    ],
    'readonly' => true,
],
```

Выбор подключения по `From`/отправителю (правило изменилось в main 26.800), очередь и шаблоны: `bitrix-mail`.

## 2. Общий SMTP-сервер для всей почты

```php
'smtp' => [
    'value' => [
        'enabled' => true,
        'host' => 'smtp.example.com',
        'port' => 587,                   // по умолчанию 465
        'login' => 'robot@example.com',
        'password' => getenv('SMTP_PASSWORD'),
        'from' => 'robot@example.com',
        'encryption_type' => 'smtps',   // 'smtps': SMTPS на 465, STARTTLS на других портах; 'smtp': без шифрования
        'connection_timeout' => 10,     // секунды, по умолчанию 30
        'force_from' => false,          // true: каждый From: заменяется на 'from'
        'debug' => false,
        'logFile' => '/var/log/site/mailer.log', // camelCase в этом режиме
    ],
    'readonly' => true,
],
```

- Используется, только если `host` и `login` непустые.
- Ключ лога зависит от режима: подключение отправителя читает `log_file`, общий сервер — `logFile`; без него пишет `mailer.log` в `DOCUMENT_ROOT`. Если режим неясен, задай оба.
- Значения `'starttls'` нет; STARTTLS — это `'smtps'` с портом кроме 465.
- TLS требует сертификат доверенного УЦ с именем `host`; самоподписанный не пройдёт проверку.
- Пароль храни в `/local/.settings_extra.php` или env, не в git.
