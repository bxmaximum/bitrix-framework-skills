# Сайты, страницы, блоки

## Модель

| Сущность | Фасад (`Bitrix\Landing\…`) | ORM |
| --- | --- | --- |
| Сайт | `Site` | `Internals\SiteTable` |
| Страница | `Landing` | `Internals\LandingTable` |
| Блок | `Block` | `Internals\BlockTable` |
| Домен, папка | `Domain`, `Folder` | Внутренние таблицы |

`Site` и `Landing` наследуют `Internals\BaseTable` (`getList`/`add`/`update`/`delete`). `getList` учитывает права пользователя, кроме фильтра `'CHECK_PERMISSIONS' => 'N'`. Предпочитай фасады внутренним `Internals\*Table`.

Типы (`Site::getTypes()`): `PAGE`, `STORE`, `SMN`, `KNOWLEDGE`, `GROUP`, `VIBE`; псевдообласть `crm_forms`. `Site\Type::SCOPE_CODE_VIBE` (стартовая страница) — **Since landing 26.300\***; раньше код `'MAINPAGE'`, теперь только псевдоним импорта `VIBE`.

Публичные пути: `Manager::PUBLICATION_PATH` (`/pub/site/`), `Manager::PUBLICATION_PATH_SITEMAN` (`/lp/`).

## Страницы

```php
use Bitrix\Landing\Landing;
use Bitrix\Landing\Site;

$rows = Landing::getList([
    'select' => ['ID', 'TITLE', 'CODE', 'ACTIVE', 'PUBLIC'],
    'filter' => ['SITE_ID' => $siteId, '=DELETED' => 'N'],
]);

$add = Landing::addByTemplate($siteId, 'empty', ['TITLE' => 'Акция', 'CODE' => 'promo']);
$siteAdd = Site::addByTemplate('empty', 'PAGE');
$siteUrl = Site::getPublicUrl($siteId);
```

Параметры `Landing::createInstance($id, $params)`, остальные игнорируются: `skip_blocks` (не загружать блоки), `blocks_limit`, `blocks_id`, `deleted` (загрузить удалённые блоки), `force_deleted` (открыть удалённую страницу), `check_permissions` (`false` — без прав), `disable_link_preview`, `is_area`. До работы проверь `$landing->exist()`.

`CODE` не должен содержать `` ' " ` $ \ < > ( ) { } `` и управляющие байты: add/update возвращают `WRONG_CODE_CHARS` (**Since landing 26.1100**). Импорт проверяет ``Security\SyspageUrl::isSafeCode($code)``; на старом ядре убирай символы сам.

## Блоки

```php
$landing = Landing::createInstance($landingId);
$blockId = $landing->addBlock('01.big_with_text', ['SORT' => 500]); // int|false
if ($blockId === false) {
    $errors = $landing->getError()->getErrors(); // ACCESS_DENIED, BLOCK_NOT_FOUND, …
} else {
    $block = $landing->getBlockById($blockId);
}
```

- `addBlock()` проверяет `canEdit()`. `$data` содержит поля строки (`ACTIVE`, `PUBLIC`, `ACCESS`, `SORT`, `CONTENT`, `ANCHOR`, `XML_ID`, `SOURCE_PARAMS`, `DESIGNED`), не переопределяет содержимое узлов.
- Чтение: `$landing->getBlocks()` (ID ⇒ `Block`), `$block->getManifest()`; запись: `$block->saveContent($html)`, затем `$block->save()`.
- Каталог кодов: `(new Block\BlockRepo())->getRepository()`; пути `BlockRepo::getGeneralPaths()` — `/bitrix/blocks` + `/local/blocks`.

## Собственные блоки

`/local/blocks/<namespace>/<code>/`, структура как в `landing/install/blocks/bitrix/<code>/`:
`block.php` (HTML), `.description.php` (манифест), `lang/`, `preview.jpg`; необязательны `style.css`, `script.js`, `class.php`.

- `<code>` соответствует `[A-Za-z0-9_.:-]+`, иначе `class.php` не подключается.
- `class.php` и `callbacks` манифеста работают только у файловых блоков. Репозиторные (REST, импорт) содержат только данные: `rules/rest-security.md`.
- После поставки вызывай `Block::clearRepositoryCache()`.

## URL в содержимом (**Since landing 26.1000**)

При сохранении `Node\Link` href, `data-pseudo-url` и атрибуты манифеста `'type' => 'url'` становятся `''`, если схема запрещена. Разрешены `http`, `https`, `mailto`, `tel`, `sms`, `skype`, относительные пути и маркеры `page:`, `block:`, `form:`, `product:`, `user:`. `file:` / `help:` — только `file:#…`/`help:#…`. Свои схемы приложений и глубокие ссылки теряются.

Методы `Bitrix\Landing\Sanitizer` возвращают `''` при отказе: `(new Sanitizer())->sanitizeHref($href)`, `->sanitizePseudoUrl($url)`, статический `sanitizeHrefScheme($href)` (только http/https/относительный). **Since landing 26.1100**: `hasDisallowedScheme($url)`, `sanitizeWidgetHandlerUrl($url, $requireHttps)`, `sanitizeCssUrl($url)`. На старом ядре проверяй схемы своим регулярным выражением.

## Ссылки на файлы Диска

`Block::isContains()` удалён в landing 26.1000 (фатальная ошибка). Используй `Block::findVisibleLandingIdByFileInBlock($blockId, $fileId)` / `findVisibleLandingIdByFileInLanding($landingId, $fileId)`: ID страницы либо `null`, только при её видимости пользователю. Ссылку ищи через `preg_match(Connector\Disk::getExactFileHrefPattern($objectId), $content)` (**Since landing 26.1100.200**), не подстрокой: 25 совпадёт с 250. <!-- verify:negative -->
