# Карта таблета

## Имена и размещение

- `SomethingTable extends \Bitrix\Main\ORM\Data\DataManager`; имя без `Table` — класс объекта (`Post`, сгенерированные `EO_Post`, `EO_Post_Collection`).
- Соглашение проекта: `lib/Model/`. `make:tablet` строит карту **существующей** таблицы БД (нужен `perfmon`), по умолчанию в `Vendor\Module\Tablet`:

```bash
php bitrix/bitrix.php make:tablet vendor_module_post vendor.module --namespace 'Vendor\Module\Model'
php bitrix/bitrix.php orm:annotate -m vendor.module     # классы EO_* для IDE
```

## Каркас

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
        return 'VENDOR_MODULE_POST';   // только если у сущности есть пользовательские поля
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

Предпочитай цепочку `configure*()` устаревшему массиву `['primary' => true]`.

## Типы полей

- `IntegerField`, `FloatField`, `DecimalField`, `StringField`, `TextField`, `BooleanField` (`configureValues('N', 'Y')`), `EnumField` (`configureValues([...])`).
- `DateField` / `DatetimeField` → `Type\Date` / `Type\DateTime` (`bitrix-datetime`).
- `ExpressionField('FULL_NAME', 'CONCAT(%s, " ", %s)', ['NAME', 'LAST_NAME'])` — вычисляемое поле.
- `CryptoField` — шифрование хранимого значения (`bitrix-security`).
- `ArrayField` по умолчанию хранит **JSON**; сохраняй этот формат. Для старого PHP-сериализованного столбца не разрешай восстановление объектов:

```php
(new Fields\ArrayField('PARAMS'))
    ->configureSerializationPhp()
    ->configureUnserializeCallback(static fn ($v) => unserialize((string)$v, ['allowed_classes' => false]));
```

**Since main 26.900** `ArrayField::decodePhp()` и `Field::unserialize()` используют `allowed_classes=false`. Старые записанные объекты (включая `Type\DateTime` и DTO) читаются как `__PHP_Incomplete_Class`; скаляры и массивы сохраняют поведение. Переведи такие значения в JSON/скаляры отдельной миграцией; не включай создание объектов обратно для недоверенных данных. На старом ядре оставляй явный безопасный обработчик десериализации из примера выше.

## Преобразователи данных поля

Метода таблета `fetchDataModification()` нет. Преобразователи задаются на полях:

```php
(new Fields\StringField('PHONE'))
    ->addSaveDataModifier(static fn ($value) => preg_replace('/\D+/', '', (string)$value))
    ->addFetchDataModifier(static fn ($value, $query, array $row, string $alias) => '+' . $value);
```

Преобразователи чтения выполняются после приведения типов БД. Формат массива: `'fetch_data_modification' => fn () => [$callable]`.

## Связи

```php
(new Fields\Relations\OneToMany('COMMENTS', CommentTable::class, 'POST'))   // 'POST' = Reference в CommentTable
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

`Join::on()` возвращает `ConditionTree`; два столбца сравнивай через `new \Bitrix\Main\ORM\Query\Filter\Expressions\ColumnExpression('this.CODE')`.

## Пользовательские поля

`getUfId()` подключает `UF_*` к select/filter и объектам (`get('UF_X')`, `set('UF_X', $v)`). `Bitrix\Main\Entity\UField` устарел.
