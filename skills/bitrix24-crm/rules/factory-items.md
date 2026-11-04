# Factory и Item: чтение

## Точки входа

```php
use Bitrix\Crm\Item;
use Bitrix\Crm\Service\Container;

\Bitrix\Main\Loader::requireModule('crm');
$container = Container::getInstance();
$factory = $container->getFactory(\CCrmOwnerType::Deal); // ?Factory
```

- Фабрики: лид (1), сделка (2), контакт (3), компания (4), предложение (7), смарт-счёт (31), смарт-документ (36), смарт-процессы; заказ (14) — только с `sale`. Для старых счетов (5) и дел (6) фабрики нет: `null`.
- `getFactory()` возвращает `null` для неизвестного или удалённого смарт-процесса. Проверяй результат.
- `\CCrmOwnerType::ResolveID('DEAL')` и `ResolveName(2)` преобразуют имя и ID; `isUseFactoryBasedApproach($entityTypeId)` проверяет подход фабрик.

## Чтение

```php
$items = $factory->getItems([
    'select' => [Item::FIELD_NAME_ID, Item::FIELD_NAME_TITLE, Item::FIELD_NAME_STAGE_ID],
    'filter' => [
        '=' . Item::FIELD_NAME_ASSIGNED => $userId,
        '@' . Item::FIELD_NAME_CATEGORY_ID => [0, $categoryId],
    ],
    'order' => [Item::FIELD_NAME_ID => 'DESC'],
    'limit' => 50,
]); // Item[]

$item = $factory->getItem($id); // ?Item: все поля, контакты, товары, наблюдатели
$item = $factory->getItem($id, [Item::FIELD_NAME_ID, Item::FIELD_NAME_STAGE_ID]);
$count = $factory->getItemsCount(['=' . Item::FIELD_NAME_ASSIGNED => $userId]);
```

- Параметры соответствуют `DataManager::getList()`. Общие имена полей в `select`/`filter`/`order` сопоставляются столбцам, включая операторы.
- `getItems()`/`getItem()`/`getItemsCount()` не проверяют права. Для пользовательских данных используй `getItemsFilteredByPermissions()` (`rules/permissions.md`).
- `select` по умолчанию (`*`) загружает UF, родителей, компанию, контакты, товары, наблюдателей. Ограничивай выборку списка.
- Для тяжёлых агрегатов чтения допустим `$factory->getDataClass()::query()`. Запись через класс данных не используй.

## Методы Item

- `get()`, `set()`, `isChanged()`, `remindActual()` (значение до несохранённых изменений), `getData()` (общие имена), `getCompatibleData()` (legacy-ключи), `isNew()`, `getId()`, `getEntityTypeId()`, `hasField()`.
- Магические методы следуют константам: `getTitle()`, `setStageId()`, `getAssignedById()`, `getCategoryId()`, `getOpportunity()`, `isChangedStageId()`, `getCompanyId()`, `setContactIds([...])` (заменяет все привязки контактов).
- Имя константы отличается от значения: `FIELD_NAME_ASSIGNED` = `ASSIGNED_BY_ID`, `FIELD_NAME_PRODUCTS` = `PRODUCT_ROWS`, `FIELD_NAME_BEGIN_DATE` = `BEGINDATE`.
- Набор полей смарт-процесса зависит от типа. Проверяй `hasField()` для необязательного поля.
- Даты — объекты `Bitrix\Main\Type\Date`/`DateTime`.
- Привязка к родителю при настроенной связи: `$item->set(\Bitrix\Crm\Service\ParentFieldManager::getParentFieldName(\CCrmOwnerType::Deal), $dealId)` заполняет `PARENT_ID_2`.
- `$item->setProductRowsFromArrays([['PRODUCT_NAME' => 'X', 'PRICE' => 100.0, 'QUANTITY' => 2]])` заменяет все товарные строки и возвращает `Result`; сохраняет их операция.
- URL карточки: `$container->getRouter()->getItemDetailUrl($entityTypeId, $id)` (`?Uri`).

## Телефоны и адреса почты (FM)

Только лид, контакт, компания (`$factory->isMultiFieldsEnabled()`).

```php
use Bitrix\Crm\Multifield;

$fm = $item->getFm(); // копия Multifield\Collection
$fm->add(
    (new Multifield\Value())
        ->setTypeId(Multifield\Type\Phone::ID)
        ->setValueType(Multifield\Type\Phone::VALUE_TYPE_WORK)
        ->setValue('+79990000000')
);
$item->setFm($fm); // сохраняется операцией добавления/изменения

foreach ($item->getFm()->filterByType(Multifield\Type\Email::ID) as $value) {
    $emails[] = $value->getValue();
}
```

- `getFm()` возвращает клон: обязательно передай его обратно через `setFm()`. `add()` пропускает одинаковые значения; `removeById($id)` удаляет одно.
- Для пакетной загрузки FM списка включи `Item::FIELD_NAME_FM` в `select` вызова `getItems()`.
- Типы: `Phone`, `Email`, `Web`, `Im`, `Link` в `Multifield\Type`; виды значений — константы `VALUE_TYPE_*` (`WORK`, `MOBILE`, `HOME`, …).
