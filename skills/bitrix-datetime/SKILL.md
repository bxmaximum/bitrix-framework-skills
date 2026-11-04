---
name: bitrix-datetime
description: 'Используй для дат Битрикса: Type\Date и Type\DateTime, разбор ввода, формат культуры, вычисления и часовые пояса. Хранение в DatetimeField ORM и старом API.'
---

# Дата и время

Baseline: main 23.0+ · Verified: main 26.1000.0

Перед PHP-кодом прочитай `bitrix-framework`, если ещё не читал его в этой задаче.

- `Bitrix\Main\Type\Date` — только дата (время `00:00:00`). Это **не наследник** `\DateTime`, а обёртка: методов `modify()`, `sub()`, `setTimestamp()` нет.
- `Bitrix\Main\Type\DateTime extends Date` добавляет время, часовой пояс и преобразование ко времени пользователя.

Доступны `format()`, `add()`, `setDate()`, `getTimestamp()`, `getDiff()`, `toString()`; `DateTime` добавляет `setTime()`, `setTimeZone()`, `setDefaultTimeZone()`, `toUserTime()`, `disableUserTime()`.

```php
use Bitrix\Main\Type\Date;
use Bitrix\Main\Type\DateTime;

$d = new Date('25.11.2025', 'd.m.Y');                  // второй аргумент — формат PHP
$dt = new DateTime('2025-11-25 14:30:00', 'Y-m-d H:i:s', new \DateTimeZone('UTC'));
$now = new DateTime();
$fromSite = new DateTime($request->get('DATE'));       // без формата используется культура сайта; неверный ввод вызывает ObjectException
```

## Разбор пользовательского ввода

```php
$dt = DateTime::tryParse($input, 'd.m.Y H:i');         // null при ошибке
$isValid = $dt !== null && $dt->format('d.m.Y H:i') === $input;   // строгая проверка
```

`Date::isCorrect('31.02.2025', 'd.m.Y')` возвращает **true**: разбор переносит дату на 03.03.2025. Используй строгую обратную проверку выше или `checkdate()`.

Другие источники: `DateTime::createFromTimestamp($ts)`, `DateTime::createFromPhp(\DateTime $php)` (для `\DateTimeImmutable` сначала `\DateTime::createFromImmutable()`), `Date::createFromText('tomorrow')` (результат `DateTime|null`).

## Форматы культуры и маски

Культура (`Context::getCurrent()->getCulture()`) хранит **маски сайта** (`DD.MM.YYYY HH:MI:SS`), а не форматы PHP. Не передавай их в `format()`:

```php
$culture = \Bitrix\Main\Context::getCurrent()->getCulture();
echo $dt->toString($culture);                           // формат культуры, часовой пояс пользователя
echo $dt->format(DateTime::getFormat($culture));        // формат культуры, без сдвига часового пояса
echo $d->format(Date::convertFormatToPhp($culture->getDateFormat()));
```

`Date::convertFormatToPhp()`:

| Маска | PHP | Примечание |
| --- | --- | --- |
| `YYYY`, `MMMM`, `MM`, `DD` | `Y`, `F`, `m`, `d` | |
| `MI`, `SS` | `i`, `s` | |
| `HH` / `H` | `H` / `h` | Одиночный `H` (без `HH`) означает 12-часовой формат |
| `GG` / `G` | `G` / `g` | То же правило, без ведущего нуля |
| `TT` / `T` | `A` / `a` | AM/PM |

## Вычисления

`add()` изменяет и возвращает объект. Принимает интервалы ISO (`P10D`, `-P1M`, `PT30M`) и относительный текст (`+2 weeks`, `-3 days`):

```php
$tomorrow = (clone $now)->add('P1D');
$dayStart = (clone $now)->setTime(0, 0);
$monday = (clone $dayStart)->add('-' . ((int)$now->format('N') - 1) . ' days');
$days = $d1->getDiff($d2)->days;                        // \DateInterval от $d1 до $d2
```

## Часовые пояса

Значения хранятся во времени **сервера**; пользователь видит свой пояс, если часовые пояса включены в главном модуле.

| Вызов | Результат |
| --- | --- |
| `(string)$dt`, `$dt->toString()` | Формат культуры во времени **пользователя**, если пояса включены и не отключены у объекта |
| `$dt->format(...)` | Часовой пояс объекта (по умолчанию сервер, если не задан, как UTC выше), без сдвига к пользователю |
| `$dt->disableUserTime()` | `toString()` остаётся во времени сервера: логи, письма, текст для человека в серверном поясе |
| `DateTime::createFromUserTime($str)` | Разбирает строку времени пользователя в серверное время |
| `$dt->toUserTime()` | Сдвигает сам объект: используй клон и не сохраняй его |

Старые методы записи (`CAgent::AddAgent`, `$DB->Add()`/`PrepareUpdate()`) проводят строки дат через `CDatabase::CharToDateFunction()`, вычитающую смещение пользователя. Передавай строки во времени **пользователя**: обычный `toString()`, без `disableUserTime()`:

```php
\CAgent::AddAgent(
    MyAgent::class . '::run();',
    'vendor.module',
    'N',
    60,
    '',
    'Y',
    (new DateTime())->add('+5 minutes')->toString(),   // время пользователя; ядро сдвинет обратно
);
```

## ORM

`DateField` возвращает `Date`, `DatetimeField` — `DateTime`; фильтруй объектами, а не строками:

```php
PostTable::query()->setSelect(['ID'])->where('CREATED_AT', '>', (new DateTime())->add('-P7D'))->fetchAll();
```

На уровне SQL `DateTime` записывается в поясе сервера (`bitrix-database`). Для внешнего кода с `\DateTimeInterface`: `\DateTime::createFromFormat('U', (string)$dt->getTimestamp())`.
