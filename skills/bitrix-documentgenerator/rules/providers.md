# Custom data provider

A provider turns a source value (usually your entity id) into fields for placeholders. Put it in your module: `/local/modules/vendor.orders/lib/DocumentGenerator/OrderProvider.php`.

## Root provider

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
        $id = (int)$source;                                    // field catalog passes ' '
        if ($id > 0)
        {
            $this->data = ServiceLocator::getInstance()->get(OrderService::class)->getDocumentData($id); // ['NUMBER' => …, 'DATE' => Date, 'ROWS' => [...]] or null
        }
    }

    public static function getLangName(): string
    {
        return 'Order';
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
            'NUMBER' => ['TITLE' => 'Number', 'REQUIRED' => 'Y'],
            'DATE' => ['TITLE' => 'Date', 'TYPE' => self::FIELD_TYPE_DATE],
            'ITEMS' => [
                'TITLE' => 'Items',
                'PROVIDER' => ArrayDataProvider::class,
                'VALUE' => [$this, 'getItems'],
                'OPTIONS' => ['ITEM_PROVIDER' => OrderItemProvider::class, 'ITEM_NAME' => 'ITEM', 'ITEM_TITLE' => 'Item'],
            ],
        ];
    }

    public function getItems(): array
    {
        $manager = DataProviderManager::getInstance();
        $items = [];
        foreach ($this->data['ROWS'] ?? [] as $row)
        {
            $item = $manager->getDataProvider(OrderItemProvider::class, $row, [], $this); // parent = $this
            if ($item) { $items[] = $item; }
        }
        return $items;
    }
}
```

Item provider — `HashDataProvider` keeps only keys listed in `getFields()`:

```php
final class OrderItemProvider extends \Bitrix\DocumentGenerator\DataProvider\HashDataProvider
{
    public function getFields(): array
    {
        return $this->fields ??= ['NAME' => ['TITLE' => 'Name'], 'PRICE' => ['TITLE' => 'Price']];
    }
}
```

Placeholders: `{Number}`, `{Date~d.m.Y}`, `{ItemsItemName}`, `{ItemsItemPrice}`, `{ItemsNumber}`.

## Rules

- `$this->data` holds field values by code; `isLoaded()` = `data !== null`. A field with `VALUE` is computed: string = another field's code, `[$this, 'method']` or `[$object, 'method']` = callback with the placeholder as argument.
- Field keys: `TITLE`, `VALUE`, `TYPE` (`FIELD_TYPE_DATE|IMAGE|STAMP|NAME|PHONE|TEXT`), `FORMAT`, `PROVIDER` + `OPTIONS` (nested provider), `REQUIRED => 'Y'`, `HIDE_ROW => 'Y'`.
- Keep constructor and `getFields()` cheap and data-free: the field catalog builds providers with source `' '`.
- Keep parameters untyped as in the base class (`$source`, `$userId`, `$name`); only return types may be added.
- `hasAccess()` of the base class returns `false` unless a parent provider grants access — root providers must override it. Pass `$this` as parent to nested providers.
- Use `[$this, 'method']`, not closures, in `VALUE` — provider values are serialized for the provider cache.
- Optional: `getAdditionalDocumentInfo(Document $document)` (extra keys in `getFile()` data), `getLangPhrasesPath()`, `isPrintable(): Result`.
- Numbering words `{CLIENT_ID}`, `{SELF_ID}`, `{SELF_COMPANY_ID}` need `Bitrix\DocumentGenerator\Integration\Numerator\DocumentNumerable` on the provider.
- Entity-backed shortcut: `DataProvider\EntityDataProvider` (implement `getTableClass()`; fields from the ORM map).

## Registration

Providers are found through the `documentgenerator:onGetDataProviderList` event. Register it persistently in your installer:

```php
// install/index.php DoInstall(); mirror with unRegisterEventHandler() on uninstall
\Bitrix\Main\EventManager::getInstance()->registerEventHandler(
    'documentgenerator', 'onGetDataProviderList', 'vendor.orders', Handler::class, 'onGetDataProviderList'
);
```

Handler method in `Vendor\Orders\DocumentGenerator\Handler`:

```php
public static function onGetDataProviderList(): array
{
    return [
        OrderProvider::class => ['NAME' => OrderProvider::getLangName(), 'CLASS' => OrderProvider::class, 'MODULE' => 'vendor.orders'],
    ];
}
```

- The class must extend `DataProvider` and implement `Nameable`, or it is skipped silently.
- `MODULE` must equal the template's `MODULE_ID` and be an installed module; otherwise `Template::setSourceType()` silently ignores the provider.
- The registry is cached statically per hit — handlers added after the first lookup are not seen.
- Avoid `_` in provider class names (template filters split on `_`).
