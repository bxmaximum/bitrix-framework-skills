# REST, repo blocks, files

REST method names are `landing.<class>.<method>` of `Bitrix\Landing\PublicAction\*`. Client-side calling (auth, batch, limits): `bitrix24-rest-api`.

## Repo blocks (`landing.repo.*`, **Since landing 26.1100**)

A repo block is a `b_landing_repo` row (REST app, `Landing\Repo::add()`, site import). It is data only:

- `assets.class` is ignored and `callbacks.afteradd` never runs (only a `\Closure` added at runtime does). PHP logic needs a file block in `/local/blocks/` (`rules/pages-blocks.md`).
- `landing.repo.register` / `unregister` work only from a REST app or for `Rights::isAdmin()`, else `ACCESS_DENIED`. The row is matched by `=APP_CODE` = the app code (`false` without an app), so an admin script creates a duplicate instead of updating an app's block.
- Unknown keys are dropped silently. Allowed fields: `NAME`, `CONTENT`, `SECTIONS`, `PREVIEW`, `DESCRIPTION`, `ACTIVE`, `SITE_TEMPLATE_ID`. Manifest keys: `block`, `nodes`, `cards`, `style`, `attrs`, `menu`, `assets`, `lang`, `lang_original`. `block.*`: `name`, `section`, `type`, `dynamic`, `subtype`, `subtype_params`, `description`. `assets`: `css`/`js` (http(s) paths ending `.css`/`.js`), `ext`.
- Subtypes `widget`/`widgetvue` → `UNSUPPORTED_BLOCK_SUBTYPE`; register widgets only with `landing.repowidget.register`, handler must be public https (`WIDGET_HANDLER_INVALID`).
- `landing.repo.bind` needs app context, a placement starting with `LANDING_` (`PLACEMENT_UNKNOWN`) and a valid handler (`PLACEMENT_HANDLER_INVALID`).
- `Landing\Repo::add()` from PHP does not normalize. Pass input through `Security\RepoNormalizer::normalize($fields, $manifest, $xmlId, $appCode)` first; the result has `->fields` and `->contentRejected`.

## PublicAction gateway (**Since landing 26.1100**)

- AJAX batch: max `PublicAction::MAX_BATCH_ITEMS` (500) commands, no files, else `BATCH_LIMIT_EXCEEDED`.
- A command without `scope` no longer inherits one from the previous command (AJAX and REST): pass `scope` in every command.
- Exception text is returned only with `exception_handling.debug`; otherwise read the event log, type `LANDING_ACTION_EXCEPTION`.

## Files

- Pictures: `Manager::savePicture($file, $ext = false, $params = [], $trustedSource = false)` returns array or `false`. **Since landing 26.1100** a local path or a non-uploaded file array (e.g. from `CFile::MakeFileArray`) returns `false` unless `$trustedSource` is `true`. Pass `true` only for paths your server code built, never for request data. Older versions have no 4th argument.
- Disk links in blocks (**Since landing 26.1100.200**): `landing.api.diskFile.download|view|info` require an authenticated user with read access to the page. Build URLs with `Controller\DiskFile::getDownloadLink($scope, $blockId, $fileId)`.
