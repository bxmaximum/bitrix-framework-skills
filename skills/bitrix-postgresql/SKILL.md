---
name: bitrix-postgresql
description: 'Используй для PostgreSQL в Битриксе: PgsqlConnection, перенос с MySQL, проверка модулей и переносимый SQL. Подготовка схемы и безопасная репетиция миграции.'
---

# PostgreSQL

Baseline: main 23.900.900+ (первая поддержка PostgreSQL) · Verified: main 26.1000.0, perfmon 25.300.0

Перед PHP-кодом прочитай `bitrix-framework`, если ещё не читал его в этой задаче.

Редакция Битрикс24 / CMS «Enterprise for Postgres». Класс соединения `\Bitrix\Main\DB\PgsqlConnection`; остальные параметры `connections` — в `bitrix-database`.

## Перед переносом

1. Действующая лицензия должна покрывать тестовый период (до шести месяцев) и переход. Лицензия Postgres даёт купон (активируется после тестов) и ключ отдельной тестовой копии; рабочий сайт пока остаётся на MySQL.
2. Нужны `perfmon` 24.0.0+, UTF-8 и последние обновления на тестовой копии.
3. Репетируй на копии production: возврат на MySQL после перехода требует ручной работы.
4. Закрой сайт на время окончательного копирования.

## Мастер `perfmon.pgsql` («Настройки → Мастера»)

- На шаге 0 показывает установленные модули с `install/mysql` (или `install/db/mysql`) без пары `pgsql` и **блокирует перенос до их удаления**. С файлом разработчика `bitrix/modules/perfmon/dev` только предупреждает и после копирования удаляет их записи из `b_module` целевой БД, сохраняя таблицы.
- Проверяет только `/bitrix/modules/*`. `/local/modules` и модули без установочных SQL-файлов (`install/migrations`) не проверяются: проверь самостоятельно.
- Просит дополнительное соединение `default_pgsql` в `.settings.php`, копирует таблицы, затем ты переключаешь `default`.
- Для ручного переноса через CLI найди неподдерживаемые модули:

```bash
for m in bitrix/modules/*/; do
  for d in install install/db; do
    [ -d "$m$d/mysql" ] && [ ! -d "$m$d/pgsql" ] && echo "нет pgsql: $m$d"
  done
done
```

## Поддержка модулей (документация main 26.800)

- Не поддерживаются: abtest, advertising, b24connector, bitrix.sitecorporate, conversion, form, meeting, photogallery, report, statistic, storeassist, support, translate, webservice, wiki. Поэтому недоступен и `translate:index`.
- У abtest и report есть скрипты pgsql, но поддержки нет: наличие скрипта не означает поддержку.
- Недавно добавлены: anonymizer 26.100, superset 26.200, note 26.250, biconnector 26.800. В PostgreSQL-дистрибутиве Битрикс24 нет vibecodeconnector и workflow.
- Поддержку модулей Marketplace уточняй у вендора.

## Переносимый код

- Используй ORM, функции `SqlHelper` и плейсхолдеры `SqlExpression` вместо MySQL-синтаксиса: без обратных кавычек, `ENGINE=`, `UNSIGNED`, `ON DUPLICATE KEY` (замена `prepareMerge*`), `GROUP_CONCAT`, `IFNULL`.
- Схема модуля: **Since main 26.600\*** один независимый от СУБД `install/migrations/tables.php` (`bitrix-modules`); раньше нужна пара скриптов `install/db/mysql/` и `install/db/pgsql/`.
- Столбцы: `datetime`, не `timestamp`. Имена индексов в PostgreSQL общие для схемы: добавляй префикс таблицы. Слой миграций нормализует и хеширует длинные имена (main 26.800).
- `getInsertedId()` после пакетной вставки — последний ID (в MySQL первый).
- Сервер: `standard_conforming_strings = on` (проверяется системой с main 26.800).

## Проверка

- [ ] Каждый установленный модуль поддерживается либо удалён до запуска мастера.
- [ ] `/local/modules` и модули Marketplace проверены на прямой MySQL SQL и установочные скрипты.
- [ ] Свои таблицы описаны через `install/migrations/tables.php` (26.600+) либо пару SQL-файлов.
- [ ] Перенос отрепетирован на копии; купон активирован только после тестов.
