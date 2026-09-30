---
name: bitrix-seo
description: 'seo module — sitemap Generator/Job, RobotsFile, Yandex/Google webmaster engines, CSeoKeywords. Use when generating or regenerating sitemap.xml, editing robots.txt, wiring webmaster services, or asked for SEO redirects.'
---

# SEO module (`seo`)

Baseline: main 23.0+ · Verified: seo 26.100.0, main 26.800.0

`Loader::includeModule('seo')` before any class below. Page title/description of iblock content is not stored here: InheritedProperty in `bitrix-iblocks` (`rules/query-seo-perf.md`). Landing counters/meta: hooks in `bitrix-landing`.

## Sitemap

| Class | Role |
| --- | --- |
| `Bitrix\Seo\Sitemap\Internals\SitemapTable` | `b_seo_sitemap` rows: `SITE_ID`, `ACTIVE`, `NAME`, `DATE_RUN`, serialized `SETTINGS` |
| `Bitrix\Seo\Sitemap\Job` | Background build by agent: `markToRegenerate()`, `addJob()`, `doJobAgent()`, `STATUS_*` |
| `Bitrix\Seo\Sitemap\Generator` | One step of a build: `init($step, $state)`, `run()`, `getStep()`, `getState()` |
| `Bitrix\Seo\Sitemap\Type\Step` | Step values: `Step::STEPS[Step::STEP_INIT]` = 0 … `Step::STEPS[Step::STEP_INDEX]` = 100 |

Settings saved from a form go through `SitemapTable::prepareSettings($settings)`. Don't write your own XML writer.

Background (preferred): `Job::markToRegenerate($sitemapId)` creates the job and an agent (first run in 60 s, then every second) that runs one step per hit until done. Agents must run (cron in production, `bitrix-background-jobs`).

In-process (CLI, stepper): `run()` does one step of about 13 s and returns `false` only on error. The build is finished when `getStep()` reaches `Step::STEPS[Step::STEP_INDEX]`. Build a new `Generator` per step and carry step + state, as `Job::doStep()` does; across HTTP hits, persist both.

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

With `SETTINGS['ROBOTS'] === 'Y'` the last step adds the `Sitemap:` line to robots.txt itself.

Admin pages: `/bitrix/admin/seo_sitemap.php`, `seo_sitemap_edit.php`.

## robots.txt

```php
use Bitrix\Seo\RobotsFile;

$robots = new RobotsFile('s1'); // site document root robots.txt
$robots->addRule([RobotsFile::SITEMAP_RULE, 'https://example.com/sitemap.xml']);
$sitemaps = $robots->getRules(RobotsFile::SITEMAP_RULE);
```

`RobotsFile` extends `Bitrix\Main\IO\File`; `SECTION_RULE` = `User-Agent`. Admin: `seo_robots.php`.

## Webmaster and search engines

- Registry: `Bitrix\Seo\SearchEngineTable` (`b_seo_search_engine`, key `CODE`).
- Engines in `Bitrix\Seo\Engine\`: `Yandex` (`ENGINE_ID = 'yandex'`), `Google` (`'google'`), `YandexDirect`, base `YandexBase`.
- `Bitrix\Seo\Webmaster\Service::getSites()` — hosts with bound/verified flags.
- `Bitrix\Seo\Service::REDIRECT_URI` (`/bitrix/tools/seo_client.php`) is the OAuth callback, not an HTTP redirect.
- Legacy helpers (compat only): `CSeoKeywords` (`Add`/`Update`/`GetByURL`), `CSeoPageChecker`, `CSeoUtils::getDomainsList()`. Admin: `seo_tools.php`, `seo_search_yandex.php`, `seo_search_google.php`.
- Ads/retargeting namespaces (`Bitrix\Seo\Retargeting\`, `LeadAds\`, `Analytics\`) are product integrations; skip them for sitemap/robots work.

## Redirects: no API here

The kernel has no seo table for 301/302 rules. Don't invent `SeoRedirectTable`.

| Need | Where |
| --- | --- |
| Crawl allow/deny, sitemap URL | `RobotsFile`, sitemap `SETTINGS` |
| Open-redirect allow-list | `security` module: `CSecurityRedirect`, `Bitrix\Security\RedirectRuleTable` |
| Page moved | web server rule, route (`bitrix-routing`) or `LocalRedirect` in a component |

After URL changes: fix links in content, `Job::markToRegenerate()`, adjust robots.

## Checklist

- [ ] `Loader::includeModule('seo')` before Sitemap/Robots/Engine classes.
- [ ] Sitemap rows via `SitemapTable`; builds via `Job` or a `Generator` step loop that carries step + state.
- [ ] `run() === false` handled as an error, not as "more steps".
- [ ] Iblock meta via InheritedProperty, not custom `b_seo_*` columns.
- [ ] No invented redirect CRUD in `seo`.
