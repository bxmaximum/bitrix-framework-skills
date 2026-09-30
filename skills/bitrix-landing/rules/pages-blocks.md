# Sites, pages, blocks

## Model

| Entity | Facade (`Bitrix\Landing\…`) | ORM |
| --- | --- | --- |
| Site | `Site` | `Internals\SiteTable` |
| Page | `Landing` | `Internals\LandingTable` |
| Block | `Block` | `Internals\BlockTable` |
| Domain, folder | `Domain`, `Folder` | internals tables |

`Site` and `Landing` extend `Internals\BaseTable` (`getList`/`add`/`update`/`delete`). Their `getList` filters by the current user's rights unless the filter has `'CHECK_PERMISSIONS' => 'N'`. Prefer them to `Internals\*Table`.

Site types (`Site::getTypes()`): `PAGE`, `STORE`, `SMN`, `KNOWLEDGE`, `GROUP`, `VIBE`; pseudo-scope `crm_forms`. `Site\Type::SCOPE_CODE_VIBE` (welcome page) is **Since landing 26.300\***; before it the code was `'MAINPAGE'`, now only an import alias for `VIBE`.

Public paths: `Manager::PUBLICATION_PATH` (`/pub/site/`), `Manager::PUBLICATION_PATH_SITEMAN` (`/lp/`).

## Pages

```php
use Bitrix\Landing\Landing;
use Bitrix\Landing\Site;

$rows = Landing::getList([
    'select' => ['ID', 'TITLE', 'CODE', 'ACTIVE', 'PUBLIC'],
    'filter' => ['SITE_ID' => $siteId, '=DELETED' => 'N'],
]);

$add = Landing::addByTemplate($siteId, 'empty', ['TITLE' => 'Promo', 'CODE' => 'promo']);
$siteAdd = Site::addByTemplate('empty', 'PAGE');
$siteUrl = Site::getPublicUrl($siteId);
```

`Landing::createInstance($id, $params)` options (anything else is ignored): `skip_blocks` (true = don't load blocks), `blocks_limit`, `blocks_id`, `deleted` (load deleted blocks), `force_deleted` (open a deleted page), `check_permissions` (false = skip rights), `disable_link_preview`, `is_area`. Check `$landing->exist()` before use.

`CODE` must not contain `` ' " ` $ \ < > ( ) { } `` or control bytes: add/update fail with `WRONG_CODE_CHARS` (**Since landing 26.1100**). Importers pre-check with `Security\SyspageUrl::isSafeCode($code)`; on older versions strip these characters yourself.

## Blocks

```php
$landing = Landing::createInstance($landingId);
$blockId = $landing->addBlock('01.big_with_text', ['SORT' => 500]); // int|false
if ($blockId === false) {
    $errors = $landing->getError()->getErrors(); // ACCESS_DENIED, BLOCK_NOT_FOUND, …
} else {
    $block = $landing->getBlockById($blockId);
}
```

- `addBlock()` checks `canEdit()`. `$data` holds block row fields (`ACTIVE`, `PUBLIC`, `ACCESS`, `SORT`, `CONTENT`, `ANCHOR`, `XML_ID`, `SOURCE_PARAMS`, `DESIGNED`); it does not override node content.
- Read: `$landing->getBlocks()` (id ⇒ `Block`), `$block->getManifest()`; write: `$block->saveContent($html)`, then `$block->save()`.
- Catalog of codes: `(new Block\BlockRepo())->getRepository()`; lookup dirs `BlockRepo::getGeneralPaths()` = `/bitrix/blocks` + `/local/blocks`.

## Custom blocks

`/local/blocks/<namespace>/<code>/`, same layout as `landing/install/blocks/bitrix/<code>/`:
`block.php` (HTML), `.description.php` (returns the manifest), `lang/`, `preview.jpg`, optional `style.css`, `script.js`, `class.php`.

- `<code>` must match `[A-Za-z0-9_.:-]+`, otherwise `class.php` is not included.
- `class.php` and manifest `callbacks` run only for file blocks. Repo blocks (REST, import) are data only: `rules/rest-security.md`.
- After deploy call `Block::clearRepositoryCache()`.

## URLs in content (**Since landing 26.1000**)

On save, `Node\Link` href, `data-pseudo-url` and manifest attrs of `'type' => 'url'` become `''` if the scheme is not allowed. Allowed: `http`, `https`, `mailto`, `tel`, `sms`, `skype`, relative, and markers `page:`, `block:`, `form:`, `product:`, `user:`; `file:` and `help:` only as `file:#…`/`help:#…`. Custom app schemes and deep links are lost.

Helpers in `Bitrix\Landing\Sanitizer` (return `''` on reject): `(new Sanitizer())->sanitizeHref($href)`, `->sanitizePseudoUrl($url)`, static `sanitizeHrefScheme($href)` (http/https/relative only). **Since landing 26.1100**: static `hasDisallowedScheme($url)`, `sanitizeWidgetHandlerUrl($url, $requireHttps)`, `sanitizeCssUrl($url)`. Older versions: your own scheme regex.

## Disk file links

`Block::isContains()` was removed in landing 26.1000 (Fatal). Use `Block::findVisibleLandingIdByFileInBlock($blockId, $fileId)` / `findVisibleLandingIdByFileInLanding($landingId, $fileId)`: landing id or `null`, only if visible to the current user. Match a link yourself with `preg_match(Connector\Disk::getExactFileHrefPattern($objectId), $content)` (**Since landing 26.1100.200**), never substring search (id 25 matches 250).
