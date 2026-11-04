---
name: bitrix-settings
description: 'Используй для конфигурации ядра Битрикса: .settings.php и .settings_extra.php, Configuration, приоритет /local, секции и readonly. Глобальные и модульные настройки.'
---

# Конфигурация ядра (`.settings.php`)

Baseline: main 23.0+ · Verified: main 26.1000.0

Перед PHP-кодом прочитай `bitrix-framework`, если ещё не читал его в этой задаче.
В таблице выбери только нужный `rules/*.md`; пути относительны каталогу скилла.

| Задача | Читать |
| --- | --- |
| Секции `connections`, `exception_handling`, `cookies`, `crypto`, `ui` и другие | `rules/sections.md` |
| Почта через SMTP (`smtp`, подключения отправителей) | `rules/smtp.md` |

## Какой файл читается

- Основной: `/local/.settings.php`, если есть (**Since main 24.100**), иначе `/bitrix/.settings.php`. Читается один. Файл `/local` **заменяет** ядерный: перенеси все секции, включая `connections`.
- Дополнительный: `/local/.settings_extra.php`, иначе `/bitrix/.settings_extra.php`. Читается один после основного; каждая секция верхнего уровня заменяется целиком, без глубокого слияния.
- Секции проекта помещай в `/local/.settings_extra.php`; перенеси нужные секции из скрываемого `/bitrix/.settings_extra.php`.
- Ядро/админка (`Configuration::setValue()`, формы настроек) записывают данные основного файла через `var_export`: замыкания ломаются при следующей записи. Дополнительный файл не перезаписывается.
- Проверка системы и `services/quickway.php` читают только `/bitrix/.settings.php`: сохраняй его корректным. `IsConfigFile()` запрещает файловому менеджеру только `/bitrix/.settings.php`, но не `/local/.settings*.php`.
- `php_interface/dbconn.php` по-прежнему нужен (`/local` **Since main 24.100**, раньше `/bitrix`).

## Формат

`'section' => ['value' => [...], 'readonly' => true]`: секции `readonly` пропускаются при `Configuration::add()`/`delete()` и в административных формах. Чтение: `Configuration::getValue('section')`.

## Глобальные и модульные настройки

| Файл | Секции |
| --- | --- |
| Глобальный основной/дополнительный | `connections`, `cache`, `cache_flags`, `session`, `cookies`, `crypto`, `exception_handling`, `routing`, `messenger`, `loggers`, `composer`, `http_client_options`, `pull`, `smtp`, `ui`, `default_language`, `services` |
| `/local/modules/<id>/.settings.php` (пока модуль установлен) | `services`, `controllers`, `console`, `messenger`, `rest` (v3) |

Секции `validation` нет (`bitrix-validation`). `routing` доступен только глобально (`bitrix-routing`).
