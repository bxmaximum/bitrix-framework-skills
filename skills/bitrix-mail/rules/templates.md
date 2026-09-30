# Event types and templates

A mail event is a type (`b_event_type`, one row per **language**) plus templates (`b_event_message`, bound to **sites** in `b_event_message_site`). Sending an event sends every active template of that type bound to the event's site whose `LANGUAGE_ID` matches the event's language or is empty.

## Create (module installer or migration)

There is no D7 writer. `Bitrix\Main\Mail\Internal\EventTypeTable` and `EventMessageTable` are internal: they don't validate, and `EventMessageTable::add()` creates no site binding, so the template is never selected by site (only by an explicit `MESSAGE_ID`). Use the legacy classes (compat only) or the `sprint.migration` helpers `saveEventType()`/`saveEventMessage()` (`bitrix-sprint-migration`).

```php
CEventType::Add([
    'LID' => 'ru',                            // language id; add one row per language
    'EVENT_NAME' => 'VENDOR_ORDER_READY',
    'NAME' => 'Order is ready',
    'DESCRIPTION' => "#EMAIL# - customer e-mail\n#NAME# - customer name\n#ORDER_ID# - order id",
]);

$message = new CEventMessage();               // Add() is an instance method
$id = $message->Add([
    'EVENT_NAME' => 'VENDOR_ORDER_READY',
    'LID' => ['s1'],                          // site ids → b_event_message_site
    'ACTIVE' => 'Y',
    'EMAIL_FROM' => '#DEFAULT_EMAIL_FROM#',
    'EMAIL_TO' => '#EMAIL#',
    'SUBJECT' => '#SITE_NAME#: order #ORDER_ID#',
    'BODY_TYPE' => 'html',
    'MESSAGE' => '<p>Hello, #HTML_NAME#! Order #ORDER_ID# is ready.</p>',
]);
if (!$id)
{
    // $message->LAST_ERROR
}
```

- `CEventType::Add()` returns the id or `false` (duplicate `EVENT_NAME` + `LID`; details in `$APPLICATION->GetException()`). `EVENT_TYPE` defaults to `email`.
- `CEventMessage::Add()` fails when the type does not exist or a site id is unknown.
- Optional template keys: `CC`, `BCC`, `REPLY_TO`, `IN_REPLY_TO`, `PRIORITY`, `LANGUAGE_ID`, `SITE_TEMPLATE_ID` (mail site template wrapped around the body), `ATTACHMENT_FILE` (b_file ids attached to every message), `ADDITIONAL_FIELD` (`[['NAME' => 'X-Order', 'VALUE' => '#ORDER_ID#']]` → headers).
- Uninstall: `CEventMessage::Delete($id)` for each template, then `CEventType::Delete('VENDOR_ORDER_READY')` (all languages).
- `saveEventMessage()` in sprint.migration finds the existing template by `EVENT_NAME` + `SUBJECT`. Changing the subject there adds a second template, and both get sent. Update by id instead.

## Macros

| In a template | Value |
| --- | --- |
| `#FIELD#` | `C_FIELDS['FIELD']`; arrays joined with `, ` |
| `#HTML_FIELD#` | HTML body only: the same value after `htmlspecialcharsbx()` + `nl2br()` |
| `#SITE_NAME#`, `#SERVER_NAME#`, `#DEFAULT_EMAIL_FROM#` | site settings, fallback to main options |
| `#SITE_ID#`, `#SITE_DIR#`, `#LANGUAGE_ID#` | site of the event (when the site exists) |
| `#MAIL_EVENTS_UNSUBSCRIBE_LINK#` | body: unsubscribe link for this event type |

- HTML body: `#FIELD#` is inserted as is (`nl2br` only if the value has no `<`). Print anything user-supplied as `#HTML_FIELD#`, or it is HTML injection in your mail.
- Subject, `EMAIL_TO`, `CC`, `REPLY_TO` get raw values. Remove `\r` and `\n` from values used there.
- A `C_FIELDS` key starting with `=` becomes a raw header (`'=X-Order' => '42'`). Never build field keys from input.
- Placeholder names use `[0-9a-zA-Z_.]` and up to 200 characters. On save, the body is compiled to PHP (`MESSAGE_PHP`) and executed on send, and templates may also call components. Only trusted admins should edit templates.
- **Since main 26.750** dotted names are escaped, so `#ORDER.ID#` no longer captures `#ORDER_ID#`. `MESSAGE_PHP` is rebuilt only when `MESSAGE` is saved, so re-save templates that have dotted placeholders after the update. On older kernels, avoid dots in placeholder names.
