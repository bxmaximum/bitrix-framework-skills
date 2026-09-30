# Own console command

## Class — `lib/Cli/Command/<Domain>/`

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

#[AsCommand(name: 'vendor:feed:rebuild', description: 'Rebuild product feed')]
final class RebuildCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Batch size', '1000')
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
        $output->writeln('<info>Done</info>');

        return Command::SUCCESS;
    }
}
```

- Name via `#[AsCommand]` or `$this->setName()` in `configure()`; prefix it with your vendor to avoid clashes.
- Logic in a service returning `Result`; the command only parses input, calls, prints, returns `SUCCESS`/`FAILURE`/`INVALID`.
- Long loops: print progress, release memory (process in batches), respect a `--limit`/time limit so cron runs don't overlap.

## Register — module `.settings.php` (module must be installed)

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

Section `console`, key `commands`. Then `php bitrix/bitrix.php list` shows it.

## Cron

```cron
*/10 * * * * cd /var/www/site && php bitrix/bitrix.php vendor:feed:rebuild -n >> /var/log/site/feed.log 2>&1
*/5  * * * * cd /var/www/site && php bitrix/bitrix.php messenger:consume --time-limit=270 -n
```

- `cd` first: `bitrix.php` builds `DOCUMENT_ROOT` from the working dir.
- Overlap guard for long jobs: `flock -n /tmp/feed.lock php …` or a DB lock inside the service (`bitrix-database`).
- CLI has no authorized user and no session: pass user/site IDs as options.
