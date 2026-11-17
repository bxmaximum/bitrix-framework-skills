# Написание миграций

## Каркас, создаваемый add

```php
<?php

namespace Sprint\Migration;

class Version20260720120000 extends Version
{
    protected $author = '';
    protected $description = 'Добавить инфоблок новостей';
    protected $moduleVersion = '5.16.1';

    public function up()
    {
        $helper = $this->getHelperManager();
    }

    public function down()
    {
    }
}
```

Если откат невозможен, выброси из `down()` исключение `\Sprint\Migration\Exceptions\MigrationException` с причиной. Пустой `down()` допустим, когда откатывать нечего. Sprint считает его успешным и снимает отметку о применении.

## Контракт результата

| В up/down | Значение |
| --- | --- |
| Без return / true | Успех |
| return false | Ошибка «migration return false»; статус прежний |
| Исключение HelperException / MigrationException | Ошибка с сообщением |
| Старый API оставил `$APPLICATION->GetException()` | Ошибка с этим сообщением |
| `$this->restart()` / помощники restart*() | Пауза, продолжение на следующей итерации |

Журнал: `$this->outSuccess()`, `outError()`, `outWarning()`, `outInfo()` с форматами sprintf (динамический текст передавай как `('%s', $text)`), `outProgress(string $msg, int $val, int $total)`.

## Идемпотентные помощники

```php
public function up()
{
    $helper = $this->getHelperManager();

    $helper->Iblock()->saveIblockType([
        'ID' => 'content',
        'LANG' => ['ru' => ['NAME' => 'Контент', 'SECTION_NAME' => 'Разделы', 'ELEMENT_NAME' => 'Элементы']],
    ]);
    $iblockId = $helper->Iblock()->saveIblock([
        'NAME' => 'Новости',
        'CODE' => 'content_news',
        'API_CODE' => 'ContentNews',
        'LID' => ['s1'],
        'IBLOCK_TYPE_ID' => 'content',
    ]);
    $helper->Iblock()->saveProperty($iblockId, ['NAME' => 'Ссылка', 'CODE' => 'LINK']);
}

public function down()
{
    throw new \Sprint\Migration\Exceptions\MigrationException(
        'Миграция могла изменить существующий инфоблок. Для отката нужны его прежние настройки.'
    );
}
```

Имена методов: `rules/helpers-builders.md`.

## Зависимости

```php
$this->checkRequiredVersions(['Version20260101120000', OtherMigration::class]); // исключение, если не установлены
```

Свойство `$requiredVersions` устарело.

## Долгие возобновляемые миграции

Каждый restart* выполняет шаг, сохраняет параметры и бросает RestartException. CLI / административный интерфейс снова вызывают up() с начала: код до restart* повторяется на каждой итерации.

- `restartOnce($name, fn)` выполняет один раз, кеширует сериализованный результат без объектов.
- `restartWhile($name, fn(int $index): int)` возвращает следующий индекс, 0 — конец.
- `restartIterator($name, array $list, fn($item, $index))` — один элемент за итерацию, список 0..n. Подходит для десятков, слишком медленно для тысяч.

```php
public function up()
{
    $ids = $this->restartOnce('ids', fn() => $this->collectIds()); // int[]
    $this->restartWhile('batch', function (int $offset) use ($ids): int {
        $chunk = array_slice($ids, $offset, 200);
        foreach ($chunk as $id) {
            // обработка строки
        }
        $this->outProgress('Строки', $offset + count($chunk), count($ids));

        return $chunk ? $offset + count($chunk) : 0;
    });
}
```

## Файлы обмена

Генераторы данных (`IblockElementsBuilder`, `HlblockElementsBuilder`, …) пишут XML в `{exchange_dir}/{VersionName}_files/`. Доступ: `$this->getExchangeManager()`. Включай каталог в коммит версии, без персональных данных / секретов.

## Ручные миграции данных

```php
public function up()
{
    if (!\Bitrix\Main\Loader::includeModule('vendor.module')) {
        $this->outError('Модуль vendor.module не установлен');
        return false;
    }
    $result = \Vendor\Module\Model\ItemTable::add(['CODE' => 'promo', 'TITLE' => 'Акция']);
    if (!$result->isSuccess()) {
        $this->outError('%s', implode('; ', $result->getErrorMessages()));
        return false;
    }
}
```

- Предпочитай D7 ORM / сервисы; находи по бизнес-ключу для безопасного повтора.
- Прямой SQL только вне возможностей ORM / помощников, значения экранируй через SqlHelper (`bitrix-database`).
- Не допускай секретов, абсолютных путей одной машины, правок `/bitrix/`, случайных начальных данных, рабочей бизнес-логики.
