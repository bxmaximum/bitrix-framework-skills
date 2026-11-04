# REST, репозиторные блоки, файлы

Имена REST: `landing.<class>.<method>` из `Bitrix\Landing\PublicAction\*`. Авторизация клиента, пакеты, лимиты: `bitrix24-rest-api`.

## Репозиторные блоки (`landing.repo.*`, **Since landing 26.1100**)

Репозиторный блок — строка `b_landing_repo` из REST-приложения, `Landing\Repo::add()` либо импорта сайта. Содержит только данные:

- `assets.class` игнорируется, `callbacks.afteradd` не вызывается (кроме `\Closure`, добавленного во время выполнения). PHP-логике нужен файловый блок `/local/blocks/` (`rules/pages-blocks.md`).
- `landing.repo.register` / `unregister` доступны REST-приложению либо `Rights::isAdmin()`, иначе `ACCESS_DENIED`. Поиск строки: `=APP_CODE` равен коду приложения (`false` вне приложения); административный скрипт создаст дубль, а не обновит блок приложения.
- Неизвестные ключи молча отбрасываются. Поля: `NAME`, `CONTENT`, `SECTIONS`, `PREVIEW`, `DESCRIPTION`, `ACTIVE`, `SITE_TEMPLATE_ID`. Манифест: `block`, `nodes`, `cards`, `style`, `attrs`, `menu`, `assets`, `lang`, `lang_original`. `block.*`: `name`, `section`, `type`, `dynamic`, `subtype`, `subtype_params`, `description`. `assets`: `css`/`js` (http(s), путь заканчивается `.css`/`.js`), `ext`.
- Подтипы `widget`/`widgetvue` дают `UNSUPPORTED_BLOCK_SUBTYPE`. Виджеты регистрируй через `landing.repowidget.register`; обработчик — публичный https (`WIDGET_HANDLER_INVALID`).
- `landing.repo.bind` требует контекст приложения, размещение с префиксом `LANDING_` (`PLACEMENT_UNKNOWN`) и корректный обработчик (`PLACEMENT_HANDLER_INVALID`).
- PHP `Landing\Repo::add()` не нормализует данные. Сначала `Security\RepoNormalizer::normalize($fields, $manifest, $xmlId, $appCode)`; результат содержит `->fields`, `->contentRejected`.

## Шлюз PublicAction (**Since landing 26.1100**)

- Пакет AJAX: до `PublicAction::MAX_BATCH_ITEMS` (500) команд, без файлов; иначе `BATCH_LIMIT_EXCEEDED`.
- Команда без `scope` больше не наследует его от предыдущей (AJAX и REST): передавай в каждой команде.
- Текст исключения возвращается только с `exception_handling.debug`; иначе ищи в журнале событий тип `LANDING_ACTION_EXCEPTION`.

## Файлы

- Изображения: `Manager::savePicture($file, $ext = false, $params = [], $trustedSource = false)` → массив либо `false`. **Since landing 26.1100** локальный путь и массив незагруженного файла (например, `CFile::MakeFileArray`) дают `false`, если `$trustedSource` не `true`. Передавай `true` только для путей серверного кода, не данных запроса. На старом ядре четвёртого аргумента нет.
- Ссылки Диска в блоках (**Since landing 26.1100.200**): `landing.api.diskFile.download|view|info` требуют авторизованного пользователя с правом чтения страницы. URL строй через `Controller\DiskFile::getDownloadLink($scope, $blockId, $fileId)`.
