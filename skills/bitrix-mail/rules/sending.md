# Отправка

## Очередь по умолчанию

```php
use Bitrix\Main\Mail\Event;

$result = Event::send([
    'EVENT_NAME' => 'VENDOR_ORDER_READY',
    'LID' => 's1',                              // ID сайта
    'C_FIELDS' => ['EMAIL' => $email, 'NAME' => $name, 'ORDER_ID' => (string)$orderId],
    'LANGUAGE_ID' => 'ru',                      // необязательный фильтр шаблонов
    'DUPLICATE' => 'N',                         // Y по умолчанию в БД добавляет main:all_bcc в BCC
    'FILE' => [$fileId],                        // ID b_file; пути/URL копируются в b_file
]);
if (!$result->isSuccess())
{
    // отмена в OnBeforeEventAdd либо ошибка вставки
}
```

- Возвращается `ORM\Data\AddResult` строки `b_event`: письмо поставлено в очередь, ещё не отправлено. Доставка и статусы: `rules/hooks-testing.md`.
- ID `FILE` читаются при отправке: не удаляй файлы сразу после `send()`. Скопированные по пути файлы удаляет агент после очистки строки `b_event`.
- Обёртка для совместимости: `CEvent::Send($event, $lid, $arFields, $Duplicate = 'Y', $message_id = '', $files = [], $languageId = '')` → ID либо `false`. `$lid` может быть массивом сайтов, `$message_id` выбирает конкретный шаблон.

## Немедленная отправка

```php
$status = Event::sendImmediate([
    'EVENT_NAME' => 'VENDOR_ORDER_READY',
    'LID' => 's1',
    'C_FIELDS' => ['EMAIL' => $email, 'NAME' => $name, 'ORDER_ID' => (string)$orderId],
    'LANGUAGE_ID' => 'ru',                      // читается без isset: передавай всегда
    'DUPLICATE' => 'N',
    'FILE' => [$fileId],                        // только числовые ID b_file; пути игнорируются
]);
if ($status !== Event::SEND_RESULT_SUCCESS) { /* PARTLY 'P', ERROR 'F', TEMPLATE_NOT_FOUND '0', NONE 'N' = cancelled */ }
```

- Выполняется в текущем запросе, включая ожидание SMTP; записи `b_event` нет. Применяй в CLI, когда пользователь должен увидеть ошибку либо нужен конкретный отправитель.
- `FILES_CONTENT` только для немедленной отправки: `[['NAME' => 'invite.ics', 'CONTENT' => $ics, 'CONTENT_TYPE' => 'text/calendar', 'ID' => 'ics1', 'CHARSET' => 'UTF-8', 'METHOD' => 'REQUEST']]`. Передай все шесть ключей; `METHOD` может быть `null`.
- Обёртка: `CEvent::SendImmediate($event, $lid, $arFields, $Duplicate = 'Y', $message_id = '', $files = [], $languageId = '', array $filesContent = [])` → статус либо `false` при отмене.

## Идентификация отправителя — Since main 26.800

```php
use Bitrix\Main\Mail\Sender\Identity;

$status = Event::sendImmediate($data, Identity::fromSender($senderId)); // b_main_mail_sender.ID
// Identity::fromMailbox($mailboxId) / fromMailboxAndSender($mailboxId, $senderId): ящики модуля mail
```

- Также `CEvent::SendImmediate(..., $filesContent, $senderIdentity, $context)`. Очередь не сохраняет отправителя и контекст; у `Event::send()` такого параметра нет.
- При заданной идентификации `From` должен совпадать с адресом отправителя; иначе `MAIL_SENDER_ADDRESS_MISMATCH`.
- На старом ядре задай `From`; SMTP-отправитель выбирается по адресу.

## Письмо без шаблона

```php
use Bitrix\Main\Mail\Mail;

$result = Mail::sendResult([                   // Since main 26.800; раньше: Mail::send() → bool
    'TO' => $to,
    'SUBJECT' => $subject,
    'BODY' => $html,
    'HEADER' => ['From' => $from, 'Reply-To' => $replyTo],
    'CHARSET' => 'UTF-8',
    'CONTENT_TYPE' => 'html',                  // либо text
    'ATTACHMENT' => [[
        'ID' => 'report1',                     // обязательный Content-ID
        'NAME' => 'report.pdf',
        'CONTENT_TYPE' => 'application/pdf',
        'PATH' => $absPath,                    // либо CONTENT => $bytes
    ]],
]);
foreach ($result->getErrors() as $error)
{
    // MAIL_SEND_CANCELED, MAIL_SEND_NOT_ALLOWED (после чёрного списка не осталось получателей),
    // MAIL_SEND_FAILED, MAIL_SENDER_ADDRESS_MISMATCH либо ошибка обработчика
}
```

- Обязательны `TO`, `SUBJECT`, `BODY`, `HEADER` (массив), `CHARSET`, `CONTENT_TYPE`. Очереди, шаблона и строки `b_event` нет. `OnBeforeMailSend` всё равно вызывается.
- Отправитель: `'CONTEXT' => (new \Bitrix\Main\Mail\Context())->setSenderIdentity(Identity::fromSender($senderId))`. `->setAutomaticCustomSmtpEnabled(false)` отключает подбор SMTP по отправителю (**Since main 26.800**).
- Вложение, превышающее вместе с остальными размер base64 из `main:max_file_size`, заменяется пояснением `.txt`.

## Выбор SMTP

1. При `smtp.enabled === true` в `.settings.php` SMTP отправителя (`b_main_mail_sender`) выбирается по `From` либо идентификации.
2. Иначе используется сервер по умолчанию той же секции, если заданы `host` и `login`.
3. Иначе PHP `mail()` либо определённая тобой `custom_mail()`.

Ключи настроек: `bitrix-settings`. **Since main 26.800** при нескольких отправителях с одним адресом побеждает публичный, иначе самый старый; раньше побеждал самый новый. Неоднозначность пишется как `MAIL_SENDER_AMBIGUOUS`. Оставляй одного отправителя на адрес либо задавай идентификацию.
