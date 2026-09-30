---
name: bitrix-datetime
description: 'Bitrix\Main\Type\Date and DateTime — parsing, culture formats and masks, arithmetic, server vs user time zone, ORM DateField/DatetimeField. Use when parsing, formatting, comparing or storing dates and times.'
---

# Date and time

Baseline: main 23.0+ · Verified: main 26.800.0

- `Bitrix\Main\Type\Date` — date only (time `00:00:00`). **Not** a `\DateTime` subclass: it wraps one, so `modify()`, `sub()`, `setTimestamp()` don't exist.
- `Bitrix\Main\Type\DateTime extends Date` — adds time, time zone and user-time conversion.

Available: `format()`, `add()`, `setDate()`, `getTimestamp()`, `getDiff()`, `toString()`; `DateTime` adds `setTime()`, `setTimeZone()`, `setDefaultTimeZone()`, `toUserTime()`, `disableUserTime()`.

```php
use Bitrix\Main\Type\Date;
use Bitrix\Main\Type\DateTime;

$d = new Date('25.11.2025', 'd.m.Y');                  // 2nd arg is a PHP format
$dt = new DateTime('2025-11-25 14:30:00', 'Y-m-d H:i:s', new \DateTimeZone('UTC'));
$now = new DateTime();
$fromSite = new DateTime($request->get('DATE'));       // no format = site culture format; bad input throws ObjectException
```

## Parsing user input

```php
$dt = DateTime::tryParse($input, 'd.m.Y H:i');         // null on error
$isValid = $dt !== null && $dt->format('d.m.Y H:i') === $input;   // strict
```

`Date::isCorrect('31.02.2025', 'd.m.Y')` returns **true**: the parser rolls it over to 03.03.2025. Use the strict round-trip above or `checkdate()`.

Other sources: `DateTime::createFromTimestamp($ts)`, `DateTime::createFromPhp(\DateTime $php)` (a `\DateTimeImmutable` needs `\DateTime::createFromImmutable()` first), `Date::createFromText('tomorrow')` (returns `DateTime|null`).

## Culture formats and masks

Culture (`Context::getCurrent()->getCulture()`) stores **site masks** (`DD.MM.YYYY HH:MI:SS`), not PHP formats. Never pass them to `format()`:

```php
$culture = \Bitrix\Main\Context::getCurrent()->getCulture();
echo $dt->toString($culture);                           // culture format, user TZ
echo $dt->format(DateTime::getFormat($culture));        // culture format, no TZ shift
echo $d->format(Date::convertFormatToPhp($culture->getDateFormat()));
```

`Date::convertFormatToPhp()`:

| Mask | PHP | Note |
| --- | --- | --- |
| `YYYY`, `MMMM`, `MM`, `DD` | `Y`, `F`, `m`, `d` | |
| `MI`, `SS` | `i`, `s` | |
| `HH` / `H` | `H` / `h` | a lone `H` (no `HH`) means 12-hour |
| `GG` / `G` | `G` / `g` | same rule, no leading zero |
| `TT` / `T` | `A` / `a` | AM/PM |

## Arithmetic

`add()` mutates and returns the object. It accepts ISO intervals (`P10D`, `-P1M`, `PT30M`) and relative text (`+2 weeks`, `-3 days`):

```php
$tomorrow = (clone $now)->add('P1D');
$dayStart = (clone $now)->setTime(0, 0);
$monday = (clone $dayStart)->add('-' . ((int)$now->format('N') - 1) . ' days');
$days = $d1->getDiff($d2)->days;                        // \DateInterval from $d1 to $d2
```

## Time zones

Values are kept in **server** time; users see their own zone when time zones are enabled in the main module.

| Call | Result |
| --- | --- |
| `(string)$dt`, `$dt->toString()` | culture format in **user** TZ (if enabled and not disabled on the object) |
| `$dt->format(...)` | the object's own TZ (server unless set, e.g. the UTC example above), never user-shifted |
| `$dt->disableUserTime()` | `toString()` stays in server time: logs, e-mails, text for humans in server TZ |
| `DateTime::createFromUserTime($str)` | parses a user-TZ string into server time |
| `$dt->toUserTime()` | shifts the object itself; use on a clone, never save it |

Legacy writers (`CAgent::AddAgent`, `$DB->Add()`/`PrepareUpdate()`) run date strings through `CDatabase::CharToDateFunction()`, which subtracts the user offset. Pass them **user-time** strings — plain `toString()`, not `disableUserTime()`:

```php
\CAgent::AddAgent(
    MyAgent::class . '::run();',
    'vendor.module',
    'N',
    60,
    '',
    'Y',
    (new DateTime())->add('+5 minutes')->toString(),   // user time; the kernel shifts it back
);
```

## ORM

`DateField` returns `Date`, `DatetimeField` returns `DateTime`; filter with objects, not strings:

```php
PostTable::query()->setSelect(['ID'])->where('CREATED_AT', '>', (new DateTime())->add('-P7D'))->fetchAll();
```

At the SQL level `DateTime` is written in server TZ (`bitrix-database`). Third-party code wanting `\DateTimeInterface`: `\DateTime::createFromFormat('U', (string)$dt->getTimestamp())`.
