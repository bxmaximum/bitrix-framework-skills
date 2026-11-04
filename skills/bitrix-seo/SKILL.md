---
name: bitrix-seo
description: 'Модуль seo: sitemap.xml, robots.txt (RobotsFile) и сервисы вебмастеров. Используй при генерации карты сайта, настройке обхода и выборе механизма SEO-редиректов.'
---

# Модуль SEO (`seo`)

Baseline: main 23.0+ · Verified: seo 26.100.0, main 26.1000.0

Перед PHP-кодом прочитай `bitrix-framework`, если ещё не читал его в этой задаче.

Загрузи `Loader::includeModule('seo')` перед классами ниже. Заголовки и описания инфоблоков: InheritedProperty в `bitrix-iblocks` (`rules/query-seo-perf.md`). Счётчики и метаданные лендинга: события в `bitrix-landing`.

## Карта сайта

| Класс | Назначение |
| --- | --- |
| `Bitrix\Seo\Sitemap\Internals\SitemapTable` | Строки `b_seo_sitemap`: `SITE_ID`, `ACTIVE`, `NAME`, `DATE_RUN`, сериализованные `SETTINGS` |
| `Bitrix\Seo\Sitemap\Job` | Фоновая сборка агентом: `markToRegenerate()`, `addJob()`, `doJobAgent()`, `STATUS_*` |
| `Bitrix\Seo\Sitemap\Generator` | Один шаг сборки: `init($step, $state)`, `run()`, `getStep()`, `getState()` |
| `Bitrix\Seo\Sitemap\Type\Step` | Значения шагов: `Step::STEPS[Step::STEP_INIT]` = 0 … `Step::STEPS[Step::STEP_INDEX]` = 100 |

Настройки формы пропускай через `SitemapTable::prepareSettings($settings)`. Не пиши собственный генератор XML.

Предпочтительна фоновая сборка: `Job::markToRegenerate($sitemapId)` создаёт задание и агент (первый запуск через 60 с, затем каждую секунду), выполняющий шаг за хит. Нужен запуск агентов (cron в production, `bitrix-background-jobs`).

В процессе (CLI, пошаговый обработчик): `run()` выполняет шаг примерно за 13 с, возвращает `false` только при ошибке. Завершение — `getStep()` достиг `Step::STEPS[Step::STEP_INDEX]`. На каждом шаге создавай новый `Generator`, передавай шаг и состояние, как `Job::doStep()`. Между HTTP-хитами сохраняй оба значения.

```php
use Bitrix\Seo\Sitemap\Generator;
use Bitrix\Seo\Sitemap\Type\Step;

$step = Step::getFirstStep();
$state = [];
do {
    $generator = (new Generator($sitemapId))->init($step, $state);
    if (!$generator->run()) {
        throw new \RuntimeException($generator->getStatusMessage());
    }
    $step = $generator->getStep();
    $state = $generator->getState();
} while ($step < Step::STEPS[Step::STEP_INDEX]);
```

При `SETTINGS['ROBOTS'] === 'Y'` последний шаг сам добавляет строку `Sitemap:` в robots.txt.

Административные страницы: `/bitrix/admin/seo_sitemap.php`, `seo_sitemap_edit.php`.

## robots.txt

```php
use Bitrix\Seo\RobotsFile;

$robots = new RobotsFile('s1'); // robots.txt в корне сайта
$robots->addRule([RobotsFile::SITEMAP_RULE, 'https://example.com/sitemap.xml']);
$sitemaps = $robots->getRules(RobotsFile::SITEMAP_RULE);
```

`RobotsFile` наследует `Bitrix\Main\IO\File`; `SECTION_RULE` = `User-Agent`. Административная страница: `seo_robots.php`.

## Вебмастера и поисковые системы

- Реестр: `Bitrix\Seo\SearchEngineTable` (`b_seo_search_engine`, ключ `CODE`).
- Адаптеры в `Bitrix\Seo\Engine\`: `Yandex` (`ENGINE_ID = 'yandex'`), `Google` (`'google'`), `YandexDirect`, базовый `YandexBase`.
- `Bitrix\Seo\Webmaster\Service::getSites()` — хосты с признаками привязки и подтверждения.
- `Bitrix\Seo\Service::REDIRECT_URI` (`/bitrix/tools/seo_client.php`) — адрес возврата OAuth, а не правило HTTP-редиректа.
- Старые классы для совместимости: `CSeoKeywords` (`Add`/`Update`/`GetByURL`), `CSeoPageChecker`, `CSeoUtils::getDomainsList()`. Административные страницы: `seo_tools.php`, `seo_search_yandex.php`, `seo_search_google.php`.
- Пространства рекламы и ретаргетинга (`Bitrix\Seo\Retargeting\`, `LeadAds\`, `Analytics\`) относятся к продуктовым интеграциям; для sitemap/robots не нужны.

## Редиректы: API в seo нет

В ядре нет таблицы seo для правил 301/302. Не выдумывай `SeoRedirectTable`.

| Задача | Механизм |
| --- | --- |
| Разрешение обхода, URL карты сайта | `RobotsFile`, `SETTINGS` карты сайта |
| Разрешённые адреса внешнего редиректа | Модуль `security`: `CSecurityRedirect`, `Bitrix\Security\RedirectRuleTable` |
| Переезд страницы | Правило веб-сервера, маршрут (`bitrix-routing`) или `LocalRedirect` в компоненте |

После смены URL исправь ссылки в содержимом, вызови `Job::markToRegenerate()`, обнови robots.

## Проверка

- [ ] `Loader::includeModule('seo')` выполнен до классов Sitemap/Robots/Engine.
- [ ] Настройки карты идут через `SitemapTable`; сборка — через `Job` или цикл `Generator` с передачей шага и состояния.
- [ ] `run() === false` обработан как ошибка, а не просьба продолжить.
- [ ] Метаданные инфоблока идут через InheritedProperty, а не свои столбцы `b_seo_*`.
- [ ] Не выдуман CRUD редиректов в `seo`.
