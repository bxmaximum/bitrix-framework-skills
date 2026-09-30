# Bitrix Framework Skills

Набор AI-скиллов для разработки на **1С-Битрикс / Bitrix Framework** (D7) и **Битрикс24** (облако и коробка): ORM, контроллеры, роутинг, кеш, безопасность, компоненты, REST, Sale, CRM, задачи, чаты, миграции (`sprint.migration`) и другие темы ядра.

Скиллы собирает и поддерживает сообщество **[BXMax](https://bxmax.ru)** — платформа для разработчиков 1С-Битрикс: практические материалы, разборы ядра, эксперименты с AI и обмен опытом между коллегами. Это не официальная документация Битрикс, а концентрированные operational-правила для AI-агентов при проектировании и написании кода в `/local/`.

**BXMax:** [bxmax.ru](https://bxmax.ru) · [Telegram](https://t.me/bxmaximum) · [Bitrix × AI](https://bxmax.ru/bitrix-ai)

Официальная документация по продукту:

- [docs.1c-bitrix.ru](https://docs.1c-bitrix.ru/) — «1С-Битрикс: Управление сайтом».
- [apidocs.bitrix24.ru](https://apidocs.bitrix24.ru/) — REST «Битрикс24».

Скиллы сверены с ядром коробки Битрикс24 **main 26.800.0**: каждый PHP-пример проходит `php -l`, а классы, методы, константы и именованные аргументы проверяются по исходникам ядра скриптом [`tools/verify-skills.php`](tools/verify-skills.php). Базовые паттерны рассчитаны на **main 23.0+**; всё, что новее, помечено `Since` с вариантом для старого ядра.

## Каноны и AGENTS.md

Общие правила — `/local/`, D7, границы DI, безопасность, политика версий — лежат в скилле `bitrix-framework`. Он ставится вместе с остальными, поэтому отдельный системный промпт не обязателен.

[AGENTS.md](AGENTS.md) — короткий системный промпт для проекта: язык ответов, порядок работы и индекс скиллов. Его можно скопировать в корень Bitrix-проекта или подключить как rule в Cursor.

## Skills

Каждый skill — самодостаточный справочник в `skills/<name>/SKILL.md`. После установки через `npx skills` попадает в `.agents/skills/<name>/` целевого проекта.

«Толстые» скиллы — это роутеры: агент открывает `SKILL.md` с таблицей «задача → файл», затем только нужный файл из `rules/*.md`. Описания скиллов короче 250 символов: они попадают в контекст каждой сессии, и мы следим, чтобы набор из 50 скиллов не съедал лишние токены: все описания вместе занимают около 11 КБ, средний `SKILL.md` — около 2,7 КБ.

Каталог:

| Область | Skill |
| --- | --- |
| Каноны, версии, DI, безопасность — начать отсюда | `bitrix-framework` |
| Структура проекта, Loader, `/local` | `bitrix-project-structure` |
| Секции `.settings.php` ядра | `bitrix-settings` |
| Создание модулей, install/uninstall | `bitrix-modules` |
| CLI, `make:*`, cron, команды | `bitrix-console-commands` |
| Контроллеры, actions, filters | `bitrix-controllers` |
| Роутинг, URL generation | `bitrix-routing` |
| ORM, tablets, queries | `bitrix-orm` |
| События (new + legacy) | `bitrix-events` |
| Валидация, DTO attributes | `bitrix-validation` |
| ServiceLocator, DI | `bitrix-service-locator` |
| Кеш, composite | `bitrix-caching` |
| Производительность | `bitrix-performance` |
| CSRF, XSS, SQLi, JWT | `bitrix-security` |
| Агенты, background jobs, Messenger | `bitrix-background-jobs` |
| Result, Error, ErrorCollection | `bitrix-result-and-errors` |
| Компоненты, templates, SEF | `bitrix-components` |
| Инфоблоки, свойства, SEO | `bitrix-iblocks` |
| Модуль поиска, индексация | `bitrix-search` |
| Генератор документов | `bitrix-documentgenerator` |
| Highload-блоки | `bitrix-highloadblock` |
| Торговый каталог, цены, SKU | `bitrix-catalog` |
| Интернет-магазин, заказы, оплата | `bitrix-sale` |
| REST API, OAuth | `bitrix-rest` |
| Pull-сервер, real-time | `bitrix-pull` |
| Landing, конструктор страниц | `bitrix-landing` |
| SEO, мета, sitemap | `bitrix-seo` |
| Бизнес-процессы | `bitrix-bizproc` |
| HttpClient, SSRF, GeoIP | `bitrix-http-client` |
| Почтовые события, SMTP | `bitrix-mail` |
| Файлы, `CFile`, загрузка | `bitrix-files` |
| PSR-3 logging | `bitrix-logger` |
| Локализация, Loc | `bitrix-localization` |
| Date/DateTime, timezones | `bitrix-datetime` |
| Application, Context, Request/Response | `bitrix-request-response` |
| Сессии, separated mode | `bitrix-sessions` |
| SQL, transactions, SqlHelper | `bitrix-database` |
| PostgreSQL migration | `bitrix-postgresql` |
| Persistent Storage (25.1100+) | `bitrix-storage` |
| JS/CSS extensions | `bitrix-extensions` |
| UI kit (popup, sidepanel) | `bitrix-ui` |
| BitrixVue 3 | `bitrix-vue` |
| CMS: sites, menus, templates | `bitrix-cms-basics` |
| Миграции БД/схемы ([sprint.migration](https://marketplace.1c-bitrix.ru/solutions/sprint.migration/)) | `bitrix-sprint-migration` |

Битрикс24:

| Область | Skill |
| --- | --- |
| REST API снаружи: вебхуки, OAuth, batch, лимиты, события, REST 3.0 | `bitrix24-rest-api` |
| Официальный MCP документации, Vibecode | `bitrix24-ai-tools` |
| CRM в коробке: Factory, Item, смарт-процессы, права, события | `bitrix24-crm` |
| Задачи в коробке | `bitrix24-tasks` |
| Чаты, уведомления, боты в коробке | `bitrix24-im` |
| Кастомизация портала: страницы, меню, структура, диск | `bitrix24-portal` |

### Официальный MCP Битрикс24

Для работы с REST Битрикс24 подключите официальный MCP-сервер документации: он ищет методы, поля и события в актуальной документации REST. Доступа к данным портала у него нет, авторизация не нужна.

```bash
claude mcp add --transport http b24-dev-mcp https://mcp-dev.bitrix24.com/mcp
```

Настройка для Cursor, Codex и других клиентов — в скилле `bitrix24-ai-tools`.

### Как добавить

Напрямую попросить агента:

```txt
Добавь в проект скиллы из репозитория https://github.com/bxmaximum/bitrix-framework-skills
```

Через [skills](https://www.npmjs.com/package/skills) — все скиллы, CLI спросит, в какие агенты ставить:

```bash
npx skills add bxmaximum/bitrix-framework-skills --skill '*'
```

Без вопросов, сразу в нужные агенты (Cursor, Codex и другие читают общую папку `.agents/skills/`):

```bash
npx skills add bxmaximum/bitrix-framework-skills --skill '*' -a claude-code cursor -y
```

Флаг `--all` не советуем: это `--skill '*' --agent '*' -y`, скиллы ставятся сразу во все ~60 поддерживаемых агентов, и в проекте появляются десятки папок вроде `.windsurf/`, `.roo/`.

Или конкретные:

```bash
npx skills add bxmaximum/bitrix-framework-skills --skill bitrix-orm bitrix-controllers
npx skills add bxmaximum/bitrix-framework-skills --list   # список без установки
```

Обновление:

```bash
npx skills update
```

После установки скиллы попадают в `.agents/skills/` целевого проекта (для Claude Code — ещё и в `.claude/skills/`). [AGENTS.md](AGENTS.md) можно дополнительно скопировать в корень Bitrix-проекта или подключить как rule в Cursor.

## Обновления и участие

Скиллы обновляются раз в месяц: после релиза [документации](https://github.com/bitrix-tools/framework-docs), по разборам обновлений ядра и по issue сообщества. Что изменилось — в [CHANGELOG.md](CHANGELOG.md). Как устроен скилл, как его проверить и как прислать правку — в [CONTRIBUTING.md](CONTRIBUTING.md).

```bash
php tools/verify-skills.php --kernel=/path/to/bitrix/modules
```

## Связанные проекты

- [bxmaximum/bitrix_ai_challenge](https://github.com/bxmaximum/bitrix_ai_challenge) — исследование AI-моделей на Bitrix; публикации на [bxmax.ru/bitrix-ai](https://bxmax.ru/bitrix-ai).
- [bitrix-tools/best-practice](https://github.com/bitrix-tools/best-practice) — operational best practices от команды Bitrix Tools

## About

**[BXMax](https://bxmax.ru)** — первая специализированная платформа для разработчиков 1С-Битрикс. Мы делаем современную разработку на D7 понятнее: от разборов ядра до практики с AI-агентами.

Эти скиллы — часть нашей открытой экосистемы. Их собираем на реальных задачах, проверяем в [Bitrix × AI](https://bxmax.ru/bitrix-ai) и обновляем по мере развития ядра.

### Что есть на BXMax

| Раздел | Что внутри |
| --- | --- |
| [Блог](https://bxmax.ru/blog) | Разборы API, туториалы, мнения о ядре |
| [Кофе && Код](https://bxmax.ru/coffee-code) | Короткие практические советы по D7 |
| [Bitrix × AI](https://bxmax.ru/bitrix-ai) | Сравнение AI-моделей на реальных ТЗ |
| [Telegram](https://t.me/bxmaximum) | Анонсы материалов, скиллов и обновлений |

### Зачем эти скиллы

Официальная документация отвечает на вопрос «что есть в продукте». Наши скиллы отвечают на «как писать в `/local/` правильно»: D7 вместо legacy, сервисный слой, безопасность, кеш, ORM — в формате, который AI-агент может применить сразу.

Если скиллы экономят вам время — [загляните на bxmax.ru](https://bxmax.ru). Там больше материалов, курсов и живого сообщества единомышленников.

**Ссылки:** [bxmax.ru](https://bxmax.ru) · [Telegram](https://t.me/bxmaximum) · [Bitrix × AI](https://bxmax.ru/bitrix-ai) · [GitHub](https://github.com/bxmaximum)

## Лицензия

MIT License
