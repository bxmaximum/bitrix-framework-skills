# Цены каталога

## Цены

Типы цен: `GroupTable` (`b_catalog_group`); базовый тип: `GroupTable::getBasePriceTypeId()` (кешируется, `?int`). Права просмотра и покупки по типам: `GroupAccessTable`.

```php
use Bitrix\Catalog\GroupTable;
use Bitrix\Catalog\Model\Price;
use Bitrix\Catalog\PriceTable;

$result = Price::add([
    'PRODUCT_ID' => $elementId,
    'CATALOG_GROUP_ID' => GroupTable::getBasePriceTypeId(),
    'PRICE' => 1990.00,
    'CURRENCY' => 'RUB',   // валюта должна существовать в модуле currency
]);
if (!$result->isSuccess()) { /* $result->getErrorMessages() */ }

$prices = PriceTable::getList([
    'select' => ['ID', 'CATALOG_GROUP_ID', 'PRICE', 'CURRENCY', 'QUANTITY_FROM', 'QUANTITY_TO'],
    'filter' => ['=PRODUCT_ID' => $elementId],
])->fetchAll();
```

НДС хранится в товаре (`VAT_ID`, `VAT_INCLUDED`); ставки: `VatTable`. Итоговую цену покупателя и скидки вычисляет `sale` (`bitrix-sale`), чтения `PriceTable` недостаточно.


## REST и права интеграции

**Since catalog 26.500** старый `catalog.price.modify` проверяет `element_edit_price` и принадлежность каждого `PRICES[].ID` товару до удаления отсутствующих цен. `update`/`delete` проверяют товар сохранённой цены. Подмена ID даёт `Access Denied` (`200040300030`). На старой версии такую проверку выполняй в интеграции.

REST V3 находится в отдельном пространстве `Infrastructure\Rest\Controller` и использует `/rest/api/`; совпадение имени метода со старым REST не означает одинаковый протокол. Подключение и официальные страницы методов: `bitrix24-rest-api`.
