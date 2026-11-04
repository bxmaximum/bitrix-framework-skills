# Типы событий и шаблоны

Почтовое событие — тип (`b_event_type`, строка на **язык**) и шаблоны (`b_event_message`), привязанные к **сайтам** в `b_event_message_site`. Отправляются все активные шаблоны типа, привязанные к сайту события, с совпадающим либо пустым `LANGUAGE_ID`.

## Создание в установщике модуля / миграции

API записи D7 нет. Внутренние `Bitrix\Main\Mail\Internal\EventTypeTable` / `EventMessageTable` не проверяют данные; `EventMessageTable::add()` не привязывает сайт, поэтому шаблон выбирается только по явному `MESSAGE_ID`. Используй старые классы либо `saveEventType()`/`saveEventMessage()` из `sprint.migration` (`bitrix-sprint-migration`).

```php
CEventType::Add([
    'LID' => 'ru',                            // ID языка: строка на каждый язык
    'EVENT_NAME' => 'VENDOR_ORDER_READY',
    'NAME' => 'Заказ готов',
    'DESCRIPTION' => "#EMAIL# — почта покупателя\n#NAME# — имя покупателя\n#ORDER_ID# — ID заказа",
]);

$message = new CEventMessage();               // Add() — метод экземпляра
$id = $message->Add([
    'EVENT_NAME' => 'VENDOR_ORDER_READY',
    'LID' => ['s1'],                          // ID сайтов → b_event_message_site
    'ACTIVE' => 'Y',
    'EMAIL_FROM' => '#DEFAULT_EMAIL_FROM#',
    'EMAIL_TO' => '#EMAIL#',
    'SUBJECT' => '#SITE_NAME#: заказ #ORDER_ID#',
    'BODY_TYPE' => 'html',
    'MESSAGE' => '<p>Здравствуйте, #HTML_NAME#! Заказ #ORDER_ID# готов.</p>',
]);
if (!$id)
{
    // $message->LAST_ERROR
}
```

- `CEventType::Add()` → ID либо `false` при дубле `EVENT_NAME` + `LID`; подробности в `$APPLICATION->GetException()`. `EVENT_TYPE` по умолчанию `email`.
- `CEventMessage::Add()` завершается ошибкой, если нет типа или неизвестен ID сайта.
- Необязательные ключи: `CC`, `BCC`, `REPLY_TO`, `IN_REPLY_TO`, `PRIORITY`, `LANGUAGE_ID`, `SITE_TEMPLATE_ID` (почтовый шаблон сайта вокруг тела), `ATTACHMENT_FILE` (ID b_file для каждого письма), `ADDITIONAL_FIELD` (`[['NAME' => 'X-Order', 'VALUE' => '#ORDER_ID#']]` — заголовки).
- Удаление модуля: `CEventMessage::Delete($id)` для каждого шаблона, затем `CEventType::Delete('VENDOR_ORDER_READY')` для всех языков.
- `saveEventMessage()` в проверенном sprint.migration 5.16.1 ищет по EVENT_NAME + SUBJECT + LID. Смена темы создаёт второй шаблон: отправятся оба. Обновляй по ID, учитывая сайт; детали в `bitrix-sprint-migration`.

## Макросы

| В шаблоне | Значение |
| --- | --- |
| `#FIELD#` | `C_FIELDS['FIELD']`; массивы соединяются через `, ` |
| `#HTML_FIELD#` | Только HTML-тело: значение после `htmlspecialcharsbx()` + `nl2br()` |
| `#SITE_NAME#`, `#SERVER_NAME#`, `#DEFAULT_EMAIL_FROM#` | Настройки сайта, иначе опции main |
| `#SITE_ID#`, `#SITE_DIR#`, `#LANGUAGE_ID#` | Сайт события, если существует |
| `#MAIL_EVENTS_UNSUBSCRIBE_LINK#` | В теле: отписка от этого типа события |

- `#FIELD#` в HTML вставляется как есть (`nl2br` только без `<` в значении). Пользовательские данные выводи как `#HTML_FIELD#`, иначе HTML-инъекция в письмо.
- Тема, `EMAIL_TO`, `CC`, `REPLY_TO` получают исходные значения: убирай `\r` и `\n` из их данных.
- Ключ `C_FIELDS`, начинающийся с `=`, создаёт сырой заголовок (`'=X-Order' => '42'`). Не собирай ключи полей из пользовательского ввода.
- Имена подстановок: `[0-9a-zA-Z_.]`, до 200 символов. При сохранении тело компилируется в PHP (`MESSAGE_PHP`), выполняемый при отправке; шаблон может вызывать компоненты. Редактирование разрешай только доверенным администраторам.
- **Since main 26.750** точки имён экранируются: `#ORDER.ID#` больше не захватывает `#ORDER_ID#`. `MESSAGE_PHP` пересобирается только при сохранении `MESSAGE`: после обновления пересохрани шаблоны с точками. На старом ядре избегай точек.
