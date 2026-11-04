# Собственный поставщик данных

Поставщик преобразует исходное значение (обычно ID сущности) в поля подстановки. Размещай в своём модуле: `/local/modules/vendor.orders/lib/DocumentGenerator/OrderProvider.php`.

## Корневой поставщик

```php
namespace Vendor\Orders\DocumentGenerator;

use Bitrix\DocumentGenerator\DataProvider;
use Bitrix\DocumentGenerator\DataProvider\ArrayDataProvider;
use Bitrix\DocumentGenerator\DataProviderManager;
use Bitrix\DocumentGenerator\Nameable;
use Bitrix\Main\DI\ServiceLocator;
use Vendor\Orders\Service\OrderService;

final class OrderProvider extends DataProvider implements Nameable
{
    public function __construct($source, array $options = [])
    {
        parent::__construct($source, $options);
        $id = (int)$source; // каталог полей передаёт ' '
        if ($id > 0)
        {
            $this->data = ServiceLocator::getInstance()->get(OrderService::class)->getDocumentData($id); // ['NUMBER' => …, 'DATE' => Date, 'ROWS' => [...]] либо null
        }
    }

    public static function getLangName(): string
    {
        return 'Заказ';
    }

    public function isRootProvider(): bool
    {
        return true;
    }

    public function hasAccess($userId): bool
    {
        return $this->isLoaded()
            && ServiceLocator::getInstance()->get(OrderService::class)->canRead((int)$this->source, (int)$userId);
    }

    public function getFields(): array
    {
        return $this->fields ??= [
            'NUMBER' => ['TITLE' => 'Номер', 'REQUIRED' => 'Y'],
            'DATE' => ['TITLE' => 'Дата', 'TYPE' => self::FIELD_TYPE_DATE],
            'ITEMS' => [
                'TITLE' => 'Позиции',
                'PROVIDER' => ArrayDataProvider::class,
                'VALUE' => [$this, 'getItems'],
                'OPTIONS' => ['ITEM_PROVIDER' => OrderItemProvider::class, 'ITEM_NAME' => 'ITEM', 'ITEM_TITLE' => 'Позиция'],
            ],
        ];
    }

    public function getItems(): array
    {
        $manager = DataProviderManager::getInstance();
        $items = [];
        foreach ($this->data['ROWS'] ?? [] as $row)
        {
            $item = $manager->getDataProvider(OrderItemProvider::class, $row, [], $this); // родитель — $this
            if ($item) { $items[] = $item; }
        }
        return $items;
    }
}
```

Поставщик строки: `HashDataProvider` сохраняет только ключи из `getFields()`:

```php
final class OrderItemProvider extends \Bitrix\DocumentGenerator\DataProvider\HashDataProvider
{
    public function getFields(): array
    {
        return $this->fields ??= ['NAME' => ['TITLE' => 'Название'], 'PRICE' => ['TITLE' => 'Цена']];
    }
}
```

Поля подстановки: `{Number}`, `{Date~d.m.Y}`, `{ItemsItemName}`, `{ItemsItemPrice}`, `{ItemsNumber}`.

## Правила

- `$this->data` хранит значения по кодам полей; `isLoaded()` означает `data !== null`. Поле с `VALUE` вычисляемое: строка — код другого поля; `[$this, 'method']` / `[$object, 'method']` — обработчик, принимающий поле подстановки.
- Ключи поля: `TITLE`, `VALUE`, `TYPE` (`FIELD_TYPE_DATE|IMAGE|STAMP|NAME|PHONE|TEXT`), `FORMAT`, `PROVIDER` + `OPTIONS` (вложенный поставщик), `REQUIRED => 'Y'`, `HIDE_ROW => 'Y'`.
- Конструктор и `getFields()` не должны загружать данные или выполнять тяжёлую работу: каталог создаёт поставщиков с исходным значением `' '`.
- Не добавляй типы параметров базового класса (`$source`, `$userId`, `$name`); можно добавлять только возвращаемые типы.
- Базовый `hasAccess()` возвращает `false`, если родитель не разрешил доступ. Корневой поставщик обязан переопределить его. Вложенным передавай `$this` как родителя.
- В `VALUE` используй `[$this, 'method']`, не замыкания: значения сериализуются в кеш поставщика.
- Необязательные методы: `getAdditionalDocumentInfo(Document $document)` (дополнительные ключи данных `getFile()`), `getLangPhrasesPath()`, `isPrintable(): Result`.
- Слова нумерации `{CLIENT_ID}`, `{SELF_ID}`, `{SELF_COMPANY_ID}` требуют `Bitrix\DocumentGenerator\Integration\Numerator\DocumentNumerable` у поставщика.
- Сущность ORM: `DataProvider\EntityDataProvider`, реализуй `getTableClass()`; поля берутся из карты ORM.

## Регистрация

Поставщики находятся через `documentgenerator:onGetDataProviderList`. Зарегистрируй постоянный обработчик в установщике:

```php
// DoInstall() в install/index.php; симметричный unRegisterEventHandler() при удалении
\Bitrix\Main\EventManager::getInstance()->registerEventHandler(
    'documentgenerator', 'onGetDataProviderList', 'vendor.orders', Handler::class, 'onGetDataProviderList'
);
```

Метод обработчика в `Vendor\Orders\DocumentGenerator\Handler`:

```php
public static function onGetDataProviderList(): array
{
    return [
        OrderProvider::class => ['NAME' => OrderProvider::getLangName(), 'CLASS' => OrderProvider::class, 'MODULE' => 'vendor.orders'],
    ];
}
```

- Класс наследует `DataProvider`, реализует `Nameable`; иначе молча пропускается.
- `MODULE` равен `MODULE_ID` шаблона и ID установленного модуля; иначе `Template::setSourceType()` молча игнорирует поставщика.
- Реестр кешируется статически на хит; обработчики, добавленные после первого обращения, не видны.
- Избегай `_` в именах классов поставщиков: фильтр шаблонов разделяет по `_`.
