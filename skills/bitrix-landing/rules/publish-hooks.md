# Публикация, предпросмотр, события, права

## Публикация

| Действие | API |
| --- | --- |
| Опубликовать страницу | `$landing->publication()` → bool |
| Проверить ошибки без публикации | `$landing->fakePublication()` |
| Снять страницу с публикации | `$landing->unpublic()` |
| Опубликовать / снять сайт | `Site::publication($siteId, true)` / `Site::unpublic($siteId)` → `Result` |
| Публичный URL | `$landing->getPublicUrl()`, `Site::getPublicUrl($siteId)` |
| Проверить права | `$landing->canPublication()`, `$landing->canEdit()` |

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

Корзина: `Landing::markDelete($id)` / `markUnDelete($id)`, `Site::markDelete($id)`. Физический `delete()` — для скриптов очистки.

`Internals\UrlRewriteTable` (`b_landing_urlrewrite`) связывает правило со страницей внутри одного сайта landing; это не глобальная таблица редиректов.

## Предпросмотр

- Изолированный предпросмотр (**Since landing 26.1000**): `Landing::getDevicePreviewMode()` истинен во фрейме устройства редактора и **Since landing 26.1200** по подписанным ссылкам `preview/<hash>/`. CSP `sandbox` создаёт непрозрачный origin: нет сессии портала, cookies, storage, same-origin `BX.ajax`. Ядро отключает `HeadBlock`, `Gtm`, `B24button` и события cookies. Свои блоки / компоненты, выводящие авторский HTML/JS, должны пропускать его в этом режиме. Это не `Landing::getPreviewMode()`.
- **Since landing 26.1100** `Site::getPublicHash()` возвращает подпись; старые ссылки предпросмотра перестают работать, выпусти новые. Хеш запроса проверяй `Site::isPublicHashValid($siteId, $hash)`, а не `==`; раньше — `hash_equals(Site::getPublicHash($siteId), $hash)`.

## События: счётчики и метаданные

Классы (`lib/hook/page/`): `YaCounter`, `GaCounter`, `Gtm`, `MetaMain`, `MetaOg`, `MetaRobots`, `MetaYandexVerification`, `MetaGoogleVerification`, `PixelFb`, `PixelVk`, `Robots`, `HeadBlock`.

Запись через дополнительные поля с ключами `<HOOK>_<FIELD>`:

```php
Landing::saveAdditionalFields($landingId, [
    'YACOUNTER_USE' => 'Y',
    'YACOUNTER_COUNTER' => '12345678',
]);
$fields = Landing::getAdditionalFields($landingId); // аналогично Site::…
```

Не зашивай счётчики в блоки. `Bitrix\Landing\Metrika\Metrika` — аналитика продукта Битрикс, а не Яндекс.Метрика.

## Права и ограничения

- `Rights::hasAccessForSite($siteId, $accessType)`, `Rights::hasAccessForLanding($landingId, $accessType)`, `Rights::isAdmin()`; роли: `Role`.
- Дополнительные права — ключи `Rights::ADDITIONAL_RIGHTS`. **Since landing 26.1100** `mainpage_create` удалён (чтение даёт Warning и `null`). Используй `vibe_create`, `vibe_admin`, `vibe_menu24`; первые два запрещены всем до настройки администратором.
- Возможности тарифа: `\Bitrix\Landing\Manager::checkFeature(\Bitrix\Landing\Manager::FEATURE_PUBLICATION_SITE)` и аналогичные константы; `\Bitrix\Landing\Restriction\Manager::isAllowed($code)`.
- **Since landing 26.1100** `Site::updateFolder()` запрещает папку другого сайта, вложение в себя (`MOVE_RESTRICTION`) и чужой `INDEX_ID` (`FOLDER_INDEX_OUT_OF_SITE`). Перенос между сайтами требует `delete` старого и `edit` нового.

## Динамический кеш и временный режим прав — Since landing 26.1500

Перед общим HTML-кешем динамического блока событие landing:OnBeforeDynamicBlockCache получает SOURCE_IDS вида module:Source. `EventResult::ERROR` любого обработчика запрещает использовать кеш для этого вывода. Событие адресовано только модулям источников: регистрируй постоянно через registerEventHandler() с ID модуля. У обработчика `addEventHandler()` ID модуля нет. На старой версии не помещай персонализированные данные в общий кеш блока; реализуй отдельную динамическую выдачу.

Если доверенному серверному сценарию необходимо временно отключить права, сохрани `Rights::isLocalOn()`, вызови `setOff()`, в finally восстанови `setOn()` только при прежнем true. `isOn()` учитывает также глобальный режим / LANDING_DISABLE_RIGHTS и для восстановления локального флага не подходит. Это не разрешение обходить права пользователя. На старом модуле избегай временного переключения общего статического флага, используй явные проверки и отдельный служебный процесс.
