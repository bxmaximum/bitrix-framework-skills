# Агенты (`CAgent`)

## Регистрация

```php
\CAgent::AddAgent(
    name: \Vendor\Module\Infrastructure\Agent\SyncAgent::class . '::run();',
    module: 'vendor.module',
    period: 'N',          // см. «Периодичность»
    interval: 300,        // секунды
    next_exec: (new \Bitrix\Main\Type\DateTime())->add('+5 minutes')->toString(), // '' = сейчас
    existError: false,    // по умолчанию true: существующий агент → false + ThrowException
);
```

- До `Type\Callback` имя `name` — PHP-код для `eval`, идентификатор агента вместе с `user_id`. Аргументы включаются в строку: `"::run('s2', {$offset});"`; приводи типы и экранируй. Новый безопасный формат без `eval` описан ниже; старые строки по-прежнему поддерживаются.
- Аргумент `datecheck` игнорируется ядром.
- `make:agent Sync -m vendor.module` (**Since main 25.900**) создаёт `Vendor\Module\Infrastructure\Agent\SyncAgent` с `run()`, возвращающим `__METHOD__ . '();'`.
- При удалении модуля: `\CAgent::RemoveModuleAgents('vendor.module')` (это также делает `ModuleManager::unRegisterModule()`).

## Вызов без `eval` (**Since main 26.900**)

В новом коде передавай `Bitrix\Main\Type\Callback` в `AddAgent()`/`Update()`/`RemoveAgent()`: ядро хранит JSON и вызывает функцию или `[класс, метод]` напрямую. Аргументы — список скаляров, `null` и вложенных массивов; объекты, ресурсы и NaN и бесконечности запрещены.

```php
use Bitrix\Main\Type\Callback;
use Vendor\Module\Infrastructure\Agent\SyncAgent;

\CAgent::AddAgent(new Callback([SyncAgent::class, 'run'], [0]), 'vendor.module', 'N', 300);
```

Метод может вернуть `new Callback([self::class, 'run'], [$nextOffset])` для следующего шага либо `null` для удаления агента. Для постоянного задания после последнего шага возвращай Callback с начальным смещением. До 26.900 используй строковый вариант ниже, формируя аргументы только из проверенных значений. Старые строки продолжают выполняться через `eval`; внедрение Callback не переводит их автоматически. При поиске ранее зарегистрированных агентов учитывай оба формата NAME.

## Метод агента

```php
final class SyncAgent
{
    public static function run(int $offset = 0): string
    {
        $next = ServiceLocator::getInstance()->get(SyncService::class)->syncBatch($offset, 100);

        return $next === null
            ? self::class . '::run();'                    // завершено: начать заново в следующий период
            : self::class . '::run(' . $next . ');';      // продолжить с $next
    }
}
```

- Возвращай строку следующего вызова для сохранения агента; `''` или `null` удаляют его.
- Исключение записывается в лог; агент остаётся заблокированным и повторяется после истечения блокировки (10 минут).
- Ядро загружает модуль агента до вызова; часовой пояс отключён (`CTimeZone::Disable()`), `$USER` равен `null`; глобальный `$pPERIOD` можно изменить для следующего интервала.

## Периодичность

- `period: 'N'`: `NEXT_EXEC = now + interval` после каждого запуска.
- `period: 'Y'`: `NEXT_EXEC = previous planned time + interval` (догоняет расписание после простоя).
- При разделении с cron (ниже) флаг также определяет место выполнения.

## Долгий агент может запуститься дважды

Запуск выбирает просроченные агенты и ставит `DATE_CHECK = now + 600 s`; спустя 570 секунд новые агенты не запускает. Агент, работающий дольше 10 минут, снова доступен для выбора и запускается **параллельно**. Первый процесс не останавливается.

- Делай работу шагами: возвращай строку вызова со смещением, как выше.
- Защищай критические секции: `Application::getConnection()->lock('vendor_sync', 0)` / `unlock()` (`bitrix-database`).
- Часы работы → Messenger или консольная команда.

## Хиты и cron

На хитах агенты выполняются как фоновое задание низкого приоритета после ответа (`main:check_agents` = `Y`). Страницу не задерживают, но занимают PHP-воркер; при малом трафике опаздывают.

Частичный cron: хиты выполняют агенты с `period: 'Y'`, cron — с `period: 'N'`:

```php
\Bitrix\Main\Config\Option::set('main', 'agents_use_crontab', 'Y');
// crontab: */5 * * * * php -f /var/www/site/bitrix/modules/main/tools/cron_events.php
```

Все агенты на cron:

1. `Option::set('main', 'agents_use_crontab', 'N')` и `Option::set('main', 'check_agents', 'N')` отключают запуск на хитах.
2. В `dbconn.php`: `if (!(defined('CHK_EVENT') && CHK_EVENT === true)) { define('BX_CRONTAB_SUPPORT', true); }`
3. В своём `php_interface/cron_events.php`: задай `$_SERVER['DOCUMENT_ROOT']`, `define('NO_KEEP_STATISTIC', true)`, `define('NOT_CHECK_PERMISSIONS', true)`, `define('CHK_EVENT', true)`, подключи `prolog_before.php`, вызови `@set_time_limit(0)`, `CAgent::CheckAgents()`, затем `define('BX_CRONTAB_SUPPORT', true)`, `define('BX_CRONTAB', true)`, `CEvent::CheckEvents()` (очередь писем, `bitrix-mail`).
4. Запускай cron каждые 1–5 минут; убери задание для `tools/cron_events.php`, чтобы избежать двойного запуска.

Проверяй сохранённое значение, а не значение по умолчанию: `Option::get('main', 'agents_use_crontab', 'Z')` → `Z` означает, что оно не задано.

## Многосайтовость

Агенты общие: один список, без `SITE_ID` и текущего сайта. Передавай сайт аргументом (`"::run('s2');"`), при необходимости регистрируй отдельный агент для каждого сайта. Отдельный cron на каждый сайт не нужен.
