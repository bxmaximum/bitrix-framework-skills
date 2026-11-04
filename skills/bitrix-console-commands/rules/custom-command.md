# Своя консольная команда

## Класс в `lib/Cli/Command/<Domain>/`

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Cli\Command\Feed;

use Bitrix\Main\DI\ServiceLocator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Vendor\Module\Public\Service\FeedService;

#[AsCommand(name: 'vendor:feed:rebuild', description: 'Пересобрать товарный фид')]
final class RebuildCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Размер пакета', '1000')
            ->addOption('dry-run', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $service = ServiceLocator::getInstance()->get(FeedService::class);
        $result = $service->rebuild((int)$input->getOption('limit'), (bool)$input->getOption('dry-run'));

        if (!$result->isSuccess())
        {
            $output->writeln('<error>' . implode('; ', $result->getErrorMessages()) . '</error>');

            return Command::FAILURE;
        }
        $output->writeln('<info>Готово</info>');

        return Command::SUCCESS;
    }
}
```

- Имя задаётся через `#[AsCommand]` или `$this->setName()` в `configure()`; добавляй префикс вендора против конфликтов.
- Логика находится в сервисе с `Result`; команда разбирает ввод, вызывает сервис, печатает результат и возвращает `SUCCESS`/`FAILURE`/`INVALID`.
- В длинных циклах показывай прогресс, освобождай память пакетами и соблюдай `--limit`/лимит времени, чтобы задания cron не пересекались.

## Регистрация в `.settings.php` установленного модуля

```php
return [
    'console' => [
        'value' => [
            'commands' => [
                \Vendor\Module\Cli\Command\Feed\RebuildCommand::class,
            ],
        ],
        'readonly' => true,
    ],
];
```

Секция `console`, ключ `commands`. После регистрации команда видна в `php bitrix/bitrix.php list`.

## Cron

```cron
*/10 * * * * cd /var/www/site && php bitrix/bitrix.php vendor:feed:rebuild -n >> /var/log/site/feed.log 2>&1
*/5  * * * * cd /var/www/site && php bitrix/bitrix.php messenger:consume --time-limit=270 -n
```

- Сначала `cd`: `bitrix.php` строит `DOCUMENT_ROOT` из рабочего каталога.
- Защищай долгие задания от пересечения: `flock -n /tmp/feed.lock php …` или блокировка БД внутри сервиса (`bitrix-database`).
- CLI не имеет авторизованного пользователя и сессии: передавай ID пользователя/сайта параметрами.


## DI и форматированный вывод (**Since main 26.900**)

Команда из `console.commands` создаётся контейнером: зависимости конструктора доступны автоматически. Не регистрируй конкретный класс с одним `className`, отключающим автосборку (`bitrix-service-locator`). До 26.900 оставляй конструктор без обязательных зависимостей и получай сервис в `execute()`, как в примере выше.

Для стандартного табличного/JSON-вывода наследуй `Bitrix\Main\Cli\Command\AbstractFormattedCommand`: `execute()` окончательный, переопределяй `executeFormatted()`. Конструктор родителя получает `Bitrix\Main\Cli\Output\OutputFormatter`; в своём конструкторе передай его в `parent::__construct($outputFormatter)`. Базовый класс добавляет `--format=table|json` и перехватывает `Throwable` с кодом `UNEXPECTED_ERROR`, скрывая детали. Ожидаемые ошибки возвращай явно:

```php
<?php declare(strict_types=1);

namespace Vendor\Module\Cli\Command\Feed;

use Bitrix\Main\Cli\Command\AbstractFormattedCommand;
use Bitrix\Main\Cli\Output\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Vendor\Module\Public\Service\FeedService;

final class PreviewCommand extends AbstractFormattedCommand
{
    public function __construct(OutputFormatter $formatter, private readonly FeedService $feed)
    {
        parent::__construct($formatter);
    }

    protected function configure(): void
    {
        $this->setName('vendor:feed:preview');
    }

    protected function executeFormatted(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->feed->rebuild(1000, true); // предварительный запуск без записи
        if (!$result->isSuccess())
        {
            return $this->renderError($output, 'FEED_FAILED', implode('; ', $result->getErrorMessages()));
        }

        return $this->renderSuccess($output, $result->getData());
    }
}
```

На старом ядре используй обычный `Command::execute()` и свой формат вывода, не наследуй отсутствующий базовый класс.
