# Помощники и генераторы

`$helper = $this->getHelperManager();`, затем `$helper->Iblock()->…`. Если нужный модуль не установлен, помощник бросает `HelperException`.

## Помощники

| Вызов | Область | Основные методы |
| --- | --- | --- |
| `Iblock()` | Типы, инфоблоки, поля, свойства, разделы, элементы, права | `saveIblockType`, `saveIblock`, `saveProperty`, `saveSection`, `saveElementByXmlId`, `deleteIblockIfExists` |
| `Hlblock()` | HL-блоки, UF, записи, права | `saveHlblock`, `saveField`, `saveElementByXmlId`, `deleteHlblockIfExists` |
| `UserTypeEntity()` | UF любого `ENTITY_ID` | `saveUserTypeEntity`, `addUserTypeEntitiesIfNotExists`, `deleteUserTypeEntitiesIfExists` |
| `UserGroup()` | Группы по `STRING_ID` | `saveGroup($code, $fields)`, `deleteGroup` |
| `Agent()` | Агенты | `saveAgent`, `deleteAgentIfExists($moduleId, $name)` |
| `Option()` | Опции модулей | `saveOption(['MODULE_ID', 'NAME', 'VALUE', 'SITE_ID'])`, `deleteOptions($filter)` |
| `Event()` | Почтовые типы / шаблоны, SMS (`bitrix-mail`) | `saveEventType($eventName, $fields)` (LID — язык, не сайт), `saveEventMessage($eventName, $fields)`, `saveEventSmsTemplate` |
| `Site()` | Чтение сайтов, шаблоны сайта | `getSites`, `getDefaultSiteIdIfExists`, `setSiteTemplates($siteId, $templates)` |
| `Lang()` / `Culture()` | Языки, культуры | `saveLang(['LID', …])`, `saveCulture(['CODE', …])` |
| `Form()` / `Forum()` / `Blog()` / `Vote()` / `Subscribe()` | Соответствующие модули | Свои `save*` |
| `OrderProperties()` / `SaleDiscount()` / `DeliveryService()` | `sale` | `save*` |
| `Medialib()`, `UserOptions()`, `Sql()`, `Text()` | Медиабиблиотека, настройки грида / фильтра, SQL, текст | |
| `IblockExchange()` / `HlblockExchange()` / `MedialibExchange()` / `BlogExchange()` | Обмен элементами для генераторов | |
| `Task()` | Уровни доступа b_task (`getTaskIdByName($moduleId, $name)`), не модуль задач | |

Особенности:

- `Site()` не создаёт / меняет сайты. Языки — `Lang()->saveLang()`, культуры — `Culture()->saveCulture()`.
- `deleteOptions(['MODULE_ID' => 'x'])` без NAME удаляет **все** опции модуля.
- В проверенном sprint.migration 5.16.1 `saveEventMessage()` требует LID и ищет по имени события + SUBJECT + LID: новая тема создаёт второй шаблон. Для смены темы найди по старой: `getEventMessage(['EVENT_NAME' => $name, 'SUBJECT' => $oldSubject, 'LID' => $siteIds])`, затем `updateEventMessageById($row['ID'], $fields)`.

Правила инфоблоков / HL: `bitrix-iblocks`, `bitrix-highloadblock`.

## Имена методов

| Шаблон | Поведение |
| --- | --- |
| `save*` | Создать / привести к заданному состоянию; основной вариант для схемы |
| `add*IfNotExists` | Только создать; расхождения полей не обновляются |
| `get*IfExists` | Возврат либо **исключение** `HelperException`, если нет |
| `delete*IfExists`, `update*IfExists` | false, если нет |

## Генераторы

Создают Version и файлы обмена из текущей БД. Запуск через административный интерфейс либо `php "$MIGRATE" run <Builder>`. Прочитай PHP, закоммить, примени up в другой среде.

Стандартные ключи `version_builders`: `BlankBuilder`, `IblockBuilder`, `IblockPropertyBuilder`, `IblockPropertyDeleteBuilder`, `IblockCategoryBuilder`, `IblockElementsBuilder`, `IblockDeleteBuilder`, `HlblockBuilder`, `HlblockElementsBuilder`, `UserTypeEntitiesBuilder`, `UserGroupBuilder`, `AgentBuilder`, `OptionBuilder`, `EventBuilder`, `FormBuilder`, `ForumBuilder`, `VoteBuilder`, `SubscribeBuilder`, `BlogBuilder`, `BlogPostBuilder`, `BlogUserTypeEntitiesBuilder`, `UserOptionsBuilder`, `LanguageBuilder`, `OrderPropertiesBuilder`, `SaleDiscountBuilder`, `MedialibElementsBuilder`, `CacheCleanerBuilder`, `MarkerBuilder`, `TransferBuilder`.

Примеры только для чтения: `{module}/examples/*.php`, `{module}/templates/*.php`.

## Свои помощники

```php
\Sprint\Migration\HelperManager::getInstance()->registerHelper('Shop', \Vendor\Shop\Migration\ShopHelper::class);
// class ShopHelper extends \Sprint\Migration\Helper; затем $helper->Shop()->…
```

Только для общего слоя миграций; иначе вызывай сервисы модуля из up().
