# Changelog

Скиллы обновляются раз в месяц. Версия релиза — год и месяц.

## 2026-10

Сверено с ядром коробки Битрикс24 **main 26.800.0**, документацией [framework-docs](https://github.com/bitrix-tools/framework-docs) по `435802d` (28.09.2026), REST-документацией Битрикс24 от 30.09.2026 и разборами обновлений ядра за сентябрь.

### Новое

- `bitrix-framework` — каноны, границы DI, политика версий и пометки `Since`. Раньше они жили только в `AGENTS.md`, который `npx skills` не устанавливает.
- Битрикс24:
  - `bitrix24-rest-api` — REST снаружи и из локальных приложений: вебхуки, OAuth, batch, пагинация, лимиты, события, placements, REST 3.0, SDK, особенности коробки;
  - `bitrix24-ai-tools` — официальный MCP документации `b24-dev-mcp`, Vibecode и `vibecodeconnector` для коробки;
  - `bitrix24-crm` — Factory, Item, операции, смарт-процессы, права, события, таймлайн, пользовательские поля;
  - `bitrix24-tasks` — V2-команды, чтение, права, события;
  - `bitrix24-im` — сообщения, чаты, уведомления, боты;
  - `bitrix24-portal` — страницы и слайдер, левое меню, пользователи и оргструктура (humanresources), диск, группы.
- `bitrix-documentgenerator`, `bitrix-search` — по новым разделам документации.
- `bitrix-mail`, `bitrix-files` — почтовые события и SMTP; файлы, загрузка и выдача.
- `tools/verify-skills.php` — `php -l` для каждого PHP-примера, сверка классов, методов, констант и именованных аргументов с индексом исходников ядра, проверка frontmatter, размеров и ссылок.
- `CONTRIBUTING.md` — формат скилла, бюджеты размеров, пометки версий, процесс ежемесячного обновления.

### Изменено

- Все описания скиллов ≤ 250 символов: они загружаются в каждую сессию. Описания в одинарных кавычках: без них `: ` и ` #` ломали разбор YAML, и `npx skills` пропускал скилл или обрезал описание. `tools/verify-skills.php` проверяет это.
- Толстые скиллы стали роутерами с таблицей «задача → rules-файл».
- В каждом `SKILL.md` строка `Baseline / Verified` или `Scope / Verified`. Пометки `Since main X.Y*` для функций, точная версия которых неизвестна.
- `AGENTS.md` сокращён до языка ответов, порядка работы и индекса скиллов.
- README: установка через `--skill '*'` вместо `--all`, который ставил скиллы во все ~60 поддерживаемых агентов разом.
- Токены: описания всех скиллов вместе — 11,4 КБ вместо 13,8 КБ, хотя скиллов стало 50 вместо 39; средний `SKILL.md` — 2,7 КБ вместо 6,4 КБ. Новый API лежит в `rules/*.md` и загружается только по задаче.
- Роутер + rules: `bitrix-ui` (13 файлов), `bitrix-sale`, `bitrix-highloadblock`, `bitrix-cms-basics`, `bitrix-bizproc`, `bitrix-database`, `bitrix-http-client`, `bitrix-validation`, `bitrix-settings`, `bitrix-modules`, `bitrix-console-commands`, `bitrix-landing`, `bitrix-rest`.
- По документации с 21.08: UI-кит (кнопки, тулбар, формы, счётчики, метки, tooltip, viewer, stageflow, timeline, entity-catalog, uploader), постраничная навигация, пользовательские поля и свои типы, раздел Highload-блоков, публичные страницы на контроллерах, DISTINCT и `Table::query()` в ORM, поддержка модулей в PostgreSQL.
- По обновлениям ядра за сентябрь: установщики модулей на `install/migrations` (`bitrix-modules`), фича-флаги `Config\Feature` (`bitrix-storage`), `LoggerFactory`, ломающие изменения main 26.800 (DDL-билдеры, ключи коллекций ORM, `CUtil::translit`, `Loc`, `MakeFileArray` для ftp), landing 26.1000–26.1200, bizproc до 26.1400, REST 3.0.

### Исправлено

Спасибо [@warenikov](https://github.com/warenikov) за сверку в [#1](https://github.com/bxmaximum/bitrix-framework-skills/issues/1).

Из issue и нашей сверки (каждый пункт проверен по коду ядра):

- **controllers:** `#[HttpMethod([HttpMethod::METHOD_GET])]` на классе-атрибуте ломал все actions контроллера; неверный порядок autowire (`HttpRequest`, `Session`, интерфейсы и `EntityObject` по id не подставляются); формат ответа при `null`; `#[Authentication]`/`#[Csrf]` поверх дефолтов выполнялись дважды; регистр сегмента контроллера при PascalCase-файлах.
- **routing / request-response:** несуществующие `Response\Component::createByComponentName()`, `Engine\Response\Html`, `isGet()`/`isPut()`, `$routes->head()`, синтаксис `{param?}`; `AjaxJson::createError(Error)`; порядок urlrewrite и маршрутов.
- **service-locator / background-jobs:** регистрация конкретного класса только через `className` отключает autowire; обработчик Messenger не обязан быть в `services`; `parent::__construct()` в наследнике `AbstractReceiver`; глобальная очередь без `module`; долгий агент после 10 минут запускается параллельно, а не блокируется; background jobs выполняются после отправки ответа.
- **events / modules:** именованный аргумент `fromModule:` → `fromModuleId:`; legacy-события меняют поля через `&$arFields`, возврат `FIELDS` игнорируется; сырые аргументы в `ExecuteModuleEventEx`; поведение `make:event`/`make:eventhandler`; `CopyDirFiles` без рекурсии.
- **settings / project-structure:** `/local/.settings.php` и `.settings_extra.php` заменяют файлы из `/bitrix`, а не сливаются; `after_connect_d7.php` только для соединения `default`.
- **console-commands:** какие `make:*` были до 25.900; реальные пути генераторов (`lib/Tablet`, `lib/Public/Service`, `lib/Internals/Entity`).
- **database / orm:** `SqlTracker::stop()`, `insertBatch`, `?v`, `convertToDbString`; у коллекции нет `delete()`; `addMerge`/`addInsertIgnore` вызывают события; `Since` для `deleteByFilter(ConditionTree)`, `AddMerge`/`InsertIgnore`, `find`/`filter`/`walk`.
- **caching / performance:** `<div data-dynamic>` и `SetPageProperty('composite_frame_mode')` не существуют; конфиг кеша без `class_name` молча отключал кеш; `ManagedCache::set()` без `read()`.
- **datetime / localization:** `Date` не наследует `\DateTime`; маски форматов; строки культуры в PHP `format()`; `getNumberDecimalSeparator()`; `lang_additional` и `BX.message`; `getDefaultLang('ua')` → `en`; даты для `CAgent::AddAgent` — в пользовательском времени.
- **http-client:** `HttpDebug::NONE`, `Promise::all()`, `getError()`; `getRealIp()` доверяет `X-Forwarded-For`.
- **extensions / vue / pull:** путь `/local/js/vendor/module/ext/`; `BX.Vue3.BitrixVue` вместо Vue 2 `BX.BitrixVue`, импорт из `ui.vue3`; сигнатуры колбэка `BX.PULL.subscribe`; `CPullWatch::Add()` для другого пользователя.
- **iblocks / highloadblock / catalog / sale:** `VERSION` по умолчанию 1; `TABLE_NAME` справочника; неизвестные ключи HL-блока отбрасываются молча; двойные имена событий HL; `PriceTable::add` → `Model\Price::add`; параметры `DiscountCouponsManager::init`.
- **landing / seo / postgresql / cms-basics:** `skip_files`, `addBlock()` возвращает id, `MAINPAGE` → `VIBE`; семантика `Sitemap\Generator::run()`; мастер PostgreSQL блокирует, а не отключает модули; `UserFieldTable` только для чтения.
- **security / components:** 2FA по умолчанию выключена; `SECURE_LEVEL_MIDDLE`; `\Bitrix\Main\Captcha` не существует; `make:component --local` не создаёт `/local/components`.
- **sprint.migration:** несуществующие `deleteOption`, `saveEvent`, `saveSite`; `get*IfExists` бросает исключение; конфиги `migrations.<name>.php` без явных каталога и таблицы пишут в общую; `down` без аргументов откатывает всё.

Не подтвердилось: SMTP не включается одним `host` — нужен `smtp.enabled === true`, раздел `bitrix-settings` был верен.
