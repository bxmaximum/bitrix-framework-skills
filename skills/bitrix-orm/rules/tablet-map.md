# Tablet map

## Naming and location

- `SomethingTable extends \Bitrix\Main\ORM\Data\DataManager`; the name without `Table` is the object class (`Post`, generated `EO_Post`, `EO_Post_Collection`).
- Project convention: `lib/Model/`. `make:tablet` builds the map from an **existing** DB table (needs `perfmon`) and defaults to `Vendor\Module\Tablet`:

```bash
php bitrix/bitrix.php make:tablet vendor_module_post vendor.module --namespace 'Vendor\Module\Model'
php bitrix/bitrix.php orm:annotate -m vendor.module     # EO_* classes for the IDE
```

## Skeleton

```php
<?php declare(strict_types=1);

namespace Vendor\Module\Model;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UserTable;

final class PostTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'vendor_module_post';
    }

    public static function getUfId(): string
    {
        return 'VENDOR_MODULE_POST';   // only if the entity has user fields
    }

    public static function getMap(): array
    {
        return [
            (new Fields\IntegerField('ID'))->configurePrimary()->configureAutocomplete(),
            (new Fields\StringField('TITLE'))->configureRequired()->configureSize(255)
                ->addValidator(new Fields\Validators\LengthValidator(null, 255)),
            (new Fields\TextField('BODY'))->configureNullable(),
            (new Fields\BooleanField('ACTIVE'))->configureValues('N', 'Y')->configureDefaultValue('Y'),
            (new Fields\DatetimeField('CREATED_AT'))->configureDefaultValue(static fn () => new DateTime()),
            (new Fields\ArrayField('SETTINGS'))->configureNullable(),                     // JSON
            (new Fields\IntegerField('AUTHOR_ID'))->configureRequired(),
            (new Fields\Relations\Reference('AUTHOR', UserTable::class, ['=this.AUTHOR_ID' => 'ref.ID']))
                ->configureJoinType('LEFT'),
        ];
    }
}
```

Fluent `configure*()` over the legacy array format (`['primary' => true]`).

## Field types

- `IntegerField`, `FloatField`, `DecimalField`, `StringField`, `TextField`, `BooleanField` (`configureValues('N', 'Y')`), `EnumField` (`configureValues([...])`).
- `DateField` / `DatetimeField` → `Type\Date` / `Type\DateTime` (`bitrix-datetime`).
- `ExpressionField('FULL_NAME', 'CONCAT(%s, " ", %s)', ['NAME', 'LAST_NAME'])` — computed.
- `CryptoField` — encrypted at rest (`bitrix-security`).
- `ArrayField` stores **JSON** by default; keep it. For a legacy PHP-serialized column never decode objects:

```php
(new Fields\ArrayField('PARAMS'))
    ->configureSerializationPhp()
    ->configureUnserializeCallback(static fn ($v) => unserialize((string)$v, ['allowed_classes' => false]));
```

## Per-field data modifiers

There is no tablet-level `fetchDataModification()`. Modifiers live on fields:

```php
(new Fields\StringField('PHONE'))
    ->addSaveDataModifier(static fn ($value) => preg_replace('/\D+/', '', (string)$value))
    ->addFetchDataModifier(static fn ($value, $query, array $row, string $alias) => '+' . $value);
```

Fetch modifiers run after DB type conversion. Array format: `'fetch_data_modification' => fn () => [$callable]`.

## Relations

```php
(new Fields\Relations\OneToMany('COMMENTS', CommentTable::class, 'POST'))   // 'POST' = Reference in CommentTable
    ->configureJoinType('LEFT'),
(new Fields\Relations\ManyToMany('TAGS', TagTable::class))
    ->configureTableName('vendor_module_post_tag')
    ->configureLocalPrimary('ID', 'POST_ID')
    ->configureRemotePrimary('ID', 'TAG_ID'),
new Fields\Relations\Reference(
    'GROUP',
    GroupTable::class,
    \Bitrix\Main\ORM\Query\Join::on('this.GROUP_ID', 'ref.ID')->where('ref.ACTIVE', 'Y'),
),
```

`Join::on()` returns a `ConditionTree`; compare two columns with `new \Bitrix\Main\ORM\Query\Filter\Expressions\ColumnExpression('this.CODE')`.

## User fields

`getUfId()` attaches `UF_*` to select/filter and objects (`get('UF_X')`, `set('UF_X', $v)`). `Bitrix\Main\Entity\UField` is deprecated.
