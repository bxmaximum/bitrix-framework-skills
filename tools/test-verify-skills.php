#!/usr/bin/env php
<?php

/** Регрессионные проверки валидатора; настоящее ядро и зависимости не нужны. */
declare(strict_types=1);

$root = sys_get_temp_dir() . '/verify-skills-tests-' . bin2hex(random_bytes(6));
mkdir($root, 0700, true);
register_shutdown_function(static function () use ($root): void {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($root);
});

$kernel = $root . '/modules';
mkdir($kernel . '/main/lib', 0700, true);
file_put_contents($kernel . '/main/lib/example.php', '<?php namespace Bitrix\Main; class Example {}');

function skillDocument(string $body = '', string $name = 'bitrix-fixture', ?string $description = null): string
{
    $description ??= "'Проверка валидатора.'";
    return "---\nname: $name\ndescription: $description\n---\n\n# Проверка\n\n"
        . "Baseline: main 23.0+ · Verified: main 26.1000.0\n\n" . $body;
}

/** @return array{exit: int, data: ?array, stdout: string, stderr: string} */
function verifyFixture(string $document, array $options = [], string $name = 'bitrix-fixture', ?array $env = null): array
{
    global $root;
    static $seq = 0;
    $skills = $root . '/case-' . (++$seq) . '/skills';
    mkdir($skills . '/' . $name, 0700, true);
    file_put_contents($skills . '/' . $name . '/SKILL.md', $document);
    $cmd = [PHP_BINARY, '-n', __DIR__ . '/verify-skills.php', '--skills=' . $skills, '--json', ...$options];
    $process = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start verifier');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return ['exit' => proc_close($process), 'data' => json_decode($stdout, true), 'stdout' => $stdout, 'stderr' => $stderr];
}

function hasIssue(array $result, string $category, string $level): bool
{
    foreach ($result['data']['issues'] ?? [] as $issue) {
        if ($issue['cat'] === $category && $issue['level'] === $level) {
            return true;
        }
    }
    return false;
}

$failures = 0;
$tests = 0;
function checkResult(string $name, array $result, bool $passed): void
{
    global $failures, $tests;
    $tests++;
    echo ($passed ? 'ok ' : 'FAIL ') . $name . "\n";
    if (!$passed) {
        $failures++;
        fwrite(STDERR, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
    }
}

$r = verifyFixture(skillDocument(), ['--files-only']);
checkResult('files-only явно сообщает об отсутствии проверки API', $r,
    $r['exit'] === 0 && $r['data']['summary']['symbol_checks'] === false && $r['data']['summary']['classes'] === 0);

$r = verifyFixture(skillDocument("```php\nif (\n```\n"), ['--files-only']);
checkResult('PHP-синтаксис проверяется и без ядра', $r, $r['exit'] === 1 && hasIssue($r, 'lint', 'ERROR'));

$missing = '`\Bitrix\Main\MissingClass::class`';
$r = verifyFixture(skillDocument("Используй $missing.\n"), ['--kernel=' . $kernel]);
checkResult('несуществующий положительный API остаётся ошибкой', $r, $r['exit'] === 1 && hasIssue($r, 'symbol', 'ERROR'));

$r = verifyFixture(skillDocument("Не используй $missing. <!-- verify:negative -->\n"), ['--kernel=' . $kernel]);
checkResult('явный отрицательный inline-пример даёт предупреждение', $r,
    $r['exit'] === 0 && hasIssue($r, 'symbol', 'WARN') && !hasIssue($r, 'symbol', 'ERROR'));

$r = verifyFixture(skillDocument("Не используй $missing.\n"), ['--kernel=' . $kernel]);
checkResult('слова в прозе не скрывают ошибки API', $r, $r['exit'] === 1 && hasIssue($r, 'symbol', 'ERROR'));

$r = verifyFixture(skillDocument("<!-- verify:negative -->\n```php\nnew \\Bitrix\\Main\\MissingClass();\n```\n"), ['--kernel=' . $kernel]);
checkResult('аннотация не ослабляет проверку PHP-блоков', $r, $r['exit'] === 1 && hasIssue($r, 'symbol', 'ERROR'));

$r = verifyFixture(skillDocument(str_repeat('я', 4000)), ['--files-only']);
checkResult('UTF-8 байты не считаются символами бюджета', $r, $r['exit'] === 0 && !hasIssue($r, 'size', 'WARN'));

$r = verifyFixture(skillDocument(str_repeat('я', 6001)), ['--files-only']);
checkResult('превышение бюджета Unicode замечено', $r, hasIssue($r, 'size', 'WARN'));

foreach (['Checklist', 'Проверка', '検証'] as $heading) {
    $r = verifyFixture(skillDocument("## $heading\n\n" . str_repeat("- [ ] Пункт\n", 7)), ['--files-only']);
    checkResult('лимит чеклиста не зависит от языка: ' . $heading, $r, hasIssue($r, 'content', 'WARN'));
}

$r = verifyFixture(skillDocument('', description: "'Незакрытая строка"), ['--files-only']);
checkResult('незакрытая YAML-кавычка отклоняется', $r, $r['exit'] === 1 && hasIssue($r, 'frontmatter', 'ERROR'));

$r = verifyFixture(skillDocument('', description: "'Проверка ''кавычек''.'"), ['--files-only']);
checkResult('корректные YAML-апострофы принимаются', $r, $r['exit'] === 0);

$r = verifyFixture(skillDocument('', 'bad--name'), ['--files-only'], 'bad--name');
checkResult('имя с двойным дефисом отклоняется', $r, $r['exit'] === 1 && hasIssue($r, 'frontmatter', 'ERROR'));

$r = verifyFixture(str_replace("name: bitrix-fixture\n", "name: bitrix-fixture\nname: bitrix-fixture\n", skillDocument()), ['--files-only']);
checkResult('дубли ключей frontmatter отклоняются', $r, $r['exit'] === 1 && hasIssue($r, 'frontmatter', 'ERROR'));

$r = verifyFixture(skillDocument('Прочитай `rules/missing.md`.'), ['--files-only']);
checkResult('битая ссылка на rules отклоняется', $r, $r['exit'] === 1 && hasIssue($r, 'link', 'ERROR'));

$r = verifyFixture(skillDocument());
checkResult('полная проверка требует явного ядра', $r, $r['exit'] === 2);

$r = verifyFixture(skillDocument(), ['--files-only', '--kernel=' . $kernel]);
checkResult('files-only нельзя выдать за проверку ядра', $r, $r['exit'] === 2);

// Проверяем, что глобальный auto_prepend_file не выполняется в индексирующих процессах.
for ($i = 0; $i < 401; $i++) {
    file_put_contents($kernel . '/main/lib/fixture' . $i . '.php', '<?php namespace Bitrix\Main; class Fixture' . $i . ' {}');
}
$ini = $root . '/php.ini';
file_put_contents($ini, 'auto_prepend_file=' . $root . '/does-not-exist.php' . "\n");
$env = getenv();
$env['PHPRC'] = $ini;
$r = verifyFixture(skillDocument(), ['--kernel=' . $kernel, '--jobs=2', '--no-cache'], env: $env);
checkResult('индексирующие процессы изолированы от php.ini приложения', $r, $r['exit'] === 0 && $r['data']['summary']['classes'] >= 402);

printf("%d tests, %d failures\n", $tests, $failures);
exit($failures === 0 ? 0 : 1);
