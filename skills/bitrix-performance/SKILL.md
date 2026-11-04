---
name: bitrix-performance
description: 'Производительность Битрикс: композит (createFrame, StaticArea), динамические области и реплики чтения модуля cluster. Используй при медленных страницах и запросах или настройке композита.'
---

# Производительность

Baseline: main 23.0+ · Verified: main 26.1000.0

Перед PHP-кодом прочитай `bitrix-framework`, если ещё не читал его в этой задаче.

Кеширование: `bitrix-caching`. Профилирование SQL: `bitrix-database` (SqlTracker). Сессии: `bitrix-sessions`.

## Композит

Статический HTML берётся из композитного кеша, в том числе напрямую через NGINX; динамические области загружаются следующим запросом. Включение: Настройки → Композитный сайт. Ядро: `Bitrix\Main\Composite\Engine`, `Page`, `StaticArea`, `BufferArea`.

В шаблоне компонента (`$this` — `CBitrixComponentTemplate`):

```php
$this->setFrameMode(true);                 // шаблон совместим с композитом

$frame = $this->createFrame()->begin('Загрузка…');  // заглушка в кешированном HTML
echo htmlspecialcharsbx($arResult['USER_NAME']);   // динамическое содержимое
$frame->end();
```

Объёмная заглушка: `begin()` → динамическое содержимое → `beginStub()` → HTML заглушки → `end()`.

Вне компонентов (шапка шаблона сайта, функции в `init.php`):

```php
use Bitrix\Main\Composite\StaticArea;

$area = new StaticArea('vendor_cart_counter');
$area->setStub('<span>0</span>');
$area->startDynamicArea();
echo (int)$basketCount;
$area->finishDynamicArea();
```

- Настройка компонента без кода: параметры `COMPOSITE_FRAME_MODE` (`A`/`Y`/`N`) и `COMPOSITE_FRAME_TYPE` (`AUTO`, `STATIC`, `DYNAMIC_WITH_STUB`, `DYNAMIC_WITHOUT_STUB`).
- Исключить страницу: `\Bitrix\Main\Composite\Page::getInstance()->markNonCacheable()`.
- Атрибута `data-dynamic` и свойства страницы `composite_frame_mode` нет.
- Не оставляй личные данные (имя, корзину, права, CSRF-токены) в статической части. Проверь динамические области гостем и авторизованным пользователем.

## Запросы

- Выбирай нужные поля; индексируй столбцы `filter`/`order`.
- Устраняй N+1: `fetchCollection()` + `fill()` либо связи в `select` (`bitrix-orm`).
- Используй пакетную запись (`addMulti`, `updateMulti`) вместо записи по строке в цикле.
- Повторные чтения: кеш ORM-запроса; собранные результаты: тегированный кеш данных.

## Реплики чтения

С модулем `cluster` запросы `SELECT` через `query()` могут уходить на реплику. Чтение сразу после записи закрепляй за основной БД:

```php
$pool = \Bitrix\Main\Application::getInstance()->getConnectionPool();
$pool->useMasterOnly(true);
try
{
    $row = OrderTable::getById($id)->fetch();
}
finally
{
    $pool->useMasterOnly(false);
}
```

useMasterOnly() меняет счётчик вложенности: каждому true нужен false в finally.

Дополнительные БД для аналитики и отчётов: именованное подключение в `connections` (`bitrix-database`).

## Проверка

- [ ] Динамические области композита проверены гостем и авторизованным пользователем.
- [ ] Если настроена отдача композита через NGINX, проверено попадание в статический кеш.
- [ ] Медленные запросы найдены через SqlTracker, нужные индексы созданы.
