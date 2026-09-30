# Publish, preview, hooks, rights

## Publish

| Action | API |
| --- | --- |
| Publish page | `$landing->publication()` → bool |
| Dry-run publish errors | `$landing->fakePublication()` |
| Unpublish page | `$landing->unpublic()` |
| Publish / unpublish site | `Site::publication($siteId, true)` / `Site::unpublic($siteId)` → `Result` |
| Public URL | `$landing->getPublicUrl()`, `Site::getPublicUrl($siteId)` |
| Rights check | `$landing->canPublication()`, `$landing->canEdit()` |

```php
$landing = Landing::createInstance($landingId);
if ($landing->canPublication() && $landing->publication()) {
    $url = $landing->getPublicUrl();
} else {
    $error = $landing->getError()->getFirstError(); // ?Bitrix\Main\Error
}

$result = Site::publication($siteId, true);
if (!$result->isSuccess()) {
    $messages = $result->getErrorMessages();
}
```

Soft delete (recycle bin): `Landing::markDelete($id)` / `markUnDelete($id)`, `Site::markDelete($id)`. Hard `delete()` only for cleanup scripts.

`Internals\UrlRewriteTable` (`b_landing_urlrewrite`) maps a rule to a page inside one landing site; it is not a global redirect table.

## Preview

- Sandboxed preview (**Since landing 26.1000**): `Landing::getDevicePreviewMode()` is true in the editor device frame and, **Since landing 26.1200**, on signed `preview/<hash>/` links. The page runs with CSP `sandbox` (opaque origin): no portal session, cookies, storage, or same-origin `BX.ajax`. The kernel turns off `HeadBlock`, `Gtm`, `B24button` and cookie hooks there. Custom blocks and components that print author HTML/JS must skip it in this mode. Not the same as `Landing::getPreviewMode()`.
- **Since landing 26.1100** `Site::getPublicHash()` is a signature: preview links issued earlier stop working, reissue them. Check a hash from a request with `Site::isPublicHashValid($siteId, $hash)`, never `==` (older: `hash_equals(Site::getPublicHash($siteId), $hash)`).

## Hooks: counters and meta

Hook classes (`lib/hook/page/`): `YaCounter`, `GaCounter`, `Gtm`, `MetaMain`, `MetaOg`, `MetaRobots`, `MetaYandexVerification`, `MetaGoogleVerification`, `PixelFb`, `PixelVk`, `Robots`, `HeadBlock`.

Write through additional fields; keys are `<HOOK>_<FIELD>`:

```php
Landing::saveAdditionalFields($landingId, [
    'YACOUNTER_USE' => 'Y',
    'YACOUNTER_COUNTER' => '12345678',
]);
$fields = Landing::getAdditionalFields($landingId); // same for Site::…
```

Don't hardcode counters in blocks. `Bitrix\Landing\Metrika\Metrika` is Bitrix product analytics, not Yandex.Metrika.

## Rights and limits

- `Rights::hasAccessForSite($siteId, $accessType)`, `Rights::hasAccessForLanding($landingId, $accessType)`, `Rights::isAdmin()`; roles: `Role`.
- Additional rights are keys of `Rights::ADDITIONAL_RIGHTS`. **Since landing 26.1100** `mainpage_create` is gone (reading it gives a Warning and `null`). Use `vibe_create`, `vibe_admin`, `vibe_menu24`. The first two deny everyone until an admin configures them.
- Tariff features: `\Bitrix\Landing\Manager::checkFeature(\Bitrix\Landing\Manager::FEATURE_PUBLICATION_SITE)` and similar constants; `\Bitrix\Landing\Restriction\Manager::isAllowed($code)`.
- **Since landing 26.1100** `Site::updateFolder()` rejects a folder from another site, nesting a folder in itself (`MOVE_RESTRICTION`) and a foreign `INDEX_ID` (`FOLDER_INDEX_OUT_OF_SITE`). Moving a folder to another site needs `delete` on the old site and `edit` on the new one.
