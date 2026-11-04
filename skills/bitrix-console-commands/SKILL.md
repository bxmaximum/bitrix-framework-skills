---
name: bitrix-console-commands
description: 'Используй для CLI Битрикса bitrix.php: генерация make:*, orm:annotate, свои команды Symfony Console, cron и JSON-вывод. Регистрация в .settings.php и DI по версиям ядра.'
---

# Консоль Битрикса (`bitrix.php`)

Baseline: main 23.0+ · Verified: main 26.1000.0

Перед PHP-кодом прочитай `bitrix-framework`, если ещё не читал его в этой задаче.
В таблице выбери только нужный `rules/*.md`; пути относительны каталогу скилла.

```bash
cd /var/www/site && php bitrix/bitrix.php list        # или: cd bitrix && php bitrix.php list
php bitrix/bitrix.php help make:service
php bitrix/bitrix.php make:service Post -m vendor.blog -n   # -n: без вопросов
```

| Задача | Читать |
| --- | --- |
| Генераторы `make:*`, наличие в 25.x, пути результата | `rules/generators.md` |
| Команды ядра: `orm:annotate`, `messenger:consume`, `update:*`, `main:*` | `rules/generators.md` |
| Своя команда, регистрация, cron | `rules/custom-command.md` |

## Обязательные правила

- Нужен Symfony Console из Composer: `composer.json` с `symfony/console` и `composer.config_path` в `.settings.php` (`bitrix-settings`); иначе `bitrix.php` выводит "Symfony Console is not installed".
- `DOCUMENT_ROOT` определяется из `getcwd()` и пути скрипта: запускай из корня сайта или `bitrix/`, а не `php /abs/path/bitrix.php` из другого каталога.
- Команды берутся только из `.settings.php` установленных модулей → `console.commands`; глобальный `/local/.settings.php` их не регистрирует.
- **Since main 26.900** команды создаются через `ServiceLocator::getInstance()->get($commandClass)`: доступен DI конструктора. На старом ядре используется `new $commandClass()`; получай сервис в `execute()` (`bitrix-framework`).
- В cron и CI всегда указывай `-n`/`--no-interaction`.
