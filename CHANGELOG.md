# Changelog

Скиллы обновляются раз в месяц. Версия релиза — год и месяц.

## 2026-11

Сверено с ядром коробки Битрикс24 **main 26.1000.0** (снимок от 30.10.2026), sprint.migration 5.16.1, [framework-docs `8eabcce5`](https://github.com/bitrix-tools/framework-docs/commit/8eabcce5bdec633e850f22e7b80b53bacf347a92) и [b24restdocs `a31b9b2b`](https://github.com/bitrix24/b24restdocs/commit/a31b9b2be622f7bbd2ae1c196afa2d07bb2ca907).

### Язык и устройство набора

- Все 50 скиллов переведены на русский. Имена скиллов, классов, методов и путей, а также метки `Baseline`, `Verified` и `Since` не переводились.
- Описания скиллов в среднем короче: 169 символов вместо 231. В каждом, кроме канона, есть 2–4 ключевых класса или метода (`CIBlockElement`, `TaggedCache`, `Mail\Event::send`), чтобы агент находил скилл по вставленному коду.
- Профильные PHP-скиллы просят один раз прочитать `bitrix-framework` перед кодом. При выборочной установке его нужно указывать явно; пример в README исправлен. Клиентскому облачному REST (`bitrix24-rest-api`) канон не нужен.
- Подробности перенесены в отдельные файлы `rules/`: версии и DI канона, кеширование, логирование, каталог, расширения, MCP и Vibecode, серверный REST 3.0, интеграции задач. Агент открывает их по задаче. Канон уменьшился с 1095 до 744 токенов (`o200k_base`).
- Скиллы обращаются к агенту на «ты». В блоке Битрикс24 раньше было «вы».
- В `AGENTS.md` есть индекс скиллов для агентов, которые не показывают модели список установленных скиллов. Для Claude Code добавлен `CLAUDE.md` с импортом `@AGENTS.md`.
- README: сначала зачем нужны скиллы и как их установить, затем пример «без скиллов / со скиллами» (выборка из инфоблока: `CIBlockElement::GetList` против ORM D7), каталог, MCP Битрикс24, проверка и бенчмарк.

### По обновлениям ядра

- **console-commands:** с main 26.900 команды модулей создаёт ServiceLocator, поэтому зависимости можно получать через конструктор. Для старого ядра оставлен вариант с сервисом внутри `execute()`. Описаны команды `main:*` и вывод `--format=json`.
- **background-jobs:** агенты регистрируются через `Type\Callback` (main 26.900) вместо строки PHP-кода; число попыток Messenger.
- **controllers:** с main 26.1000 при `debug=false` текст большинства исключений в ответ не попадает; экспорт компонента через `Controller\Export` требует подписи `signExportParameters()`.
- **orm:** PHP-сериализация `ArrayField` не восстанавливает объекты; `Collection::filter()` бросает исключение только при несохранённом изменении состава коллекции, изменение поля объекта к этому не приводит; для кеша запросов с JOIN нужен `cache_joins`.
- **validation:** явный `null` проходит через правила, неинициализированное nullable-свойство пропускается.
- **sessions:** формат зашифрованных cookie v2 и сброс разделённой сессии при обновлении до main 26.900.
- **documentgenerator:** `Document::actualize()` удалён в 26.400, документ пересобирает `Document::update([])`.
- **search:** команда `search:reindex` и её параметры.
- **ui, files, mail:** `main.core` HttpClient, FileInput (серверная проверка загрузки обязательна), индикаторы и пошаговый UI, checkbox-list, feedback.form, очистка пользовательских полей, почтовые подписи.
- **Битрикс24:** обязательность полей и стадии CRM (`SetRequirementCommand`, crm 26.1800), право скачивания на Диске отдельно от права чтения, контракты задач, бизнес-процессов и IM, новые группы REST 3.0, static placements, offline events и SDK. Лимит SQL-запроса из облачной документации описан отдельно: в проверенной коробке его реализации нет.

### Безопасные значения по умолчанию

- Примеры не включают полное логирование HTTP (`HttpDebug::ALL`) и SMTP: в телах и заголовках бывают токены и пароли.
- Исправление кеша не требует перехода на Redis или Memcached, обычная разработка не меняет настройки 2FA.
- PHP-клиент REST в коробке (`bitrix24-rest-api/rules/php-client.md`) принимает только HTTPS-адрес вебхука, не идёт по редиректам и проверяет HTTP-статус.

### Проверка

- `tools/verify-skills.php` считает размеры в символах, понимает пометку `<!-- verify:negative -->` для заведомо неверного API и работает без ядра в режиме `--files-only`. Добавлены регрессионные тесты скрипта (`tools/test-verify-skills.php`).

### Исправлено

- Устаревший запрет внедрять зависимости в конструктор консольной команды, упоминания `Document::actualize()` и утверждение, что `search:reindex` нет.
- Позиция фильтра в `CUserTypeEntity::GetList`, контракт `saveEventMessage` в sprint.migration 5.16.1, лишние утверждения о несохранённых изменениях коллекции и nullable-валидации.

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
