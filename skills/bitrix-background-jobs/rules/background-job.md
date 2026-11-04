# `Application::addBackgroundJob()`

`addBackgroundJob(callable $job, array $args = [], $priority = Application::JOB_PRIORITY_NORMAL)`

```php
use Bitrix\Main\Application;

Application::getInstance()->addBackgroundJob(
    [\Vendor\Module\Public\Service\WelcomeService::class, 'sendAsync'], // статический метод
    [$userId],
);
```

Порядок выполнения: `Application::end()` → отправка ответа (`fastcgi_finish_request()`) → `terminate()`:

1. Обработчики `OnAfterEpilog`;
2. `session_write_close()`: изменения сессии из задания теряются;
3. Задания по приоритету (`JOB_PRIORITY_NORMAL` = 100 первым, `JOB_PRIORITY_LOW` = 50; агенты ядра — 50, почтовые события — 49); пул БД переключён на master;
4. Добавленные из заданий задания выполняются в том же цикле.

Правила:

- Пользователь уже получил ответ, но PHP-воркер занят до завершения заданий. Ограничивай работу секундами.
- Исключение одного задания записывается в лог; остальные выполняются, после чего исключение выбрасывается повторно.
- Гарантии доставки нет: сбой процесса или `exit` теряет задание. Для повторов, объёма или отдельного воркера используй Messenger (`rules/messenger.md`).
- Передавай скаляры в `$args`; не захватывай объекты запроса/сессии.
- Работает только при завершении через `Application::end()`/`terminate()` (обычные страницы, контроллеры, CLI `bitrix.php`).
