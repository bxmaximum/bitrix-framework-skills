# Bitrix Framework Skills: скиллы для ИИ-агентов на 1С-Битрикс D7 и Битрикс24

Скиллы для Claude Code, Cursor и Codex, чтобы ИИ писал код на 1С-Битрикс по канону D7, а не legacy. 50 скиллов: ORM, контроллеры, кеш, безопасность, инфоблоки, компоненты, интернет-магазин и Битрикс24 (CRM, задачи, REST, MCP). Ставятся одной командой в любой проект.

## Зачем: нейросеть пишет на Битриксе по-старому

Без подсказок нейросеть пишет для Битрикса старым API: `CIBlockElement::GetList`, `$DB->Query` со склейкой SQL, глобальные `$USER` и `$APPLICATION`, код вне `/local/`. Такой код работает, но его трудно поддерживать, а безопасность и кеш остаются на совести разработчика. Скиллы дают агенту канон D7: ORM, контроллеры, сервисы через DI, проверки CSRF и прав, тегированный кеш. Агент открывает нужный скилл сам, когда задача его касается.

Это не официальная документация Битрикса, а правила, как писать код в `/local/`. Документация по продукту — на [docs.1c-bitrix.ru](https://docs.1c-bitrix.ru/) («1С-Битрикс: Управление сайтом») и [apidocs.bitrix24.ru](https://apidocs.bitrix24.ru/) (REST Битрикс24).

## Установка в Claude Code, Cursor и Codex

```bash
npx skills add bxmaximum/bitrix-framework-skills --skill '*'
```

Запустите в корне проекта. CLI [skills](https://www.npmjs.com/package/skills) спросит, в какие агенты ставить. Скиллы попадут в `.agents/skills/` (для Claude Code — ещё и в `.claude/skills/`).

### Другие способы

Без вопросов, сразу в нужные агенты (Cursor, Codex и другие читают общую папку `.agents/skills/`):

```bash
npx skills add bxmaximum/bitrix-framework-skills --skill '*' -a claude-code cursor -y
```

Попросить агента:

```txt
Добавь в проект скиллы из репозитория https://github.com/bxmaximum/bitrix-framework-skills
```

Только нужные скиллы или список без установки:

```bash
npx skills add bxmaximum/bitrix-framework-skills --skill bitrix-orm bitrix-controllers
npx skills add bxmaximum/bitrix-framework-skills --list   # список без установки
```

Обновление:

```bash
npx skills update
```

Флаг `--all` не советуем: это `--skill '*' --agent '*' -y`, скиллы ставятся сразу во все ~60 поддерживаемых агентов, и в проекте появляются десятки папок вроде `.windsurf/`, `.roo/`.

### AGENTS.md

Общие правила — `/local/`, D7, границы DI, безопасность, политика версий — лежат в скилле `bitrix-framework`. Он ставится вместе с остальными, поэтому отдельный системный промпт не обязателен.

[AGENTS.md](AGENTS.md) — короткий системный промпт: язык ответов, порядок работы и индекс скиллов. Его можно скопировать в корень проекта или подключить как rule в Cursor.

## Пример: выборка из инфоблока без скиллов и со скиллами

Задача: 10 активных новостей автора «Иван Петров».

Без скиллов агент часто пишет так:

```php
CModule::IncludeModule('iblock');

$res = CIBlockElement::GetList(
    ['SORT' => 'ASC'],
    ['IBLOCK_ID' => 5, 'ACTIVE' => 'Y', 'PROPERTY_AUTHOR' => 'Иван Петров'],
    false,
    ['nTopCount' => 10],
    ['ID', 'NAME', 'PREVIEW_TEXT', 'PROPERTY_AUTHOR']
);
while ($row = $res->GetNext()) {
    $items[] = $row;
}
```

Это работает, но ID инфоблока зашит в код и на другом стенде будет другим. Свойства идут через `PROPERTY_*`, результат — массивы без типов.

Со скиллом `bitrix-iblocks` агент берёт ORM D7:

```php
use Bitrix\Iblock\Elements\ElementNewsTable;
use Bitrix\Main\Loader;

Loader::includeModule('iblock');

$news = ElementNewsTable::query()
    ->setSelect(['ID', 'NAME', 'PREVIEW_TEXT', 'AUTHOR'])
    ->where('ACTIVE', 'Y')
    ->where('AUTHOR.VALUE', 'Иван Петров')
    ->setOrder(['SORT' => 'ASC'])
    ->setLimit(10)
    ->setCacheTtl(3600)
    ->cacheJoins(true)
    ->fetchCollection();
```

- Класс `ElementNewsTable` ядро создаёт само для инфоблока с `API_CODE = News`. Инфоблок находится по коду, без ID.
- Свойство `AUTHOR` — поле ORM: его можно выбрать и отфильтровать (`AUTHOR.VALUE`).
- Кеш запроса включён вместе с JOIN свойств.
- Результат — коллекция объектов: `$item->getName()`, `$item->getAuthor()?->getValue()`.

Скилл заодно предупреждает: ORM не проверяет права на инфоблок и даты активности, это нужно сделать отдельно. Пример взят из `skills/bitrix-iblocks/rules/`. Оба фрагмента проходят `php -l` и `tools/verify-skills.php` на main 26.800.0.

## Каталог скиллов

Каждый скилл — `skills/<name>/SKILL.md`. Большие скиллы разбиты на темы в `rules/*.md`: агент читает только нужный файл, поэтому 50 скиллов не забивают контекст.

| Область | Skill |
| --- | --- |
| Каноны, версии, DI, безопасность — начать отсюда | `bitrix-framework` |
| Структура проекта, Loader, `/local` | `bitrix-project-structure` |
| Секции `.settings.php` ядра | `bitrix-settings` |
| Создание модулей, install/uninstall | `bitrix-modules` |
| CLI, `make:*`, cron, команды | `bitrix-console-commands` |
| Контроллеры, actions, фильтры | `bitrix-controllers` |
| Роутинг, генерация URL | `bitrix-routing` |
| Application, Context, Request/Response | `bitrix-request-response` |
| Валидация, DTO на атрибутах | `bitrix-validation` |
| CSRF, XSS, SQL-инъекции, SSRF, права, JWT | `bitrix-security` |
| Сессии, separated mode | `bitrix-sessions` |
| ServiceLocator, DI | `bitrix-service-locator` |
| Result, Error, ErrorCollection | `bitrix-result-and-errors` |
| События (D7 и legacy) | `bitrix-events` |
| Агенты, background jobs, Messenger | `bitrix-background-jobs` |
| PSR-3 logging | `bitrix-logger` |
| ORM: сущности, запросы, связи, объекты | `bitrix-orm` |
| SQL, транзакции, SqlHelper | `bitrix-database` |
| PostgreSQL: подключение, переход с MySQL | `bitrix-postgresql` |
| Кеш: Data\Cache, тегированный, кеш ORM и компонентов | `bitrix-caching` |
| Настройки модулей, фича-флаги, хранилище с TTL | `bitrix-storage` |
| Миграции БД/схемы ([sprint.migration](https://marketplace.1c-bitrix.ru/solutions/sprint.migration/)) | `bitrix-sprint-migration` |
| Date/DateTime, часовые пояса | `bitrix-datetime` |
| Локализация, Loc | `bitrix-localization` |
| HttpClient, SSRF, GeoIP | `bitrix-http-client` |
| Почтовые события, SMTP | `bitrix-mail` |
| Файлы, `CFile`, загрузка | `bitrix-files` |
| Производительность, композит, реплики | `bitrix-performance` |
| Компоненты, шаблоны, SEF | `bitrix-components` |
| JS/CSS extensions | `bitrix-extensions` |
| UI kit (popup, sidepanel, grid, формы) | `bitrix-ui` |
| BitrixVue 3 | `bitrix-vue` |
| Pull-сервер, real-time | `bitrix-pull` |
| CMS: сайты, шаблоны, меню, пагинация, пользовательские поля | `bitrix-cms-basics` |
| Инфоблоки, свойства, SEO-шаблоны | `bitrix-iblocks` |
| Highload-блоки | `bitrix-highloadblock` |
| Landing, конструктор страниц | `bitrix-landing` |
| sitemap.xml, robots.txt, вебмастера | `bitrix-seo` |
| Модуль поиска, индексация | `bitrix-search` |
| Генератор документов | `bitrix-documentgenerator` |
| Торговый каталог, цены, SKU | `bitrix-catalog` |
| Интернет-магазин, заказы, оплата | `bitrix-sale` |
| Бизнес-процессы | `bitrix-bizproc` |
| Свои REST-методы модуля, REST 3.0 | `bitrix-rest` |

Битрикс24:

| Область | Skill |
| --- | --- |
| REST API снаружи: вебхуки, OAuth, batch, лимиты, события, REST 3.0 | `bitrix24-rest-api` |
| Официальный MCP документации, Vibecode | `bitrix24-ai-tools` |
| CRM в коробке: Factory, Item, смарт-процессы, права, события | `bitrix24-crm` |
| Задачи в коробке | `bitrix24-tasks` |
| Чаты, уведомления, боты в коробке | `bitrix24-im` |
| Кастомизация портала: страницы, меню, структура, диск | `bitrix24-portal` |

## MCP для Битрикс24: документация REST в агенте

Для работы с REST Битрикс24 подключите официальный MCP-сервер документации: он ищет методы, поля и события в актуальной документации REST. Доступа к данным портала у него нет, авторизация не нужна.

```bash
claude mcp add --transport http b24-dev-mcp https://mcp-dev.bitrix24.com/mcp
```

Настройка для Cursor, Codex и других клиентов — в скилле `bitrix24-ai-tools`.

## Как проверяются скиллы

Скиллы сверены с ядром коробки Битрикс24 **main 26.800.0**. Каждый PHP-пример проходит `php -l`, а классы, методы, константы и именованные аргументы проверяются по исходникам ядра скриптом [`tools/verify-skills.php`](tools/verify-skills.php). Скрипт не видит неверного поведения, поэтому рискованные утверждения в изменённых скиллах перепроверяются по коду ядра вручную.

Базовые паттерны рассчитаны на **main 23.0+**. Всё, что новее, помечено `Since` с вариантом для старого ядра.

## Какая нейросеть лучше пишет код на 1С-Битрикс

[Bitrix AI Challenge](https://bxmax.ru/bitrix-ai/challenge) — бенчмарк BXMax. Связки «приложение + модель + скиллы» (Claude Code, Codex, Cursor и другие) делают один и тот же реальный заказ веб-студии на живом стенде 1С-Битрикс: лендинг с онлайн-записью, инфоблоками и модулем записи. Результат оценивают модель-ревьювер и автор по одной рубрике.

Все участники получают на стенде одни и те же скиллы из этого репозитория, коммит записан в `run.yaml` каждого прогона: с октября 2026 — релиз 2026-10.

Вклад скиллов отдельно не измеряется: бенчмарк оценивает связку целиком. Методология и код прогонов — в [bxmaximum/bitrix_ai_challenge](https://github.com/bxmaximum/bitrix_ai_challenge).

## Обновления и участие

Скиллы обновляются раз в месяц: после релиза [документации](https://github.com/bitrix-tools/framework-docs), по разборам обновлений ядра и по issue сообщества. Что изменилось — в [CHANGELOG.md](CHANGELOG.md). Как устроен скилл, как его проверить и как прислать правку — в [CONTRIBUTING.md](CONTRIBUTING.md).

```bash
php tools/verify-skills.php --kernel=/path/to/bitrix/modules
```

## Связанные проекты

- [bxmaximum/bitrix_ai_challenge](https://github.com/bxmaximum/bitrix_ai_challenge) — исследование AI-моделей на Bitrix; публикации на [bxmax.ru/bitrix-ai](https://bxmax.ru/bitrix-ai).
- [bitrix-tools/best-practice](https://github.com/bitrix-tools/best-practice) — operational best practices от команды Bitrix Tools.

## BXMax

Скиллы поддерживает [BXMax](https://bxmax.ru) — сообщество разработчиков 1С-Битрикс. Мы разбираем ядро и проверяем ИИ-агентов на реальных задачах, по этим задачам и собираем скиллы.

| Раздел | Что внутри |
| --- | --- |
| [Блог](https://bxmax.ru/blog) | Разборы API, туториалы, мнения о ядре |
| [Кофе && Код](https://bxmax.ru/coffee-code) | Короткие практические советы по D7 |
| [Bitrix × AI](https://bxmax.ru/bitrix-ai) | Сравнение AI-моделей на реальных ТЗ |
| [Telegram](https://t.me/bxmaximum) | Анонсы материалов, скиллов и обновлений |

**Ссылки:** [bxmax.ru](https://bxmax.ru) · [Telegram](https://t.me/bxmaximum) · [Bitrix × AI](https://bxmax.ru/bitrix-ai) · [GitHub](https://github.com/bxmaximum)

## Лицензия

[MIT](LICENSE)
