---
name: bitrix-project-structure
description: 'Используй для размещения кода Битрикса в /local и диагностики загрузки классов: php_interface, Loader::includeModule, PSR-4 модулей, Composer и приоритет файлов.'
---

# Структура проекта и автозагрузка

Baseline: main 23.0+ · Verified: main 26.1000.0

Перед PHP-кодом прочитай `bitrix-framework`, если ещё не читал его в этой задаче.

**Since main 26.900** проверка системы требует PHP 8.2+ и расширение Tokenizer (`token_get_all`). При обновлении проверь доступность в HTTP и CLI; старые версии ядра оценивай по их требованиям, не отключай проверку для обхода несовместимости.

## Корневые каталоги

- `/bitrix/` — ядро и модули Marketplace; заменяются обновлениями. Не редактируй (`bitrix-framework`).
- `/local/` — весь код проекта; одинаковый относительный путь в `/local/` приоритетнее `/bitrix/`.
- `/upload/` — файлы пользователей и модулей.

```
/local/
├── modules/<vendor>.<module>/   # код проекта находится в модулях (bitrix-modules)
├── components/<ns>/<name>/      # bitrix-components
├── templates/<site_tpl>/        # шаблоны сайта и переопределения шаблонов компонентов
├── routes/web.php               # bitrix-routing
├── js/<vendor>/<module>/<ext>/  # ID расширения vendor.module.ext (bitrix-extensions)
├── activities/                  # действия bizproc (также activities/custom)
├── gadgets/<ns>/<name>/         # гаджеты рабочего стола
├── blocks/                      # блоки landing
├── php_interface/
│   ├── init.php                 # каждый хит, до кода страницы
│   ├── dbconn.php               # здесь Since main 24.100; константы ядра
│   ├── after_connect_d7.php     # только после подключения default к БД
│   └── user_lang/<lang>/lang.php # подмена фраз
├── .settings.php                # заменяет /bitrix/.settings.php (bitrix-settings)
└── .settings_extra.php
```

Защити `/local/php_interface/` от HTTP-доступа так же, как `/bitrix/php_interface/`.

## Файлы `php_interface`

- `init.php`: только константы, нужные до модулей, и `addEventHandler()` для событий, не принадлежащих модулю. Остальное размещай в модуле (`install/`, `include.php`, `.settings.php`).
- `after_connect_d7.php` подключается внутри объекта соединения `default`: используй `$this->queryExecute("SET …")` (кодировка, `sql_mode`, часовой пояс). Выполняется один файл: копия `/local` отключает `/bitrix`, поэтому перенеси его инструкции. Другие соединения запускают свой файл через `'include_after_connected' => '/abs/path.php'` в `connections`.

## Загрузка модуля (`Loader`)

| Ситуация | Вызов |
| --- | --- |
| Модуль обязателен; отсутствие — ошибка | `Loader::requireModule('vendor.module')` — выбрасывает `LoaderException` |
| Необязательная интеграция | `if (Loader::includeModule('crm')) { … }` |
| Статус пробного/демонстрационного модуля | `Loader::includeSharewareModule()` |

`includeModule()` возвращает `false`, если модуль не зарегистрирован в `b_module` (наличия файлов недостаточно) либо его `include.php` вернул `false`. При успехе регистрирует корень PSR-4, подключает `include.php` и регистрирует `services` модуля. Загружай один раз во входе (контроллер, агент, команда), а не внутри сервисов. `CModule::IncludeModule()` — только совместимость.

## PSR-4 в `lib/` модуля

- Корень: `vendor.module` → `Vendor\Module\` → `lib/`; однословный `mymodule` → `Bitrix\Mymodule\`.
- Каталог соответствует сегменту пространства имён, файл — классу: `Vendor\Module\Public\Service\PostService` → `lib/Public/Service/PostService.php`.
- Loader пробует точный регистр пути, затем нижний (ядро смешивает стили; с main 26.600 многие `lib/` используют PascalCase). `PostTable` также ищется как `post.php`.
- Не подключай `lib/` ядра напрямую через `require`: обновления переименовывают файлы (например, lists 26.200 перешёл на PascalCase).
- Старый код без PSR-4: `Loader::registerNamespace('Vendor\\Legacy', $absDir)` или `Loader::registerAutoLoadClasses('vendor.module', ['COldClass' => 'classes/old.php'])` в `include.php`.

## Composer

- `composer.json` размещай вне `DOCUMENT_ROOT` (например, в корне проекта); `.settings.php` → `'composer' => ['value' => ['config_path' => '../composer.json']]`. Ядро подключает его `vendor/autoload.php`.
- Нужен для `bitrix.php` (Symfony Console): `bitrix-console-commands`.
- Не устанавливай пакеты в `/bitrix/`.

## Приоритет файлов

- Компоненты, шаблоны сайта, `php_interface`, `.settings*.php`: сначала `/local/`, затем `/bitrix/`.
- Шаблон компонента в `components/…` шаблона сайта приоритетнее собственных `templates/` компонента.
- Модули: при наличии обоих используется `/local/modules/<id>`.
- Маршруты модуля не загружаются автоматически: подключай их через `require` из `/local/routes/web.php` (`bitrix-routing`).
