---
name: bitrix-modules
description: 'Используй для своих модулей Битрикса в /local/modules: CModule с DoInstall/DoUninstall, version.php, make:module, таблицы, обработчики, агенты, настройки и автозагрузка.'
---

# Модули Битрикса

Baseline: main 23.0+ · Verified: main 26.1000.0

Перед PHP-кодом прочитай `bitrix-framework`, если ещё не читал его в этой задаче.
В таблице выбери только нужный `rules/*.md`; пути относительны каталогу скилла.

| Задача | Читать |
| --- | --- |
| Новый модуль: ID, каркас, `install/index.php`, версия, языки, удаление | `rules/installer.md` |
| Архитектура `lib/`, границы слоёв | `rules/architecture.md` |
| Таблицы, события, агенты в `install/migrations/*.php` (main 26.600+) | `rules/install-migrations.md` |
| Страница настроек, `default_option.php`, PSR-4, маршруты модуля | `rules/options-autoload.md` |

## Обязательные правила

- Каталог `/local/modules/<vendor>.<module>/`; ID в нижнем регистре `vendor.module`; класс установщика `vendor_module`; пространство имён `Vendor\Module` (однословный ID `mymodule` → `Bitrix\Mymodule`).
- Для нового модуля используй PSR-4 в `lib/` с точным регистром путей. Исключение допустимо только для контроллера с фиксированным строчным сегментом в AJAX action (`bitrix-controllers`).
- До регистрации модуля в `b_module` загрузка не работает: `Loader::includeModule()` возвращает `false`, `.settings.php` модуля (`services`, `controllers`, `console`, `messenger`) не читается.
- Установка и удаление симметричны: у каждой созданной таблицы, регистрации, агента и настройки есть путь удаления; повторная установка не должна падать.
- При регистрации событий используй имена параметров ядра (`fromModuleId:`) или позиционные аргументы; сигнатура обработчика зависит от отправителя (`bitrix-events`).
- Маршруты модуля подключаются через `require` из `/local/routes/web.php`; секция `routing` в `.settings.php` модуля игнорируется (`bitrix-routing`).
- Обязательные ресурсы модуля, включая его инфоблоки, создавай в установщике. Структуру и контент проекта, настройки окружения и обновления действующих установок веди через `bitrix-sprint-migration`.
