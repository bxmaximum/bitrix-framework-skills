#!/usr/bin/env php
<?php

/**
 * Static verification of skills/ against Bitrix kernel sources. Run with --help.
 * Single file, PHP 8.2+, no dependencies.
 */

declare(strict_types=1);

const VS_HELP = <<<'TXT'
verify-skills: static checks of skills/*/SKILL.md and rules/*.md against Bitrix kernel sources.

Usage:
  php tools/verify-skills.php --kernel=<modules dir> [--kernel=<dir> ...] [options] [skill-name ...]

Examples:
  php tools/verify-skills.php --kernel=/srv/b24/www/bitrix/modules
  php tools/verify-skills.php --kernel=/srv/b24/www/bitrix/modules bitrix-orm bitrix-controllers
  php tools/verify-skills.php --kernel=/srv/b24/www/bitrix/modules \
      --kernel=/srv/site/www/bitrix/modules/sprint.migration --warnings
  php tools/verify-skills.php --kernel=/srv/b24/www/bitrix/modules --json > report.json

Options:
  --kernel=DIR    bitrix/modules directory or a single module directory. Repeatable, at least one
                  is required. Use a Bitrix24 box kernel so that crm, tasks, im etc. are indexed.
  --skills=DIR    skills directory (default: ./skills, else <repo>/skills)
  --warnings      print WARN lines (default: only their count in the summary)
  --json          machine-readable output with all issues, including WARN
  --no-cache      rebuild the kernel index instead of reading tools/.cache/
  --jobs=N        parallel processes for indexing and php -l (default: CPU count)
  --guess=LEVEL   severity for members of unimported short class names (Loader::x) that
                  were matched to kernel classes by short name: error (default) or warn
  -h, --help      show this help

Checks, per skill:
  Files    frontmatter: name == directory, description present and <= 250 chars, no unquoted YAML
           traps (`: `, ` #`) and no `\\` inside double quotes (ERROR);
           size budgets: SKILL.md 6 KB, router SKILL.md (has rules/) 2.5 KB, rules/*.md 6 KB (WARN);
           rules/*.md mentions and relative .md links resolve (ERROR); rules/*.md not referenced
           from SKILL.md (WARN); backticked `bitrix-…` / `bitrix24-…` skill names exist (ERROR);
           any mention of AGENTS.md (ERROR); no "Baseline|Scope: … · Verified: …" line, "How to use" /
           "Progressive disclosure" sections, Covers/Applied when/Key terms in description,
           checklist longer than 6 items (WARN).
  Lint     php -l for every ```php block. `...` placeholders are neutralised first. A block that
           fails is retried wrapped in a class, a function and an array literal; ERROR only when
           every variant fails (reported at the md line of the error). Compile-time
           deprecations are WARN.
  Symbols  In ```php blocks and in inline `code`: use-imports, new X(), X::method(), X::CONST,
           X::class, #[Attr(...)], extends/implements, parent::method(), named arguments,
           non-static methods called statically, $var->method() when the class of $var is known
           (new X, X::getInstance(), typed parameter, kernel return types incl. @return), and
           $this->method() in example classes extending a kernel class (WARN unless a close
           kernel name exists: the helper may just be left out of the example).
           Enforced for Bitrix\<Module>\… of indexed modules, Sprint\Migration\… and global C*
           classes; runtime classes (iblock Elements\Element*Table, Section{ID}Table, ORM EO_*) are skipped.
           ORM objects accept get*/set*/has*/fill*/… accessors of fields named in their tablet.
           Unimported short names (Loader::includeModule) are matched to kernel classes by short
           name; an issue is reported only if no candidate fits, as ERROR when the match is
           unambiguous (single candidate, or the shallowest one in the skill's module or in
           Bitrix\Main), otherwise WARN. Inline `code` is lenient: X::name may be a method or a
           constant, X::PREFIX_* is a prefix, static calls are checked only when arguments contain
           variables (ERROR for singletons with getInstance(), else WARN), and errors on lines
           that call an API removed / deprecated / "do not use" become WARN.

Kernel index:
  Built statically with PhpToken from every *.php under each --kernel dir (lang/, install/js/,
  component templates are skipped); no Bitrix bootstrap, no database. Classes, interfaces,
  traits, enums, methods (static flag, parameter names, variadic, return types incl. @return
  and @method), constants, enum cases, class_alias() and global functions are collected.
  Cached as JSON in tools/.cache/ per kernel dir; the key covers module versions and directory
  mtimes. Use --no-cache after editing kernel files in place.

Output:
  Issues are grouped by skill and file: `LEVEL path:line  message`, then a summary.
  Exit code: 0 no errors, 1 errors found, 2 usage error.
TXT;

const VS_INDEX_FORMAT = 3;
const VS_DESCRIPTION_MAX = 250;
const VS_BUDGET_SKILL = 6144;
const VS_BUDGET_ROUTER = 2560;
const VS_BUDGET_RULE = 6144;
const VS_CHECKLIST_MAX = 6;
/** Prose lines that mention an API as wrong or removed: inline symbol errors there are only WARN. */
const VS_NEGATIVE = '/\b(?:do not use|don\'t use|never use|instead of|no such|not exist|does not exist|doesn\'t exist|is wrong|wrong:|removed|deprecated|hallucinat|there is no|not available|no longer|before it|previously|formerly|renamed|old name)\b|❌|✗/iu';
/** Short names of Composer-vendor classes used by Bitrix code (Symfony Console): never matched to kernel classes. */
const VS_EXTERNAL_SHORT = [
    'command' => true, 'inputinterface' => true, 'outputinterface' => true, 'inputargument' => true,
    'inputoption' => true, 'symfonystyle' => true, 'arrayinput' => true, 'bufferedoutput' => true, 'consoleoutput' => true,
];
/** Backticked tokens that look like skill names but are not skills (b24-dev-mcp tool names). */
const VS_NOT_SKILLS = [
    'bitrix-method-details' => true,
    'bitrix-event-details' => true,
    'bitrix-article-details' => true,
    'bitrix-app-development-doc-details' => true,
];

// ---------------------------------------------------------------------------------------------
// Tokens and kernel parser (their source is hashed into the index cache key)
// ---------------------------------------------------------------------------------------------

final class VsTok
{
    public const IDENT = '/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*$/';

    /**
     * Significant tokens (no whitespace, comments, open tags, inline HTML) and doc comments
     * keyed by the index of the significant token that follows them.
     *
     * @return array{0: list<PhpToken>, 1: array<int, string>}
     */
    public static function sig(string $code, bool $withDocs = false): array
    {
        $sig = [];
        $docs = [];
        $doc = null;
        foreach (PhpToken::tokenize($code) as $tok) {
            $id = $tok->id;
            if ($id === T_WHITESPACE || $id === T_COMMENT || $id === T_OPEN_TAG || $id === T_INLINE_HTML) {
                continue;
            }
            if ($id === T_DOC_COMMENT) {
                if ($withDocs) {
                    $doc = $tok->text;
                }
                continue;
            }
            if ($doc !== null) {
                $docs[count($sig)] = $doc;
                $doc = null;
            }
            $sig[] = $tok;
        }
        return [$sig, $docs];
    }

    public static function isIdent(?PhpToken $t): bool
    {
        if ($t === null) {
            return false;
        }
        return $t->id === T_STRING || ($t->id > 255 && preg_match(self::IDENT, $t->text) === 1);
    }

    public static function isName(?PhpToken $t): bool
    {
        return $t !== null && ($t->id === T_STRING || $t->id === T_NAME_QUALIFIED
            || $t->id === T_NAME_FULLY_QUALIFIED || $t->id === T_NAME_RELATIVE);
    }

    public static function isAmp(int $id): bool
    {
        return $id === 38 || $id === T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG
            || $id === T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG;
    }

    public static function isOpen(int $id): bool
    {
        return $id === 40 || $id === 91 || $id === 123 || $id === T_CURLY_OPEN
            || $id === T_DOLLAR_OPEN_CURLY_BRACES || $id === T_ATTRIBUTE;
    }

    public static function isClose(int $id): bool
    {
        return $id === 41 || $id === 93 || $id === 125;
    }

    /** Index of the bracket closing the one at $i. */
    public static function close(array $t, int $i): int
    {
        $n = count($t);
        $d = 0;
        for ($j = $i; $j < $n; $j++) {
            $id = $t[$j]->id;
            if (self::isOpen($id)) {
                $d++;
            } elseif (self::isClose($id)) {
                $d--;
                if ($d <= 0) {
                    return $j;
                }
            }
        }
        return $n - 1;
    }

    /** Index of the bracket opening the one closed at $i. */
    public static function openBack(array $t, int $i): int
    {
        $d = 0;
        for ($j = $i; $j >= 0; $j--) {
            $id = $t[$j]->id;
            if (self::isClose($id)) {
                $d++;
            } elseif (self::isOpen($id)) {
                $d--;
                if ($d <= 0) {
                    return $j;
                }
            }
        }
        return 0;
    }

    public static function resolve(PhpToken $tok, string $ns, array $uses): string
    {
        if ($tok->id === T_NAME_FULLY_QUALIFIED) {
            return substr($tok->text, 1);
        }
        if ($tok->id === T_NAME_RELATIVE) {
            $rest = substr($tok->text, 10);
            return $ns === '' ? $rest : $ns . '\\' . $rest;
        }
        return self::resolveText($tok->text, $ns, $uses);
    }

    public static function resolveText(string $text, string $ns, array $uses): string
    {
        if ($text !== '' && $text[0] === '\\') {
            return substr($text, 1);
        }
        $p = strpos($text, '\\');
        $first = strtolower($p === false ? $text : substr($text, 0, $p));
        if (isset($uses[$first])) {
            return $uses[$first] . ($p === false ? '' : substr($text, $p));
        }
        return $ns === '' ? $text : $ns . '\\' . $text;
    }

    public static function lastSeg(string $name): string
    {
        $p = strrpos($name, '\\');
        return $p === false ? $name : substr($name, $p + 1);
    }

    /**
     * Parses an import statement starting at T_USE.
     *
     * @return array{0: list<array{0: string, 1: string, 2: string, 3: int}>, 1: int}
     *         items [fqcn, alias, kind(class|function|const), token index] and the index where parsing stopped
     */
    public static function parseUse(array $t, int $i): array
    {
        $n = count($t);
        $j = $i + 1;
        $kind = 'class';
        if (($t[$j]->id ?? 0) === T_FUNCTION) {
            $kind = 'function';
            $j++;
        } elseif (($t[$j]->id ?? 0) === T_CONST) {
            $kind = 'const';
            $j++;
        }
        $items = [];
        while ($j < $n) {
            $tok = $t[$j];
            if (!self::isName($tok)) {
                break;
            }
            $name = ltrim($tok->text, '\\');
            $nameIdx = $j;
            $j++;
            if (($t[$j]->id ?? 0) === T_NS_SEPARATOR && ($t[$j + 1]->id ?? 0) === 123) {
                $j += 2;
                while ($j < $n && $t[$j]->id !== 125) {
                    $ik = $kind;
                    if ($t[$j]->id === T_FUNCTION) {
                        $ik = 'function';
                        $j++;
                    } elseif ($t[$j]->id === T_CONST) {
                        $ik = 'const';
                        $j++;
                    }
                    if (!self::isName($t[$j] ?? null)) {
                        $j++;
                        continue;
                    }
                    $sub = ltrim($t[$j]->text, '\\');
                    $subIdx = $j;
                    $j++;
                    $alias = self::lastSeg($sub);
                    if (($t[$j]->id ?? 0) === T_AS && isset($t[$j + 1])) {
                        $alias = $t[$j + 1]->text;
                        $j += 2;
                    }
                    $items[] = [$name . '\\' . $sub, $alias, $ik, $subIdx];
                    if (($t[$j]->id ?? 0) === 44) {
                        $j++;
                    }
                }
                $j++;
            } else {
                $alias = self::lastSeg($name);
                if (($t[$j]->id ?? 0) === T_AS && isset($t[$j + 1])) {
                    $alias = $t[$j + 1]->text;
                    $j += 2;
                }
                $items[] = [$name, $alias, $kind, $nameIdx];
            }
            if (($t[$j]->id ?? 0) === 44) {
                $j++;
                continue;
            }
            break;
        }
        return [$items, $j];
    }
}

final class VsKernelParser
{
    public const SCALARS = [
        'null' => 1, 'void' => 1, 'mixed' => 1, 'array' => 1, 'string' => 1, 'int' => 1, 'integer' => 1,
        'bool' => 1, 'boolean' => 1, 'float' => 1, 'double' => 1, 'object' => 1, 'callable' => 1,
        'iterable' => 1, 'false' => 1, 'true' => 1, 'resource' => 1, 'never' => 1, 'numeric' => 1,
        'scalar' => 1, 'list' => 1, 'number' => 1, 'real' => 1, 'callback' => 1, 'class-string' => 1,
        'non-empty-string' => 1, 'positive-int' => 1, 'array-key' => 1,
    ];

    private const MODIFIERS = [
        T_PUBLIC => 1, T_PROTECTED => 1, T_PRIVATE => 1, T_STATIC => 1, T_ABSTRACT => 1,
        T_FINAL => 1, T_READONLY => 1, T_VAR => 1,
    ];

    public static function emptyPart(): array
    {
        return ['c' => [], 'f' => [], 'n' => [], 'a' => []];
    }

    /**
     * Class record: n name, k kind (c|i|t|e), x abstract, p parents, i interfaces, t traits,
     * m methods [lc => [Name, static 0|1|2(unknown), params|null, variadic, returns|null, virtual]],
     * c constants [NAME => 1], f file:line, eo generated ORM class (EO_*), dc ORM $dataClass,
     * fm UPPER_SNAKE string literals of a *Table class without underscores (ORM field names).
     */
    public static function newRec(string $fq, string $kind, string $where): array
    {
        return ['n' => $fq, 'k' => $kind, 'p' => [], 'i' => [], 't' => [], 'm' => [], 'c' => [], 'f' => $where];
    }

    public static function mergeClass(array &$classes, string $key, array $rec): void
    {
        if (!isset($classes[$key])) {
            $classes[$key] = $rec;
            return;
        }
        $c = &$classes[$key];
        foreach (['p', 'i', 't'] as $f) {
            if ($rec[$f] !== []) {
                $c[$f] = array_values(array_unique(array_merge($c[$f], $rec[$f])));
            }
        }
        foreach ($rec['m'] as $lc => $m) {
            if (!isset($c['m'][$lc])) {
                $c['m'][$lc] = $m;
                continue;
            }
            $o = $c['m'][$lc];
            if ($o[5] && !$m[5]) {
                $c['m'][$lc] = $m;
                continue;
            }
            if ($m[5] && !$o[5]) {
                continue;
            }
            if ($o[1] !== $m[1]) {
                $o[1] = 2;
            }
            $o[2] = ($o[2] === null || $m[2] === null) ? null : array_values(array_unique(array_merge($o[2], $m[2])));
            $o[3] = max($o[3], $m[3]);
            $o[4] = ($o[4] === null || $m[4] === null) ? null : array_values(array_unique(array_merge($o[4], $m[4])));
            $c['m'][$lc] = $o;
        }
        $c['c'] += $rec['c'];
        if (isset($rec['fm'])) {
            $c['fm'] = ($c['fm'] ?? []) + $rec['fm'];
        }
        if (isset($rec['dc']) && !isset($c['dc'])) {
            $c['dc'] = $rec['dc'];
        }
        if (empty($rec['x'])) {
            unset($c['x']);
        }
        if (!empty($rec['eo'])) {
            $c['eo'] = 1;
        }
        unset($c);
    }

    public static function parseFile(string $path, string $rel, array &$out): void
    {
        $code = @file_get_contents($path);
        if ($code === false || $code === '') {
            return;
        }
        try {
            self::parse($code, $rel, $out);
        } catch (Throwable) {
            // unparseable file: skip
        }
    }

    public static function parse(string $code, string $file, array &$out): void
    {
        [$t, $docs] = VsTok::sig($code, true);
        $n = count($t);
        $ns = '';
        $uses = [];
        $depth = 0;
        $nsDepth = -1;
        $stack = [];
        $pending = null;
        $recs = [];
        $mapEnd = -1;
        $mapKey = null;
        for ($i = 0; $i < $n; $i++) {
            $tok = $t[$i];
            $id = $tok->id;
            if ($id === 123 || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
                $depth++;
                if ($pending !== null) {
                    $stack[] = [$depth, $pending[0], $pending[1]];
                    $pending = null;
                }
                continue;
            }
            if ($id === 125) {
                if ($stack !== [] && $stack[count($stack) - 1][0] === $depth) {
                    array_pop($stack);
                }
                if ($depth === $nsDepth) {
                    $ns = '';
                    $uses = [];
                    $nsDepth = -1;
                }
                $depth--;
                continue;
            }
            if ($id < 256 && $id !== 0) {
                continue;
            }
            $top = $stack === [] ? null : $stack[count($stack) - 1];
            $inBody = $top !== null && $top[0] === $depth;
            $key = $inBody ? $top[1] : null;

            if ($id === T_CONSTANT_ENCAPSED_STRING) {
                // UPPER_SNAKE literals in *Table classes and in any getMap(): ORM field names for EO accessor checks
                $fmKey = $i < $mapEnd ? $mapKey : (($top !== null && $top[1] !== null && str_ends_with($top[1], 'table')) ? $top[1] : null);
                if ($fmKey !== null && preg_match('/^[\'"]([A-Z][A-Z0-9_]*)[\'"]$/', $tok->text, $fm) === 1) {
                    $recs[$fmKey]['fm'][str_replace('_', '', $fm[1])] = 1;
                }
                continue;
            }

            if ($id === T_VARIABLE) {
                if ($inBody && $key !== null && $tok->text === '$dataClass' && ($t[$i + 1]->id ?? 0) === 61) {
                    $v = $t[$i + 2] ?? null;
                    if ($v !== null && $v->id === T_CONSTANT_ENCAPSED_STRING) {
                        $recs[$key]['dc'] = ltrim(str_replace('\\\\', '\\', substr($v->text, 1, -1)), '\\');
                    } elseif (VsTok::isName($v) && ($t[$i + 3]->id ?? 0) === T_DOUBLE_COLON && ($t[$i + 4]->id ?? 0) === T_CLASS) {
                        $recs[$key]['dc'] = VsTok::resolve($v, $ns, $uses);
                    }
                }
                continue;
            }

            if ($id === T_NAMESPACE) {
                $nx = $t[$i + 1] ?? null;
                if ($nx !== null && ($nx->id === T_STRING || $nx->id === T_NAME_QUALIFIED)) {
                    $ns = $nx->text;
                    $uses = [];
                    $parts = explode('\\', strtolower($ns));
                    $acc = '';
                    foreach ($parts as $p) {
                        $acc = $acc === '' ? $p : $acc . '\\' . $p;
                        $out['n'][$acc] = 1;
                    }
                    if (($t[$i + 2]->id ?? 0) === 123) {
                        $nsDepth = $depth + 1;
                    }
                    $i++;
                } elseif ($nx !== null && $nx->id === 123) {
                    $ns = '';
                    $uses = [];
                    $nsDepth = $depth + 1;
                }
                continue;
            }

            if ($id === T_USE) {
                if ($inBody) {
                    $i = self::traitUse($t, $i, $key, $ns, $uses, $recs);
                } elseif (($t[$i - 1]->id ?? 0) !== 41) {
                    [$items, $end] = VsTok::parseUse($t, $i);
                    foreach ($items as [$fq, $alias, $kind]) {
                        if ($kind === 'class') {
                            $uses[strtolower($alias)] = $fq;
                        }
                    }
                    $i = max($i, $end - 1);
                }
                continue;
            }

            if ($id === T_CLASS || $id === T_INTERFACE || $id === T_TRAIT || $id === T_ENUM) {
                if (($t[$i - 1]->id ?? 0) === T_DOUBLE_COLON) {
                    continue;
                }
                $nameTok = $t[$i + 1] ?? null;
                if (!VsTok::isIdent($nameTok) || $nameTok->id === T_EXTENDS || $nameTok->id === T_IMPLEMENTS) {
                    if ($id === T_CLASS) {
                        $pending = [null, 'c'];
                    }
                    continue;
                }
                $fq = ($ns === '' ? '' : $ns . '\\') . $nameTok->text;
                $ck = strtolower($fq);
                $kind = $id === T_CLASS ? 'c' : ($id === T_INTERFACE ? 'i' : ($id === T_TRAIT ? 't' : 'e'));
                $start = self::declStart($t, $i);
                if (!isset($recs[$ck])) {
                    $recs[$ck] = self::newRec($fq, $kind, $file . ':' . $tok->line);
                }
                for ($k = $start; $k < $i; $k++) {
                    if ($t[$k]->id === T_ABSTRACT) {
                        $recs[$ck]['x'] = 1;
                    }
                }
                $mode = null;
                $backed = false;
                $j = $i + 2;
                for (; $j < $n; $j++) {
                    $jt = $t[$j];
                    $jid = $jt->id;
                    if ($jid === 123 || $jid === 59) {
                        break;
                    }
                    if ($jid === T_EXTENDS) {
                        $mode = $kind === 'i' ? 'i' : 'p';
                    } elseif ($jid === T_IMPLEMENTS) {
                        $mode = 'i';
                    } elseif ($jid === 58) {
                        $backed = true;
                        $mode = null;
                    } elseif ($mode !== null && VsTok::isName($jt)) {
                        $recs[$ck][$mode][] = VsTok::resolve($jt, $ns, $uses);
                    }
                }
                $doc = $docs[$start] ?? null;
                if ($doc !== null && str_contains($doc, '@method')) {
                    self::docMethods($doc, $ns, $uses, $recs[$ck]);
                }
                if (str_starts_with(strtolower($nameTok->text), 'eo_')) {
                    $recs[$ck]['eo'] = 1;
                }
                if ($kind === 'e') {
                    $recs[$ck]['i'][] = $backed ? 'BackedEnum' : 'UnitEnum';
                    $recs[$ck]['m']['cases'] = ['cases', 1, [], 0, [], 0];
                    if ($backed) {
                        $recs[$ck]['m']['from'] = ['from', 1, ['value'], 0, ['@static'], 0];
                        $recs[$ck]['m']['tryfrom'] = ['tryFrom', 1, ['value'], 0, ['@static'], 0];
                    }
                }
                $pending = [$ck, $kind];
                $i = $j - 1;
                continue;
            }

            if ($id === T_FUNCTION) {
                $j = $i + 1;
                if (isset($t[$j]) && VsTok::isAmp($t[$j]->id)) {
                    $j++;
                }
                $nameTok = $t[$j] ?? null;
                if (!VsTok::isIdent($nameTok) || ($t[$j + 1]->id ?? 0) !== 40) {
                    continue;
                }
                $open = $j + 1;
                $close = VsTok::close($t, $open);
                [$params, $variadic] = self::params($t, $open, $close);
                if ($inBody) {
                    if ($key !== null) {
                        $start = self::declStart($t, $i);
                        $static = 0;
                        for ($k = $start; $k < $i; $k++) {
                            if ($t[$k]->id === T_STATIC) {
                                $static = 1;
                            }
                        }
                        $ret = self::returnType($t, $close, $ns, $uses);
                        if ($ret === null && isset($docs[$start])) {
                            $ret = self::docReturn($docs[$start], $ns, $uses);
                        }
                        $recs[$key]['m'][strtolower($nameTok->text)] = [$nameTok->text, $static, $params, $variadic ? 1 : 0, $ret, 0];
                        if (strcasecmp($nameTok->text, 'getMap') === 0) {
                            for ($b = $close + 1; $b < $n && $t[$b]->id !== 123 && $t[$b]->id !== 59; $b++);
                            if (($t[$b]->id ?? 0) === 123) {
                                $mapEnd = VsTok::close($t, $b);
                                $mapKey = $key;
                            }
                        }
                    }
                } else {
                    $out['f'][strtolower(($ns === '' ? '' : $ns . '\\') . $nameTok->text)] = 1;
                }
                $i = $close;
                continue;
            }

            if ($id === T_CONST && $inBody) {
                $k = $i + 1;
                $d = 0;
                for (; $k < $n; $k++) {
                    $kid = $t[$k]->id;
                    if ($kid === 40 || $kid === 91 || $kid === 123) {
                        $d++;
                    } elseif ($kid === 41 || $kid === 93 || $kid === 125) {
                        $d--;
                    } elseif ($d === 0 && $kid === 59) {
                        break;
                    } elseif ($d === 0 && $key !== null && ($t[$k + 1]->id ?? 0) === 61 && VsTok::isIdent($t[$k])) {
                        $recs[$key]['c'][$t[$k]->text] = 1;
                    }
                }
                $i = $k;
                continue;
            }

            if ($id === T_CASE && $inBody && $key !== null && $top[2] === 'e') {
                if (VsTok::isIdent($t[$i + 1] ?? null)) {
                    $recs[$key]['c'][$t[$i + 1]->text] = 1;
                }
                continue;
            }

            if (($id === T_STRING || $id === T_NAME_FULLY_QUALIFIED)
                && strcasecmp(ltrim($tok->text, '\\'), 'class_alias') === 0
                && ($t[$i + 1]->id ?? 0) === 40
            ) {
                $prev = $t[$i - 1]->id ?? 0;
                if ($prev === T_OBJECT_OPERATOR || $prev === T_DOUBLE_COLON || $prev === T_FUNCTION || $prev === T_NULLSAFE_OBJECT_OPERATOR) {
                    continue;
                }
                [$a1, $k] = self::aliasArg($t, $i + 2, $ns, $uses);
                if ($a1 !== null && ($t[$k]->id ?? 0) === 44) {
                    [$a2, $k2] = self::aliasArg($t, $k + 1, $ns, $uses);
                    $after = $t[$k2]->id ?? 0;
                    if ($a2 !== null && ($after === 41 || $after === 44)) {
                        self::addAlias($out, $a2, $a1);
                    }
                }
                continue;
            }

            // Loader::registerClassAliases(['Old\Name' => 'New\Name', ...]) (main/include/compatibility.php)
            if ($id === T_STRING && strcasecmp($tok->text, 'registerClassAliases') === 0
                && ($t[$i - 1]->id ?? 0) === T_DOUBLE_COLON && ($t[$i + 1]->id ?? 0) === 40
            ) {
                $open = $i + 2;
                if (($t[$open]->id ?? 0) === T_ARRAY) {
                    $open++;
                }
                $end = VsTok::close($t, $open);
                for ($q = $open + 1; $q < $end; $q++) {
                    [$alias, $qa] = self::aliasArg($t, $q, $ns, $uses, false);
                    if ($alias === null || ($t[$qa]->id ?? 0) !== T_DOUBLE_ARROW) {
                        continue;
                    }
                    [$target, $qb] = self::aliasArg($t, $qa + 1, $ns, $uses, false);
                    if ($target !== null) {
                        self::addAlias($out, $alias, $target);
                        $q = $qb - 1;
                    }
                }
                $i = $end;
            }
        }
        foreach ($recs as $ck => $rec) {
            self::mergeClass($out['c'], (string)$ck, $rec);
        }
    }

    private static function addAlias(array &$out, string $alias, string $target): void
    {
        $alias = ltrim($alias, '\\');
        $out['a'][strtolower($alias)] = ltrim($target, '\\');
        $parts = explode('\\', strtolower($alias));
        array_pop($parts);
        $acc = '';
        foreach ($parts as $p) {
            $acc = $acc === '' ? $p : $acc . '\\' . $p;
            $out['n'][$acc] = 1;
        }
    }

    private static function aliasArg(array $t, int $k, string $ns, array $uses, bool $strict = true): array
    {
        $tok = $t[$k] ?? null;
        if ($tok === null) {
            return [null, $k];
        }
        if ($tok->id === T_CONSTANT_ENCAPSED_STRING) {
            $after = $t[$k + 1]->id ?? 0;
            if ($strict && $after !== 44 && $after !== 41) {
                return [null, $k];
            }
            $s = str_replace('\\\\', '\\', substr($tok->text, 1, -1));
            return [ltrim($s, '\\'), $k + 1];
        }
        if (VsTok::isName($tok) && ($t[$k + 1]->id ?? 0) === T_DOUBLE_COLON && ($t[$k + 2]->id ?? 0) === T_CLASS) {
            return [VsTok::resolve($tok, $ns, $uses), $k + 3];
        }
        return [null, $k];
    }

    private static function declStart(array $t, int $i): int
    {
        $k = $i - 1;
        while ($k >= 0) {
            $kid = $t[$k]->id;
            if (isset(self::MODIFIERS[$kid])) {
                $k--;
                continue;
            }
            if ($kid === 93) {
                $o = VsTok::openBack($t, $k);
                if ($t[$o]->id === T_ATTRIBUTE) {
                    $k = $o - 1;
                    continue;
                }
            }
            break;
        }
        return $k + 1;
    }

    /** @return array{0: list<string>, 1: bool} */
    public static function params(array $t, int $open, int $close): array
    {
        $params = [];
        $variadic = false;
        for ($k = $open + 1; $k < $close; $k++) {
            if ($t[$k]->id !== T_VARIABLE) {
                continue;
            }
            $params[] = substr($t[$k]->text, 1);
            $b = $k - 1;
            while ($b > $open && VsTok::isAmp($t[$b]->id)) {
                $b--;
            }
            if ($t[$b]->id === T_ELLIPSIS) {
                $variadic = true;
            }
        }
        return [$params, $variadic];
    }

    /** Native return type after the parameter list: list of class names ('@static' for self/static), [] for scalars, null if absent. */
    private static function returnType(array $t, int $close, string $ns, array $uses): ?array
    {
        $k = $close + 1;
        if (($t[$k]->id ?? 0) !== 58) {
            return null;
        }
        $res = [];
        for ($k++; isset($t[$k]); $k++) {
            $tt = $t[$k];
            $kid = $tt->id;
            if ($kid === 123 || $kid === 59 || $kid === T_DOUBLE_ARROW) {
                break;
            }
            if ($kid === T_STATIC) {
                $res[] = '@static';
            } elseif (VsTok::isName($tt)) {
                $lc = strtolower($tt->text);
                if ($lc === 'self') {
                    $res[] = '@static';
                } elseif (!isset(self::SCALARS[$lc])) {
                    $res[] = VsTok::resolve($tt, $ns, $uses);
                }
            }
        }
        return $res;
    }

    private static function docReturn(string $doc, string $ns, array $uses): ?array
    {
        if (!preg_match('/@return\s+([^\s*]+(?:\s*\|\s*[^\s*]+)*)/', $doc, $m)) {
            return null;
        }
        return self::docType($m[1], $ns, $uses);
    }

    public static function docType(string $s, string $ns, array $uses): ?array
    {
        $res = [];
        foreach (preg_split('/\s*\|\s*/', trim($s)) ?: [] as $p) {
            $p = ltrim($p, '?');
            if ($p === '' || str_ends_with($p, '[]')) {
                continue;
            }
            $p = (string)preg_replace('/[<{(].*$/s', '', $p);
            $lc = strtolower(ltrim($p, '\\'));
            if ($lc === 'static' || $lc === 'self' || $lc === '$this') {
                $res[] = '@static';
                continue;
            }
            if ($lc === '' || isset(self::SCALARS[$lc])) {
                continue;
            }
            if (preg_match('/^\\\\?[A-Za-z_][A-Za-z0-9_\\\\]*$/', $p) !== 1) {
                return null;
            }
            $res[] = VsTok::resolveText($p, $ns, $uses);
        }
        return $res;
    }

    private static function docMethods(string $doc, string $ns, array $uses, array &$rec): void
    {
        preg_match_all('/@method\s+(static\s+)?(?:([^\s(]+)\s+)?([A-Za-z_]\w*)\s*\(([^)\n]*)\)/', $doc, $mm, PREG_SET_ORDER);
        foreach ($mm as $m) {
            $lc = strtolower($m[3]);
            if (isset($rec['m'][$lc])) {
                continue;
            }
            preg_match_all('/\$(\w+)/', $m[4], $pp);
            $ret = $m[2] !== '' ? self::docType($m[2], $ns, $uses) : null;
            $rec['m'][$lc] = [$m[3], $m[1] !== '' ? 1 : 0, $pp[1], str_contains($m[4], '...') ? 1 : 0, $ret, 1];
        }
    }

    private static function traitUse(array $t, int $i, ?string $key, string $ns, array $uses, array &$recs): int
    {
        $n = count($t);
        for ($k = $i + 1; $k < $n; $k++) {
            $kt = $t[$k];
            if ($kt->id === 59) {
                return $k;
            }
            if ($kt->id === 123) {
                $end = VsTok::close($t, $k);
                for ($q = $k + 1; $q < $end; $q++) {
                    if ($t[$q]->id !== T_AS || $key === null) {
                        continue;
                    }
                    $s = $q + 1;
                    while ($s < $end && $t[$s]->id !== 59) {
                        $s++;
                    }
                    $last = $t[$s - 1];
                    if ($s - 1 > $q && VsTok::isIdent($last) && !in_array($last->id, [T_PUBLIC, T_PROTECTED, T_PRIVATE], true)) {
                        $recs[$key]['m'][strtolower($last->text)] ??= [$last->text, 2, null, 0, null, 0];
                    }
                }
                return $end;
            }
            if ($key !== null && VsTok::isName($kt)) {
                $recs[$key]['t'][] = VsTok::resolve($kt, $ns, $uses);
            }
        }
        return $n - 1;
    }
}

// ---------------------------------------------------------------------------------------------
// Kernel index
// ---------------------------------------------------------------------------------------------

final class VsIndex
{
    public array $classes = [];
    public array $functions = [];
    public array $namespaces = [];
    public array $aliases = [];
    /** @var list<string> lower-case namespace roots with trailing backslash */
    public array $roots = ['bitrix\\'];
    private ?array $short = null;
    private array $lineageCache = [];
    private array $missing = [];

    public function add(array $part): void
    {
        foreach ($part['c'] as $k => $rec) {
            VsKernelParser::mergeClass($this->classes, (string)$k, $rec);
        }
        $this->functions += $part['f'];
        $this->namespaces += $part['n'];
        $this->aliases += $part['a'];
        $this->short = null;
        $this->lineageCache = [];
        $this->missing = [];
    }

    public function key(string $fq): ?string
    {
        $lc = strtolower(ltrim($fq, '\\'));
        if ($lc === '') {
            return null;
        }
        if (isset($this->classes[$lc])) {
            return $lc;
        }
        for ($hops = 0; isset($this->aliases[$lc]) && $hops < 5; $hops++) {
            $lc = strtolower(ltrim($this->aliases[$lc], '\\'));
            if (isset($this->classes[$lc])) {
                return $lc;
            }
        }
        if (isset($this->missing[$lc])) {
            return null;
        }
        $rec = self::builtin($lc);
        if ($rec !== null) {
            $this->classes[$lc] = $rec;
            return $lc;
        }
        $this->missing[$lc] = true;
        return null;
    }

    public function name(string $key): string
    {
        return $this->classes[$key]['n'] ?? $key;
    }

    public function isInterface(string $key): bool
    {
        return ($this->classes[$key]['k'] ?? '') === 'i';
    }

    public function isNamespace(string $fq): bool
    {
        return isset($this->namespaces[strtolower(trim($fq, '\\'))]);
    }

    /**
     * Names checked against the index: Bitrix\<Module>\… of a module present in the index (so
     * `Bitrix\Mymodule\…` examples are user code), other roots (Sprint\Migration\…), global C* classes.
     * Runtime-generated classes (iblock Element*Table / Section{ID}Table, ORM EO_*) are never enforced.
     */
    public function enforced(string $fq): bool
    {
        $fq = ltrim($fq, '\\');
        $lc = strtolower($fq);
        if (str_starts_with($lc, 'bitrix\\iblock\\elements\\')
            || preg_match('/^bitrix\\\\iblock\\\\section\d+table$/', $lc) === 1
            || str_starts_with(strtolower(VsTok::lastSeg($fq)), 'eo_')) {
            return false;
        }
        foreach ($this->roots as $root) {
            if (str_starts_with($lc, $root)) {
                if ($root !== 'bitrix\\') {
                    return true;
                }
                $seg = explode('\\', $lc)[1] ?? '';
                return $seg !== '' && isset($this->namespaces['bitrix\\' . $seg]);
            }
        }
        return !str_contains($fq, '\\') && preg_match('/^C[A-Z][A-Za-z0-9_]+$/', $fq) === 1;
    }

    /** Kernel classes whose FQCN ends with $name (short or partially qualified), enforced roots and global C* only. */
    public function candidates(string $name): array
    {
        $this->short ??= $this->buildShort();
        $lc = strtolower(trim($name, '\\'));
        $res = [];
        foreach ($this->short[strtolower(VsTok::lastSeg($lc))] ?? [] as $k) {
            if (!$this->enforced((string)($this->classes[$k]['n'] ?? $k))) {
                continue;
            }
            if (str_contains($lc, '\\') && !str_ends_with($k, '\\' . $lc) && $k !== $lc) {
                continue;
            }
            $res[] = $k;
        }
        return $res;
    }

    /** All classes (any namespace) with this short name; a qualified $name (Site\Type) must match as a suffix. */
    public function sameShort(string $name): array
    {
        $this->short ??= $this->buildShort();
        $lc = strtolower(trim($name, '\\'));
        $list = $this->short[strtolower(VsTok::lastSeg($lc))] ?? [];
        if (!str_contains($lc, '\\')) {
            return $list;
        }
        return array_values(array_filter($list, fn ($k) => $k === $lc || str_ends_with($k, '\\' . $lc)));
    }

    private function buildShort(): array
    {
        $map = [];
        foreach ($this->classes as $k => $rec) {
            $k = (string)$k;
            if (str_starts_with($k, 'php:')) {
                continue;
            }
            $map[VsTok::lastSeg($k)][] = $k;
        }
        foreach ($this->aliases as $alias => $target) {
            $tk = strtolower(ltrim((string)$target, '\\'));
            if (isset($this->classes[$tk])) {
                $map[VsTok::lastSeg((string)$alias)][] = $tk;
            }
        }
        foreach ($map as $s => $list) {
            $map[$s] = array_values(array_unique($list));
        }
        return $map;
    }

    /** @return array{0: list<string>, 1: bool} class itself, traits, parents, interfaces; complete = all ancestors known */
    public function lineage(string $key): array
    {
        if (isset($this->lineageCache[$key])) {
            return $this->lineageCache[$key];
        }
        $keys = [];
        $complete = true;
        $queue = [$key];
        $seen = [];
        while ($queue !== []) {
            $k = array_shift($queue);
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            if (!isset($this->classes[$k])) {
                $complete = false;
                continue;
            }
            $keys[] = $k;
            $rec = $this->classes[$k];
            foreach (['t', 'p', 'i'] as $f) {
                foreach ($rec[$f] as $parent) {
                    $pk = $this->key((string)$parent);
                    if ($pk === null) {
                        $complete = false;
                    } else {
                        $queue[] = $pk;
                    }
                }
            }
        }
        return $this->lineageCache[$key] = [$keys, $complete];
    }

    public function isSubclassOf(string $key, string $ancestor): bool
    {
        return in_array($ancestor, $this->lineage($key)[0], true);
    }

    /** @return array{0: ?array, 1: ?string, 2: bool, 3: bool, 4: bool, 5: bool} method, owner, complete, __callStatic, __call, ORM object */
    public function findMethod(string $key, string $lcm): array
    {
        [$keys, $complete] = $this->lineage($key);
        $found = null;
        $owner = null;
        $magicStatic = false;
        $magicCall = false;
        $eo = false;
        foreach ($keys as $k) {
            $rec = $this->classes[$k];
            if ($found === null && isset($rec['m'][$lcm])) {
                $found = $rec['m'][$lcm];
                $owner = $k;
            }
            if (isset($rec['m']['__callstatic'])) {
                $magicStatic = true;
            }
            if (isset($rec['m']['__call'])) {
                $magicCall = true;
            }
            if (!empty($rec['eo'])) {
                $eo = true;
            }
        }
        return [$found, $owner, $complete, $magicStatic, $magicCall, $eo];
    }

    /** @return array{0: bool, 1: bool} found, complete */
    public function findConst(string $key, string $name): array
    {
        [$keys, $complete] = $this->lineage($key);
        foreach ($keys as $k) {
            if (isset($this->classes[$k]['c'][$name])) {
                return [true, $complete];
            }
        }
        return [false, $complete];
    }

    /** @return list<string> method names ('m') or constant names ('c') across the hierarchy */
    public function members(string $key, string $what): array
    {
        $res = [];
        foreach ($this->lineage($key)[0] as $k) {
            foreach ($this->classes[$k][$what] as $lc => $v) {
                $res[] = $what === 'm' ? (string)$v[0] : (string)$lc;
            }
        }
        return array_values(array_unique($res));
    }

    /**
     * ORM object/collection field names (normalised: upper case, no underscores) from its tablet.
     * false: not an ORM object; null: ORM object whose tablet fields are unknown.
     */
    public function ormFields(string $key): array|false|null
    {
        [$keys] = $this->lineage($key);
        $orm = false;
        $dc = null;
        foreach ($keys as $k) {
            if ($k === 'bitrix\\main\\orm\\objectify\\entityobject' || $k === 'bitrix\\main\\orm\\objectify\\collection'
                || !empty($this->classes[$k]['eo'])
            ) {
                $orm = true;
            }
            $dc ??= $this->classes[$k]['dc'] ?? null;
        }
        if (!$orm) {
            return false;
        }
        $tk = $dc === null ? null : $this->key((string)$dc);
        if ($tk === null) {
            return null;
        }
        $fields = [];
        foreach ($this->lineage($tk)[0] as $k) {
            $fields += $this->classes[$k]['fm'] ?? [];
        }
        return $fields === [] ? null : $fields;
    }

    /** getXmlId → [XMLID]; isTitleChanged → [TITLECHANGED, TITLE]; null if not an ORM accessor name. */
    public static function accessorFields(string $name): ?array
    {
        if (preg_match('/^(removeFrom|addTo|remindActual|require|reset|unset|fill|get|set|has|is|remove)([A-Z]\w*)$/', $name, $m) !== 1) {
            return null;
        }
        $base = strtoupper(str_replace('_', '', $m[2]));
        $res = [$base];
        foreach (['FILLED', 'CHANGED', 'LIST', 'COLLECTION'] as $suffix) {
            if (str_ends_with($base, $suffix) && strlen($base) > strlen($suffix)) {
                $res[] = substr($base, 0, -strlen($suffix));
            }
        }
        return $res;
    }

    public static function closest(string $needle, array $names): ?string
    {
        $best = null;
        $bestD = PHP_INT_MAX;
        $nl = strtolower($needle);
        foreach ($names as $name) {
            $d = levenshtein($nl, strtolower($name));
            if ($d < $bestD) {
                $bestD = $d;
                $best = $name;
            }
        }
        return $best !== null && $bestD > 0 && $bestD <= max(2, intdiv(strlen($needle), 3)) ? $best : null;
    }

    public function suggestClass(string $fq): string
    {
        $fq = ltrim($fq, '\\');
        $p = strrpos($fq, '\\');
        if ($p !== false) {
            // same namespace first: a close name or one that extends the missing name (Html → HtmlContent)
            $prefix = strtolower(substr($fq, 0, $p + 1));
            $short = VsTok::lastSeg($fq);
            $names = [];
            foreach ($this->classes as $k => $rec) {
                $k = (string)$k;
                if (str_starts_with($k, $prefix) && !str_contains(substr($k, strlen($prefix)), '\\')) {
                    $names[] = VsTok::lastSeg((string)$rec['n']);
                }
            }
            $best = self::closest($short, $names);
            if ($best === null) {
                foreach ($names as $n) {
                    if (stripos($n, $short) === 0 || stripos($short, $n) === 0) {
                        $best = $n;
                        break;
                    }
                }
            }
            if ($best !== null) {
                return '; did you mean `' . substr($fq, 0, $p + 1) . $best . '`?';
            }
        }
        $same = array_filter($this->sameShort(VsTok::lastSeg($fq)), fn ($k) => $k !== strtolower($fq));
        if ($same !== []) {
            $names = array_map(fn ($k) => '`' . $this->name($k) . '`', array_slice(array_values($same), 0, 3));
            return '; same short name: ' . implode(', ', $names);
        }
        return '';
    }

    private static function builtin(string $lc): ?array
    {
        if (!(class_exists($lc, false) || interface_exists($lc, false) || trait_exists($lc, false) || enum_exists($lc, false))) {
            return null;
        }
        $r = new ReflectionClass($lc);
        if (!$r->isInternal()) {
            return null;
        }
        $rec = VsKernelParser::newRec($r->getName(), $r->isInterface() ? 'i' : ($r->isTrait() ? 't' : ($r->isEnum() ? 'e' : 'c')), 'php');
        $parent = $r->getParentClass();
        if ($parent !== false) {
            $rec['p'][] = $parent->getName();
        }
        $rec['i'] = $r->getInterfaceNames();
        foreach ($r->getMethods() as $m) {
            $params = [];
            $variadic = 0;
            foreach ($m->getParameters() as $p) {
                $params[] = $p->getName();
                if ($p->isVariadic()) {
                    $variadic = 1;
                }
            }
            $rt = $m->getReturnType() ?? $m->getTentativeReturnType();
            $ret = null;
            if ($rt instanceof ReflectionNamedType) {
                $tn = $rt->getName();
                $ret = ($tn === 'static' || $tn === 'self') ? ['@static'] : ($rt->isBuiltin() ? [] : [$tn]);
            }
            $rec['m'][strtolower($m->getName())] = [$m->getName(), $m->isStatic() ? 1 : 0, $params, $variadic, $ret, 0];
        }
        foreach (array_keys($r->getConstants()) as $c) {
            $rec['c'][$c] = 1;
        }
        return $rec;
    }
}

final class VsIndexBuilder
{
    public function __construct(
        private string $cacheDir,
        private int $jobs,
        private bool $noCache,
        private string $tmp,
    ) {
    }

    public static function parserHash(): string
    {
        $src = '';
        foreach ([VsTok::class, VsKernelParser::class] as $class) {
            $r = new ReflectionClass($class);
            $lines = file((string)$r->getFileName()) ?: [];
            $src .= implode('', array_slice($lines, $r->getStartLine() - 1, $r->getEndLine() - $r->getStartLine() + 1));
        }
        return sha1($src . VS_INDEX_FORMAT . PHP_VERSION);
    }

    /** @return array{0: array, 1: bool, 2: int} part, from cache, file count */
    public function load(string $dir, callable $log): array
    {
        $real = rtrim((string)(realpath($dir) ?: $dir), '/');
        $stamp = $this->stamp($real);
        $prefix = 'index-php' . PHP_MAJOR_VERSION . PHP_MINOR_VERSION . '-' . substr(sha1($real), 0, 10);
        $file = $this->cacheDir . '/' . $prefix . '-' . substr($stamp, 0, 12) . '.json';
        if (!$this->noCache && is_file($file)) {
            $data = json_decode((string)file_get_contents($file), true);
            if (is_array($data) && ($data['key'] ?? null) === $stamp && isset($data['index'])) {
                return [$data['index'], true, (int)($data['files'] ?? 0)];
            }
        }
        $t0 = microtime(true);
        $files = self::listFiles($real);
        $log(sprintf('Indexing %s: %d PHP files, %d jobs...', $real, count($files), $this->jobs));
        $part = $this->parseAll($real, $files);
        $log(sprintf('  %d classes, %d functions in %.1fs', count($part['c']), count($part['f']), microtime(true) - $t0));
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0777, true);
        }
        foreach (glob($this->cacheDir . '/' . $prefix . '-*.json') ?: [] as $old) {
            @unlink($old);
        }
        $json = json_encode(
            ['key' => $stamp, 'dir' => $real, 'files' => count($files), 'built' => date('c'), 'index' => $part],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
        );
        if ($json !== false) {
            @file_put_contents($file, $json);
        }
        return [$part, false, count($files)];
    }

    private function stamp(string $real): string
    {
        $parts = [self::parserHash(), $real, (string)@filemtime($real)];
        $mods = is_dir($real . '/install') ? [$real] : (glob($real . '/*', GLOB_ONLYDIR) ?: []);
        foreach ($mods as $m) {
            $parts[] = basename($m) . '|' . @filemtime($m) . '|' . @filemtime($m . '/lib') . '|' . @filemtime($m . '/classes/general')
                . '|' . @filemtime($m . '/meta');
            foreach ([$m . '/install/version.php', $m . '/classes/general/version.php'] as $v) {
                if (is_file($v)) {
                    $parts[] = (string)md5_file($v);
                }
            }
        }
        return sha1(implode("\n", $parts));
    }

    /** @return list<string> */
    public static function listFiles(string $real): array
    {
        $skipNames = ['lang' => 1, 'node_modules' => 1, '.git' => 1, '.svn' => 1, '.idea' => 1, '.cache' => 1];
        $len = strlen($real) + 1;
        $filter = static function (SplFileInfo $f, string $path) use ($skipNames, $len): bool {
            if ($f->isDir()) {
                if (isset($skipNames[$f->getFilename()])) {
                    return false;
                }
                $rel = substr($path, $len);
                return preg_match('~(?:^|/)install/(?:js|css|images|fonts|themes|panel|templates)$~', $rel) !== 1
                    && preg_match('~(?:^|/)install/components/[^/]+/[^/]+/templates$~', $rel) !== 1;
            }
            return str_ends_with($path, '.php');
        };
        $it = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS),
                $filter,
            ),
        );
        $files = [];
        foreach ($it as $path => $info) {
            $files[] = (string)$path;
        }
        sort($files);
        return $files;
    }

    private function parseAll(string $real, array $files): array
    {
        $len = strlen($real) + 1;
        if ($this->jobs <= 1 || count($files) < 400) {
            $part = VsKernelParser::emptyPart();
            foreach ($files as $f) {
                VsKernelParser::parseFile($f, substr($f, $len), $part);
            }
            return $part;
        }
        $sizes = [];
        foreach ($files as $f) {
            $sizes[$f] = (int)@filesize($f);
        }
        arsort($sizes);
        $buckets = array_fill(0, $this->jobs, []);
        $load = array_fill(0, $this->jobs, 0);
        foreach ($sizes as $f => $s) {
            $b = (int)array_search(min($load), $load, true);
            $buckets[$b][] = $f;
            $load[$b] += $s + 2000;
        }
        $cmds = [];
        $outs = [];
        foreach ($buckets as $b => $list) {
            if ($list === []) {
                continue;
            }
            $listFile = $this->tmp . '/list-' . $b . '.txt';
            $outs[$b] = $this->tmp . '/part-' . $b . '.ser';
            file_put_contents($listFile, implode("\n", $list));
            $cmds[$b] = [PHP_BINARY, '-d', 'memory_limit=-1', '-d', 'display_errors=stderr', __FILE__,
                '--index-worker=' . $listFile, '--index-out=' . $outs[$b], '--index-root=' . $real];
        }
        $results = VsProc::run($cmds, $this->jobs, $this->tmp);
        $part = VsKernelParser::emptyPart();
        foreach ($outs as $b => $out) {
            $data = is_file($out) ? unserialize((string)file_get_contents($out)) : false;
            if (!is_array($data)) {
                throw new RuntimeException('Index worker failed: ' . trim($results[$b][2] ?? ''));
            }
            foreach ($data['c'] as $k => $rec) {
                VsKernelParser::mergeClass($part['c'], (string)$k, $rec);
            }
            $part['f'] += $data['f'];
            $part['n'] += $data['n'];
            $part['a'] += $data['a'];
            @unlink($out);
        }
        return $part;
    }

    public static function worker(string $listFile, string $outFile, string $root): int
    {
        $len = strlen($root) + 1;
        $part = VsKernelParser::emptyPart();
        foreach (file($listFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $f) {
            VsKernelParser::parseFile($f, substr($f, $len), $part);
        }
        file_put_contents($outFile, serialize($part));
        return 0;
    }
}

final class VsProc
{
    /**
     * Runs commands with at most $par in parallel.
     *
     * @param array<int|string, list<string>> $cmds
     * @return array<int|string, array{0: int, 1: string, 2: string}> exit code, stdout, stderr
     */
    public static function run(array $cmds, int $par, string $tmp): array
    {
        $results = [];
        $running = [];
        $queue = array_keys($cmds);
        $seq = 0;
        while ($queue !== [] || $running !== []) {
            while ($queue !== [] && count($running) < max(1, $par)) {
                $i = array_shift($queue);
                $seq++;
                $out = $tmp . '/proc-' . $seq . '.out';
                $err = $tmp . '/proc-' . $seq . '.err';
                $p = proc_open($cmds[$i], [0 => ['pipe', 'r'], 1 => ['file', $out, 'w'], 2 => ['file', $err, 'w']], $pipes);
                if (!is_resource($p)) {
                    $results[$i] = [-1, '', 'proc_open failed'];
                    continue;
                }
                fclose($pipes[0]);
                $running[$i] = [$p, $out, $err];
            }
            foreach ($running as $i => [$p, $out, $err]) {
                $st = proc_get_status($p);
                if ($st['running']) {
                    continue;
                }
                proc_close($p);
                $results[$i] = [(int)$st['exitcode'], (string)@file_get_contents($out), (string)@file_get_contents($err)];
                @unlink($out);
                @unlink($err);
                unset($running[$i]);
            }
            if ($running !== []) {
                usleep(3000);
            }
        }
        return $results;
    }
}

// ---------------------------------------------------------------------------------------------
// Markdown and PHP block preparation
// ---------------------------------------------------------------------------------------------

final class VsMd
{
    /**
     * @return array{blocks: list<array{lang: string, line: int, end: int, code: string}>,
     *               spans: list<array{0: int, 1: string}>, text: array<int, string>, unclosed: ?int}
     */
    public static function parse(string $content): array
    {
        $lines = preg_split('/\r?\n/', $content) ?: [];
        $blocks = [];
        $spans = [];
        $text = [];
        $in = null;
        foreach ($lines as $idx => $line) {
            $no = $idx + 1;
            if ($in === null) {
                if (preg_match('/^(\s*)(`{3,}|~{3,})\s*([^\s`{]*)/', $line, $m)) {
                    $in = ['indent' => strlen($m[1]), 'fence' => $m[2], 'lang' => strtolower($m[3]), 'line' => $no + 1, 'start' => $no, 'code' => []];
                    continue;
                }
                $text[$no] = $line;
                if (str_contains($line, '`') && preg_match_all('/(?<!`)(`+)(?!`)(.+?)(?<!`)\1(?!`)/', $line, $mm, PREG_SET_ORDER)) {
                    foreach ($mm as $m) {
                        $spans[] = [$no, trim($m[2])];
                    }
                }
                continue;
            }
            $trim = trim($line);
            if ($trim !== '' && $trim[0] === $in['fence'][0] && preg_match('/^(`{3,}|~{3,})$/', $trim) === 1
                && strlen($trim) >= strlen($in['fence'])
            ) {
                $blocks[] = ['lang' => $in['lang'], 'line' => $in['line'], 'end' => $no, 'code' => implode("\n", $in['code'])];
                $in = null;
                continue;
            }
            $strip = min($in['indent'], strspn($line, " \t"));
            $in['code'][] = substr($line, $strip);
        }
        $unclosed = null;
        if ($in !== null) {
            $unclosed = $in['start'];
            $blocks[] = ['lang' => $in['lang'], 'line' => $in['line'], 'end' => count($lines), 'code' => implode("\n", $in['code'])];
        }
        return ['blocks' => $blocks, 'spans' => $spans, 'text' => $text, 'unclosed' => $unclosed];
    }

    /** @return ?array{fields: array<string, string>, lines: array<string, int>, end: int} */
    public static function frontmatter(string $content): ?array
    {
        if (!str_starts_with($content, "---\n") && !str_starts_with($content, "---\r\n")) {
            return null;
        }
        $lines = preg_split('/\r?\n/', $content) ?: [];
        $end = null;
        for ($i = 1, $n = count($lines); $i < $n; $i++) {
            if (rtrim($lines[$i]) === '---') {
                $end = $i;
                break;
            }
        }
        if ($end === null) {
            return null;
        }
        $fields = [];
        $at = [];
        $raws = [];
        for ($i = 1; $i < $end; $i++) {
            if (!preg_match('/^([A-Za-z0-9_-]+):\s*(.*)$/', $lines[$i], $m)) {
                continue;
            }
            $key = $m[1];
            $val = rtrim($m[2]);
            $at[$key] = $i + 1;
            $raws[$key] = $val;
            $cont = [];
            while ($i + 1 < $end && preg_match('/^\s+\S/', $lines[$i + 1])) {
                $cont[] = trim($lines[++$i]);
            }
            if (in_array($val, ['>', '>-', '>+', '|', '|-', '|+'], true)) {
                $val = implode($val[0] === '>' ? ' ' : "\n", $cont);
            } elseif ($val !== '' && ($val[0] === '"' || $val[0] === "'")) {
                $raw = implode(' ', array_merge([$val], $cont));
                if ($raw[0] === '"') {
                    $dec = json_decode($raw);
                    $val = is_string($dec) ? $dec : trim($raw, '"');
                } else {
                    $val = str_replace("''", "'", substr($raw, 1, -1));
                }
            } elseif ($val !== '') {
                $val = trim(implode(' ', array_merge([$val], $cont)));
            } else {
                $val = '';
            }
            $fields[$key] = $val;
        }
        return ['fields' => $fields, 'lines' => $at, 'raw' => $raws, 'end' => $end + 1];
    }
}

final class VsPrep
{
    /** @return array{mode: string, code: string} mode php|tagged|template; code: neutralised, starts with an open tag unless template */
    public static function raw(string $src): array
    {
        $trim = ltrim($src);
        if (str_starts_with($trim, '<?')) {
            return ['mode' => 'tagged', 'code' => self::neutralize($src)];
        }
        if (str_contains($src, '<?php') || str_contains($src, '<?=')) {
            return ['mode' => 'template', 'code' => self::neutralize($src)];
        }
        return ['mode' => 'php', 'code' => self::neutralize('<?php ' . $src)];
    }

    /** Replaces `...`, `…` and `/* ... *\/` placeholders (not spread operators) with nothing, `null` or `true`, keeping lines. */
    public static function neutralize(string $code): string
    {
        $toks = PhpToken::tokenize($code);
        $out = [];
        $sig = [];
        foreach ($toks as $k => $tk) {
            $out[$k] = $tk->text;
            $id = $tk->id;
            if (!in_array($id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG, T_INLINE_HTML], true) || self::isPlaceholderComment($tk)) {
                $sig[] = $k;
            }
        }
        $keepNext = [T_VARIABLE => 1, T_STRING => 1, T_NAME_QUALIFIED => 1, T_NAME_FULLY_QUALIFIED => 1,
            T_NAME_RELATIVE => 1, T_STATIC => 1, T_NEW => 1, T_ARRAY => 1, 91 => 1, 40 => 1];
        $toNull = [61 => 1, T_DOUBLE_ARROW => 1, T_RETURN => 1, 63 => 1, 58 => 1, T_COALESCE => 1, T_YIELD => 1,
            T_ECHO => 1, 46 => 1, T_IS_EQUAL => 1, T_IS_IDENTICAL => 1, T_IS_NOT_EQUAL => 1, T_IS_NOT_IDENTICAL => 1,
            60 => 1, 62 => 1, T_BOOLEAN_AND => 1, T_BOOLEAN_OR => 1, T_CONCAT_EQUAL => 1, T_COALESCE_EQUAL => 1,
            T_PLUS_EQUAL => 1, T_THROW => 1, T_PRINT => 1, 43 => 1, 45 => 1, 42 => 1, 47 => 1, T_LOGICAL_AND => 1, T_LOGICAL_OR => 1];
        $m = count($sig);
        for ($s = 0; $s < $m; $s++) {
            $tk = $toks[$sig[$s]];
            $isComment = $tk->id === T_COMMENT;
            $unicode = $tk->id === T_STRING && preg_match('/^(?:…)+$/u', $tk->text) === 1;
            if (!$isComment && !$unicode && $tk->id !== T_ELLIPSIS) {
                continue;
            }
            if ($tk->id === T_ELLIPSIS) {
                $next = $s + 1 < $m ? $toks[$sig[$s + 1]] : null;
                if ($next !== null && isset($keepNext[$next->id])) {
                    continue;
                }
            }
            $prev = $s > 0 ? $toks[$sig[$s - 1]] : null;
            $prev2 = $s > 1 ? $toks[$sig[$s - 2]] : null;
            $rep = '';
            if ($prev !== null) {
                if (isset($toNull[$prev->id])) {
                    $rep = 'null';
                } elseif ($prev->id === 40 && $prev2 !== null && in_array($prev2->id, [T_IF, T_ELSEIF, T_WHILE, T_SWITCH, T_MATCH], true)) {
                    $rep = 'true';
                } elseif ($prev->id === 40 && $prev2 !== null && $prev2->id === T_FOREACH) {
                    $rep = '[]';
                }
            }
            if ($isComment && $rep === '') {
                continue;
            }
            $out[$sig[$s]] = $isComment ? $rep . str_repeat("\n", substr_count($tk->text, "\n")) : $rep;
        }
        return implode('', $out);
    }

    private static function isPlaceholderComment(PhpToken $tk): bool
    {
        return $tk->id === T_COMMENT && preg_match('~^/\*[\s.…]*\*/$~u', $tk->text) === 1;
    }

    /** Wraps a neutralised php/tagged block as a class body, function body or array literal, keeping line numbers. */
    public static function wrap(string $code, string $kind): string
    {
        $lines = explode("\n", $code);
        foreach ($lines as $k => $l) {
            $p = strpos($l, '<?php');
            if ($p !== false) {
                $lines[$k] = substr($l, 0, $p) . substr($l, $p + 5);
                break;
            }
            if (trim($l) !== '') {
                break;
            }
        }
        $h = count($lines) - 1;
        $inUse = false;
        $inComment = false;
        foreach ($lines as $k => $l) {
            $s = trim($l);
            if ($inUse) {
                if (str_contains($s, ';')) {
                    $inUse = false;
                }
                continue;
            }
            if ($inComment) {
                if (str_contains($s, '*/')) {
                    $inComment = false;
                }
                continue;
            }
            if ($s === '' || str_starts_with($s, '//') || (str_starts_with($s, '#') && !str_starts_with($s, '#['))) {
                continue;
            }
            if (str_starts_with($s, '/*')) {
                if (!str_contains($s, '*/')) {
                    $inComment = true;
                }
                continue;
            }
            if (preg_match('/^(namespace\s+[\w\\\\]+\s*;|declare\s*\(|use\s+[\\\\\w])/i', $s) === 1) {
                if (stripos($s, 'use') === 0 && !str_contains($s, ';')) {
                    $inUse = true;
                }
                continue;
            }
            $h = $k;
            break;
        }
        [$open, $close] = match ($kind) {
            'class' => ['class __VerifyWrap { ', "\n}"],
            'function' => ['function __verify_wrap() { ', "\n}"],
            default => ['$__verify = [ ', "\n];"],
        };
        $lines[$h] = $open . $lines[$h];
        return '<?php ' . implode("\n", $lines) . $close;
    }
}

final class VsLint
{
    /**
     * @param array<string, string> $jobs id => code
     * @return array<string, array{ok: bool, err: ?array{0: int, 1: string}, dep: list<array{0: int, 1: string}>}>
     */
    public static function run(array $jobs, int $par, string $tmp, ?bool $multi = null): array
    {
        $multi ??= PHP_VERSION_ID >= 80300; // php -l accepts several files since PHP 8.3
        $byPath = [];
        $n = 0;
        foreach ($jobs as $id => $code) {
            $path = $tmp . '/lint-' . (++$n) . '.php';
            file_put_contents($path, $code);
            $byPath[$path] = (string)$id;
        }
        $paths = array_keys($byPath);
        $base = [PHP_BINARY, '-n', '-d', 'display_errors=stdout', '-d', 'error_reporting=-1', '-d', 'log_errors=0',
            '-d', 'html_errors=0', '-d', 'short_open_tag=0', '-l'];
        $cmds = [];
        if ($multi) {
            $size = max(1, min(60, (int)ceil(count($paths) / max(1, $par))));
            foreach (array_chunk($paths, $size) as $chunk) {
                $cmds[] = array_merge($base, $chunk);
            }
        } else {
            foreach ($paths as $p) {
                $cmds[] = array_merge($base, [$p]);
            }
        }
        $res = [];
        foreach ($byPath as $path => $id) {
            $res[$id] = ['ok' => false, 'err' => null, 'dep' => []];
        }
        foreach (VsProc::run($cmds, $par, $tmp) as [$code, $stdout, $stderr]) {
            foreach (preg_split('/\r?\n/', $stdout . "\n" . $stderr) ?: [] as $line) {
                if (preg_match('/^(?:PHP )?(Parse error|Fatal error|Deprecated|Warning|Notice|Compile Error|Compile Warning):\s+(.*) in (\S+) on line (\d+)$/', $line, $m)) {
                    $id = $byPath[$m[3]] ?? null;
                    if ($id === null) {
                        continue;
                    }
                    if (in_array($m[1], ['Deprecated', 'Warning', 'Notice', 'Compile Warning'], true)) {
                        $res[$id]['dep'][] = [(int)$m[4], $m[2]];
                    } else {
                        $res[$id]['err'] ??= [(int)$m[4], $m[2]];
                    }
                } elseif (preg_match('/^No syntax errors detected in (\S+)$/', $line, $m) && isset($byPath[$m[1]])) {
                    $res[$byPath[$m[1]]]['ok'] = true;
                }
            }
        }
        foreach ($byPath as $path => $id) {
            @unlink($path);
            if ($res[$id]['ok']) {
                $res[$id]['err'] = null;
            } else {
                $res[$id]['err'] ??= [1, 'php -l failed without a message'];
            }
        }
        return $res;
    }
}

// ---------------------------------------------------------------------------------------------
// Symbol checks
// ---------------------------------------------------------------------------------------------

final class VsCodeChecker
{
    private const PSEUDO = ['self' => 1, 'static' => 1, 'parent' => 1];

    /** @var list<PhpToken> */
    private array $t = [];
    private bool $inline = false;
    private string $ns = '';
    private array $uses = [];
    /** @var array<string, array{fq: string, ext: ?int}> */
    private array $declared = [];
    /** @var array<string, true> lower-case names of functions/methods declared in the block */
    private array $declaredFns = [];
    private array $vars = [];
    private array $issues = [];
    private array $seen = [];
    private array $done = [];
    private array $warnedImport = [];
    private array $classStack = [];
    private array $parentRefs = [];
    /** @var array<string, list<?array>> trait refs used by block classes */
    private array $classTraits = [];
    /** @var array<string, int> checks resolved against the index, by kind */
    public array $counts = ['import' => 0, 'class' => 0, 'static' => 0, 'const' => 0, 'instance' => 0, 'named' => 0];

    /** @var list<string> namespace prefixes of the checked skill's module, then Bitrix\Main */
    private array $hints = ['bitrix\\main\\'];

    public function __construct(
        private VsIndex $idx,
        private array $userShort,
        private array $userFq,
        private string $guessLevel,
    ) {
    }

    /** bitrix-landing → Bitrix\Landing\, bitrix-iblocks → Bitrix\Iblock\, bitrix24-crm → Bitrix\Crm\; always Bitrix\Main\ last. */
    public function setSkill(string $skill): void
    {
        $this->hints = [];
        $x = (string)preg_replace('/^bitrix(?:24)?-/', '', $skill);
        foreach ([$x, rtrim($x, 's'), str_replace('-', '', $x)] as $m) {
            if ($m !== '' && $m !== 'main' && $this->idx->isNamespace('bitrix\\' . $m)) {
                $this->hints[] = 'bitrix\\' . $m . '\\';
            }
        }
        $this->hints = array_values(array_unique($this->hints));
        $this->hints[] = 'bitrix\\main\\';
    }

    /** @return list<array{line: int, level: string, msg: string}> lines are 1-based within $code */
    public function check(string $code, bool $inline): array
    {
        try {
            [$this->t] = VsTok::sig($code);
        } catch (Throwable) {
            return [];
        }
        $this->inline = $inline;
        $this->ns = '';
        $this->uses = [];
        $this->declared = [];
        $this->declaredFns = [];
        $this->vars = [];
        $this->issues = [];
        $this->seen = [];
        $this->done = [];
        $this->warnedImport = [];
        $this->classStack = [];
        $this->parentRefs = [];
        $this->classTraits = [];
        $this->prepass();
        $this->mainPass();
        return $this->issues;
    }

    private function prepass(): void
    {
        $t = $this->t;
        $n = count($t);
        $depth = 0;
        for ($i = 0; $i < $n; $i++) {
            $id = $t[$i]->id;
            if ($id === 123 || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
                $depth++;
            } elseif ($id === 125) {
                $depth--;
            } elseif ($id === T_FUNCTION && VsTok::isIdent($t[$i + 1] ?? null)) {
                $this->declaredFns[strtolower($t[$i + 1]->text)] = true;
            } elseif ($id === T_NAMESPACE) {
                $nx = $t[$i + 1] ?? null;
                if ($nx !== null && ($nx->id === T_STRING || $nx->id === T_NAME_QUALIFIED)) {
                    $this->ns = $nx->text;
                }
            } elseif ($id === T_USE && $depth === 0 && ($t[$i - 1]->id ?? 0) !== 41) {
                [$items, $end] = VsTok::parseUse($t, $i);
                foreach ($items as [$fq, $alias, $kind]) {
                    if ($kind === 'class') {
                        $this->uses[strtolower($alias)] = $fq;
                    }
                }
                $i = max($i, $end - 1);
            } elseif (($id === T_CLASS || $id === T_INTERFACE || $id === T_TRAIT || $id === T_ENUM)
                && ($t[$i - 1]->id ?? 0) !== T_DOUBLE_COLON
            ) {
                $nameTok = $t[$i + 1] ?? null;
                if (VsTok::isIdent($nameTok) && $nameTok->id !== T_EXTENDS && $nameTok->id !== T_IMPLEMENTS) {
                    $ext = null;
                    if (($t[$i + 2]->id ?? 0) === T_EXTENDS && VsTok::isName($t[$i + 3] ?? null)) {
                        $ext = $i + 3;
                    }
                    $this->declared[strtolower($nameTok->text)] = [
                        'fq' => ($this->ns === '' ? '' : $this->ns . '\\') . $nameTok->text,
                        'ext' => $ext,
                    ];
                }
            }
        }
    }

    private function mainPass(): void
    {
        $t = $this->t;
        $n = count($t);
        $depth = 0;
        $pendingClass = null;
        for ($i = 0; $i < $n; $i++) {
            $tok = $t[$i];
            $id = $tok->id;
            $prevId = $t[$i - 1]->id ?? 0;
            if ($id === 123 || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
                $depth++;
                if ($pendingClass !== null) {
                    $this->classStack[] = [$depth, $pendingClass];
                    $pendingClass = null;
                }
                continue;
            }
            if ($id === 125) {
                if ($this->classStack !== [] && $this->classStack[count($this->classStack) - 1][0] === $depth) {
                    array_pop($this->classStack);
                }
                $depth--;
                continue;
            }
            if ($id === T_NAMESPACE) {
                if (VsTok::isName($t[$i + 1] ?? null)) {
                    $i++;
                }
                continue;
            }
            if ($id === T_USE) {
                $inBody = $this->classStack !== [] && $this->classStack[count($this->classStack) - 1][0] === $depth;
                if ($depth === 0 && $prevId !== 41) {
                    [$items, $end] = VsTok::parseUse($t, $i);
                    foreach ($items as [$fq, $alias, $kind, $at]) {
                        $this->done[$at] = true;
                        if ($kind === 'class') {
                            $this->checkImport($fq, $at);
                        }
                    }
                    $i = max($i, $end - 1);
                } elseif ($inBody) {
                    $cls = $this->classStack[count($this->classStack) - 1][1];
                    for ($k = $i + 1; $k < $n && $t[$k]->id !== 59 && $t[$k]->id !== 123; $k++) {
                        if (VsTok::isName($t[$k])) {
                            $this->done[$k] = true;
                            $ref = $this->ref($k);
                            $this->existCheck($ref, $k, false);
                            $this->classTraits[$cls][] = $ref;
                        }
                    }
                }
                continue;
            }
            if ($id === T_CLASS || $id === T_INTERFACE || $id === T_TRAIT || $id === T_ENUM) {
                if ($prevId === T_DOUBLE_COLON) {
                    continue;
                }
                $nameTok = $t[$i + 1] ?? null;
                $named = VsTok::isIdent($nameTok) && $nameTok->id !== T_EXTENDS && $nameTok->id !== T_IMPLEMENTS;
                $pendingClass = $named ? strtolower($nameTok->text) : '';
                for ($j = $i + 1; $j < $n && $t[$j]->id !== 123 && $t[$j]->id !== 59; $j++) {
                    if (VsTok::isName($t[$j]) && in_array($t[$j - 1]->id, [T_EXTENDS, T_IMPLEMENTS, 44], true)) {
                        $this->done[$j] = true;
                        $this->existCheck($this->ref($j), $j, false);
                    }
                }
                continue;
            }
            if ($id === T_ATTRIBUTE) {
                $this->attribute($i);
                continue;
            }
            if ($id === T_NEW && $prevId !== T_DOUBLE_COLON && $prevId !== T_OBJECT_OPERATOR && $prevId !== T_NULLSAFE_OBJECT_OPERATOR) {
                $this->newExpr($i);
                continue;
            }
            if ($id === T_FUNCTION || $id === T_FN) {
                $this->typedParams($i);
                continue;
            }
            if ($id === T_VARIABLE) {
                $next = $t[$i + 1]->id ?? 0;
                if ($next === 61 && $tok->text !== '$this') {
                    [$types, $end] = $this->exprTypes($i + 2);
                    $endId = $t[$end]->id ?? 59;
                    if ($types !== null && ($endId === 59 || $endId === T_CLOSE_TAG)) {
                        $this->vars[$tok->text] = $types;
                    } else {
                        unset($this->vars[$tok->text]);
                    }
                } elseif (($next === T_OBJECT_OPERATOR || $next === T_NULLSAFE_OBJECT_OPERATOR) && isset($this->vars[$tok->text])) {
                    $this->chain($this->vars[$tok->text], $i + 1, true);
                } elseif ($next === T_OBJECT_OPERATOR && $tok->text === '$this' && ($t[$i + 3]->id ?? 0) === 40
                    && VsTok::isIdent($t[$i + 2] ?? null) && !isset($this->declaredFns[strtolower($t[$i + 2]->text)])
                ) {
                    $types = $this->thisTypes();
                    if ($types !== null) {
                        $this->chain($types, $i + 1, true);
                    }
                }
                continue;
            }
            if ((VsTok::isName($tok) || $id === T_STATIC) && ($t[$i + 1]->id ?? 0) === T_DOUBLE_COLON) {
                if ($prevId !== T_OBJECT_OPERATOR && $prevId !== T_NULLSAFE_OBJECT_OPERATOR && $prevId !== T_DOUBLE_COLON) {
                    $this->staticAccess($i);
                }
                continue;
            }
            if (($id === T_NAME_FULLY_QUALIFIED || $id === T_NAME_QUALIFIED || $id === T_NAME_RELATIVE) && !isset($this->done[$i])) {
                $next = $t[$i + 1]->id ?? 0;
                if (str_contains($tok->text, '…')) {
                    $this->ellipsisName($i);
                } elseif ($next !== 40) {
                    $ref = $this->ref($i);
                    if ($ref !== null && $ref['mode'] === 'exact') {
                        $this->existCheck($ref, $i, true);
                    }
                }
            }
        }
    }

    // --- name resolution -------------------------------------------------------------------

    /**
     * @return ?array{fq: string, keys: list<string>, mode: string, label: string, via: string}
     *         mode exact (name resolved, enforced namespace) or guess (unimported short name matched by short name)
     */
    private function ref(int $i, bool $report = true): ?array
    {
        $tok = $this->t[$i];
        $text = $tok->text;
        $id = $tok->id;
        if (str_contains($text, '…')) {
            return null;
        }
        if ($id === T_STRING) {
            $lc = strtolower($text);
            if (isset(self::PSEUDO[$lc]) || isset(VsKernelParser::SCALARS[$lc])) {
                return null;
            }
            if (isset($this->uses[$lc])) {
                return $this->exact($this->uses[$lc], 'import');
            }
            if (isset($this->declared[$lc])) {
                return null;
            }
            if ($this->ns !== '') {
                $fq = $this->ns . '\\' . $text;
                if ($this->idx->enforced($fq)) {
                    return $this->exact($fq, 'namespace');
                }
                $g = $this->guess($text);
                if ($g !== null && !empty($g['strong']) && $report && !$this->inline && !isset($this->warnedImport[$lc])) {
                    $this->warnedImport[$lc] = true;
                    $this->issue($i, 'WARN', sprintf('`%s` is not imported: in namespace `%s` it resolves to `%s`', $text, $this->ns, $fq));
                }
                return $g;
            }
            return $this->global($text);
        }
        if ($id === T_NAME_QUALIFIED) {
            $first = strtolower(substr($text, 0, (int)strpos($text, '\\')));
            if (isset($this->uses[$first])) {
                return $this->exact(VsTok::resolveText($text, $this->ns, $this->uses), 'import-prefix');
            }
            if ($this->ns !== '') {
                $fq = $this->ns . '\\' . $text;
                return $this->idx->enforced($fq) ? $this->exact($fq, 'namespace') : $this->guess($text);
            }
            return $this->idx->enforced($text) ? $this->exact($text, 'qualified') : $this->guess($text);
        }
        if ($id === T_NAME_FULLY_QUALIFIED) {
            $fq = substr($text, 1);
            if (!str_contains($fq, '\\')) {
                return $this->global($fq, false);
            }
            return $this->exact($fq, 'qualified');
        }
        if ($id === T_NAME_RELATIVE) {
            return $this->exact(VsTok::resolve($tok, $this->ns, $this->uses), 'qualified');
        }
        return null;
    }

    private function exact(string $fq, string $via): ?array
    {
        $fq = ltrim($fq, '\\');
        if (isset($this->userFq[strtolower($fq)]) || !$this->idx->enforced($fq)) {
            return null;
        }
        $key = $this->idx->key($fq);
        return ['fq' => $fq, 'keys' => $key === null ? [] : [$key], 'mode' => 'exact',
            'label' => $key === null ? $fq : $this->idx->name($key), 'via' => $via];
    }

    private function global(string $name, bool $allowGuess = true): ?array
    {
        $lc = strtolower($name);
        if (isset($this->userShort[$lc]) || isset($this->declared[$lc])) {
            return null;
        }
        if (preg_match('/^C[A-Z][A-Za-z0-9_]+$/', $name) === 1) {
            $key = $this->idx->key($name);
            return ['fq' => $name, 'keys' => $key === null ? [] : [$key], 'mode' => 'exact',
                'label' => $key === null ? $name : $this->idx->name($key), 'via' => 'global'];
        }
        return $allowGuess ? $this->guess($name) : null;
    }

    /**
     * Unimported short (or partially qualified) name matched to kernel classes by short name.
     * `alt` holds same-named classes outside the enforced roots (e.g. Symfony Command): a member
     * found there silences the report. Strong (reported at --guess level, else WARN) = no alternatives
     * and a single candidate or a unique shallowest one in the skill's module / Bitrix\Main.
     */
    private function guess(string $name): ?array
    {
        if (isset($this->userShort[strtolower(VsTok::lastSeg($name))]) || isset(VS_EXTERNAL_SHORT[strtolower($name)])) {
            return null;
        }
        $cands = $this->idx->candidates($name);
        if ($cands === []) {
            return null;
        }
        $alt = array_values(array_diff($this->idx->sameShort($name), $cands));
        // A unique shallowest candidate in the skill's module (Block in bitrix-landing → Bitrix\Landing\Block)
        // or in Bitrix\Main (Loader → Bitrix\Main\Loader, Option → Main\Config\Option) is what an unimported
        // name in an example almost always means.
        $primary = false;
        foreach ($this->hints as $hint) {
            $depth = [];
            foreach ($cands as $k) {
                if (str_starts_with($k, $hint)) {
                    $depth[$k] = substr_count($k, '\\');
                }
            }
            if ($depth !== []) {
                $top = array_keys($depth, min($depth), true);
                $primary = count($top) === 1;
                if ($primary) {
                    $cands = array_values(array_unique(array_merge($top, $cands)));
                }
                break;
            }
        }
        return ['fq' => $name, 'keys' => $cands, 'mode' => 'guess', 'label' => $name, 'via' => 'guess',
            'alt' => $alt, 'strong' => $alt === [] && (count($cands) === 1 || $primary)];
    }

    private function parentRef(): ?array
    {
        if ($this->classStack === []) {
            return null;
        }
        $cls = $this->classStack[count($this->classStack) - 1][1];
        if ($cls === '' || !isset($this->declared[$cls]) || $this->declared[$cls]['ext'] === null) {
            return null;
        }
        if (!array_key_exists($cls, $this->parentRefs)) {
            $this->parentRefs[$cls] = $this->ref($this->declared[$cls]['ext'], false);
        }
        return $this->parentRefs[$cls];
    }

    /** Kernel parent and traits of the enclosing example class; null if any of them is not a kernel class. */
    private function thisTypes(): ?array
    {
        if ($this->classStack === []) {
            return null;
        }
        $cls = $this->classStack[count($this->classStack) - 1][1];
        $refs = $this->classTraits[$cls] ?? [];
        if (isset($this->declared[$cls]) && $this->declared[$cls]['ext'] !== null) {
            $refs[] = $this->parentRef();
        }
        if ($refs === []) {
            return null;
        }
        $keys = [];
        $lvl = 'ERROR';
        foreach ($refs as $ref) {
            if ($ref === null || $ref['keys'] === []) {
                return null;
            }
            $keys = array_merge($keys, $ref['keys']);
            if ($this->level($ref) !== 'ERROR') {
                $lvl = $this->level($ref);
            }
        }
        return ['keys' => array_values(array_unique($keys)), 'lvl' => $lvl, 'this' => true];
    }

    private function insideSubclassOf(array $keys): bool
    {
        foreach ($this->declared as $lc => $d) {
            if ($d['ext'] === null) {
                continue;
            }
            $this->parentRefs[$lc] ??= $this->ref($d['ext'], false);
            foreach ($this->parentRefs[$lc]['keys'] ?? [] as $pk) {
                foreach ($keys as $k) {
                    if ($this->idx->isSubclassOf($pk, $k)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    private function level(?array $ref): string
    {
        if (($ref['mode'] ?? 'exact') === 'exact') {
            return 'ERROR';
        }
        return !empty($ref['strong']) ? $this->guessLevel : 'WARN';
    }

    /** A member exists in a same-named class outside the enforced roots: the guess is unreliable. */
    private function altHas(?array $ref, string $member, string $what): bool
    {
        foreach ($ref['alt'] ?? [] as $k) {
            if ($what === 'm' ? $this->idx->findMethod($k, strtolower($member))[0] !== null : $this->idx->findConst($k, $member)[0]) {
                return true;
            }
        }
        return false;
    }

    // --- checks ------------------------------------------------------------------------------

    private function issue(int $i, string $level, string $msg): void
    {
        $line = $this->t[$i]->line ?? 1;
        $k = $line . '|' . $msg;
        if (isset($this->seen[$k])) {
            return;
        }
        $this->seen[$k] = true;
        $this->issues[] = ['line' => $line, 'level' => $level, 'msg' => $msg];
    }

    private function checkImport(string $fq, int $at): void
    {
        if (!$this->idx->enforced($fq) || isset($this->userFq[strtolower($fq)]) || !str_contains($fq, '\\')) {
            return;
        }
        $this->counts['import']++;
        if ($this->idx->key($fq) !== null || $this->idx->isNamespace($fq)) {
            return;
        }
        $this->issue($at, 'ERROR', sprintf('use `%s`: class or namespace not found in kernel%s', $fq, $this->idx->suggestClass($fq)));
    }

    private function existCheck(?array $ref, int $i, bool $allowNs): bool
    {
        if ($ref !== null && $ref['mode'] === 'exact') {
            $this->counts['class']++;
        }
        if ($ref === null || $ref['mode'] !== 'exact' || $ref['keys'] !== []) {
            return true;
        }
        if ($allowNs && $this->idx->isNamespace($ref['fq'])) {
            return true;
        }
        if ($ref['via'] !== 'import') {
            $this->issue($i, 'ERROR', sprintf('%s `%s` not found in kernel%s', $allowNs ? 'Class or namespace' : 'Class', $ref['fq'],
                $this->idx->suggestClass($ref['fq'])));
        }
        return false;
    }

    /** `Bitrix\Tasks\V2\Public\Command\…` in prose: the part before `\…` must be a namespace or class. */
    private function ellipsisName(int $i): void
    {
        $text = $this->t[$i]->text;
        $p = strrpos(substr($text, 0, (int)strpos($text, '…')), '\\');
        if ($p === false || $p === 0) {
            return;
        }
        $prefix = substr($text, 0, $p);
        $tok = new PhpToken($this->t[$i]->id, $prefix, $this->t[$i]->line);
        $saved = $this->t[$i];
        $this->t[$i] = $tok;
        $ref = $this->ref($i);
        $this->t[$i] = $saved;
        if ($ref !== null && $ref['mode'] === 'exact') {
            $this->existCheck($ref, $i, true);
        }
    }

    private function staticAccess(int $i): void
    {
        $t = $this->t;
        $tok = $t[$i];
        $lc = strtolower($tok->text);
        $member = $t[$i + 2] ?? null;
        if ($member === null || $tok->id === T_STATIC || $lc === 'self' || $lc === 'static') {
            return;
        }
        $isParent = $lc === 'parent';
        if (($t[$i - 1]->id ?? 0) === 42) {
            return; // `*Settings::x()`: wildcard class name in prose
        }
        $ref = $isParent ? $this->parentRef() : $this->ref($i);
        $this->done[$i] = true;
        if ($member->id === T_CLASS || $member->id === T_VARIABLE) {
            $this->existCheck($ref, $i, false);
            return;
        }
        if (!VsTok::isIdent($member)) {
            return;
        }
        if (($t[$i + 3]->id ?? 0) === 42) {
            $this->prefixAccess($ref, $i, $i + 2);
            return;
        }
        if (($t[$i + 3]->id ?? 0) === 40) {
            $types = $this->staticCall($ref, $i, $i + 2, $i + 3, $isParent, true);
            $this->chain($types, VsTok::close($t, $i + 3) + 1, true);
            return;
        }
        $this->constAccess($ref, $i, $i + 2);
    }

    /**
     * @return array{0: list<array{0: string, 1: array, 2: string}>, 1: bool} found [key, method, owner], unknown
     *         (unknown: an ancestor is not indexed, magic __call/__callStatic, interface, or a valid ORM accessor)
     */
    private function lookup(array $keys, string $member, bool $static, bool $isParent): array
    {
        $lcm = strtolower($member);
        $found = [];
        $unknown = false;
        foreach ($keys as $key) {
            [$m, $owner, $complete, $magicS, $magicC] = $this->idx->findMethod($key, $lcm);
            if ($m !== null) {
                $found[] = [$key, $m, (string)$owner];
                continue;
            }
            if (!$complete || ($static && $magicS)) {
                $unknown = true;
                continue;
            }
            if ($static && !$isParent) {
                continue;
            }
            if ($this->idx->isInterface($key)) {
                $unknown = true;
                continue;
            }
            if (!$magicC) {
                continue;
            }
            // __call: plain classes (crm Item, facades) are unknown. ORM objects/collections accept
            // get*/set*/has*/is*/fill*/… accessors of tablet fields; other names throw in EntityObject::__call.
            $fields = $this->idx->ormFields($key);
            if ($fields === false) {
                $unknown = true;
                continue;
            }
            $cands = VsIndex::accessorFields($member);
            if ($cands === null) {
                continue;
            }
            if ($fields === null || str_starts_with($cands[0], 'UF')) {
                $unknown = true;
                continue;
            }
            foreach ($cands as $f) {
                if (isset($fields[$f])) {
                    $unknown = true;
                    continue 2;
                }
            }
        }
        return [$found, $unknown];
    }

    private function staticCall(?array $ref, int $i, int $mi, int $paren, bool $isParent, bool $report): ?array
    {
        if ($ref === null) {
            return null;
        }
        if ($report ? !$this->existCheck($ref, $i, false) : $ref['keys'] === []) {
            return null;
        }
        $member = $this->t[$mi]->text;
        $lcm = strtolower($member);
        [$found, $unknown] = $this->lookup($ref['keys'], $member, !$isParent, $isParent);
        if ($report) {
            $this->counts['static']++;
        }
        $label = $isParent ? 'parent (' . $ref['label'] . ')' : $ref['label'];
        if ($found === []) {
            if ($unknown || !$report || $this->altHas($ref, $member, 'm')) {
                return null;
            }
            if ($isParent && $lcm === '__construct') {
                $this->issue($mi, $this->level($ref), sprintf('parent::__construct(): `%s` and its ancestors have no constructor', $ref['label']));
                return null;
            }
            $this->issue($mi, $this->level($ref), sprintf('Method `%s::%s()` not found%s', $label, $member, $this->memberHint($ref, $member, 'm')));
            return null;
        }
        if ($report && !$isParent && !($this->inline && !$this->argsHaveVars($paren))) {
            $allInstance = true;
            $interfaces = true;
            $singleton = false;
            foreach ($found as [$k, $m]) {
                if ($m[1] !== 0) {
                    $allInstance = false;
                }
                if (!$this->idx->isInterface($k)) {
                    $interfaces = false;
                }
                if (($this->idx->findMethod($k, 'getinstance')[0][1] ?? 0) === 1) {
                    $singleton = true;
                }
            }
            // Prose often writes Class::method($x) for instance methods: only singletons (the static
            // call is a real trap there) are errors inline; interfaces are skipped.
            if ($allInstance && !($this->inline && $interfaces) && !$this->insideSubclassOf($ref['keys']) && $lcm !== '__construct') {
                $hint = $singleton ? sprintf('; use `%s::getInstance()->%s()`', VsTok::lastSeg($ref['label']), $member) : '';
                $level = $this->inline && !$singleton ? 'WARN' : $this->level($ref);
                $this->issue($mi, $level, sprintf('`%s::%s()` is not static%s', $ref['label'], $member, $hint));
            }
        }
        if ($report) {
            $this->namedArgs($found, $paren, $label . '::' . $member, $this->level($ref));
        }
        return $this->returnTypes($found, $this->level($ref));
    }

    private function constAccess(?array $ref, int $i, int $mi): void
    {
        if ($ref === null || !$this->existCheck($ref, $i, false) || $ref['keys'] === []) {
            return;
        }
        $name = $this->t[$mi]->text;
        $unknown = false;
        $this->counts['const']++;
        foreach ($ref['keys'] as $key) {
            [$ok, $complete] = $this->idx->findConst($key, $name);
            if ($ok) {
                return;
            }
            if (!$complete) {
                $unknown = true;
            }
            if ($this->inline && $this->idx->findMethod($key, strtolower($name))[0] !== null) {
                return;
            }
        }
        if ($unknown || $this->altHas($ref, $name, 'c') || ($this->inline && $this->altHas($ref, $name, 'm'))) {
            return;
        }
        $this->issue($mi, $this->level($ref), sprintf('Constant `%s::%s` not found%s', $ref['label'], $name, $this->memberHint($ref, $name, 'c')));
    }

    /** `X::PREFIX_*` / `X::getSome*()` in prose: some constant or method must start with the prefix. */
    private function prefixAccess(?array $ref, int $i, int $mi): void
    {
        if ($ref === null || !$this->existCheck($ref, $i, false) || $ref['keys'] === []) {
            return;
        }
        $prefix = $this->t[$mi]->text;
        foreach (array_merge($ref['keys'], $ref['alt'] ?? []) as $key) {
            if (!$this->idx->lineage($key)[1]) {
                return;
            }
            foreach (array_merge($this->idx->members($key, 'c'), $this->idx->members($key, 'm')) as $name) {
                if (stripos($name, $prefix) === 0) {
                    return;
                }
            }
        }
        $this->issue($mi, $this->level($ref), sprintf('No constant or method of `%s` starts with `%s`', $ref['label'], $prefix));
    }

    private function memberHint(array $ref, string $member, string $what): string
    {
        $names = [];
        foreach ($ref['keys'] as $k) {
            $names = array_merge($names, $this->idx->members($k, $what));
        }
        $hint = '';
        $best = VsIndex::closest($member, $names);
        if ($best !== null) {
            $hint .= sprintf('; did you mean `%s%s`?', $best, $what === 'm' ? '()' : '');
        }
        $others = [];
        foreach ($ref['keys'] as $k) {
            foreach ($this->idx->sameShort(VsTok::lastSeg($k)) as $other) {
                if (in_array($other, $ref['keys'], true) || in_array($other, $others, true)) {
                    continue;
                }
                $has = $what === 'm'
                    ? $this->idx->findMethod($other, strtolower($member))[0] !== null
                    : $this->idx->findConst($other, $member)[0];
                if ($has) {
                    $others[] = $other;
                }
            }
        }
        if ($others !== []) {
            $hint .= sprintf('; it exists in `%s`', implode('`, `', array_map(fn ($k) => $this->idx->name($k), array_slice($others, 0, 2))));
        }
        if ($ref['mode'] === 'guess') {
            $hint .= sprintf(' (unimported `%s` matched: `%s`)', $ref['label'],
                implode('`, `', array_map(fn ($k) => $this->idx->name($k), array_slice($ref['keys'], 0, 3))));
        }
        return $hint;
    }

    private function argsHaveVars(int $paren): bool
    {
        $close = VsTok::close($this->t, $paren);
        for ($k = $paren + 1; $k < $close; $k++) {
            if ($this->t[$k]->id === T_VARIABLE) {
                return true;
            }
        }
        return false;
    }

    /** @param list<array{0: string, 1: array, 2: string}> $found */
    private function namedArgs(array $found, int $paren, string $label, string $level): void
    {
        $t = $this->t;
        $close = VsTok::close($t, $paren);
        $d = 0;
        for ($k = $paren + 1; $k < $close; $k++) {
            $id = $t[$k]->id;
            if (VsTok::isOpen($id)) {
                $d++;
                continue;
            }
            if (VsTok::isClose($id)) {
                $d--;
                continue;
            }
            if ($d !== 0 || ($t[$k + 1]->id ?? 0) !== 58 || !VsTok::isIdent($t[$k])) {
                continue;
            }
            $prev = $t[$k - 1]->id;
            if ($prev !== 40 && $prev !== 44) {
                continue;
            }
            $name = $t[$k]->text;
            $ok = false;
            $params = [];
            $this->counts['named']++;
            foreach ($found as [, $m]) {
                if ($m[2] === null || $m[3] === 1 || in_array($name, $m[2], true)) {
                    $ok = true;
                    break;
                }
                $params = array_merge($params, $m[2]);
            }
            if ($ok) {
                continue;
            }
            $params = array_values(array_unique($params));
            $best = VsIndex::closest($name, $params);
            $this->issue($k, $level, sprintf('Unknown named argument `%s:` for `%s()`; parameters: %s%s', $name, $label,
                $params === [] ? '(none)' : '$' . implode(', $', $params),
                $best !== null ? '; did you mean `' . $best . ':`?' : ''));
        }
    }

    /** @param list<array{0: string, 1: array, 2: string}> $found */
    private function returnTypes(array $found, string $lvl): ?array
    {
        $keys = [];
        foreach ($found as [$key, $m]) {
            if ($m[4] === null) {
                return null;
            }
            foreach ($m[4] as $rt) {
                $k = $rt === '@static' ? $key : $this->idx->key((string)$rt);
                if ($k === null) {
                    return null;
                }
                $keys[] = $k;
            }
        }
        $keys = array_values(array_unique($keys));
        return $keys === [] ? null : ['keys' => $keys, 'lvl' => $lvl];
    }

    /** @return array{0: ?array, 1: int} types after the -> chain and the index after it */
    private function chain(?array $types, int $j, bool $report): array
    {
        $t = $this->t;
        while ($types !== null && isset($t[$j]) && ($t[$j]->id === T_OBJECT_OPERATOR || $t[$j]->id === T_NULLSAFE_OBJECT_OPERATOR)) {
            $m = $t[$j + 1] ?? null;
            if (!VsTok::isIdent($m) || ($t[$j + 2]->id ?? 0) !== 40) {
                return [null, $j];
            }
            $paren = $j + 2;
            $types = $this->instanceCall($types, $j + 1, $paren, $report);
            $j = VsTok::close($t, $paren) + 1;
        }
        return [$types, $j];
    }

    private function instanceCall(array $types, int $mi, int $paren, bool $report): ?array
    {
        $member = $this->t[$mi]->text;
        [$found, $unknown] = $this->lookup($types['keys'], $member, false, false);
        if ($report) {
            $this->counts['instance']++;
        }
        $level = $types['lvl'];
        $label = implode('|', array_map(fn ($k) => $this->idx->name($k), array_slice($types['keys'], 0, 3)));
        if ($found === []) {
            if (!$unknown && $report) {
                $ref = ['keys' => $types['keys'], 'mode' => 'exact', 'label' => $label];
                $hint = $this->memberHint($ref, $member, 'm');
                if (!empty($types['this'])) {
                    // $this->x() in an example class: x() may be a helper the example leaves out
                    $this->issue($mi, str_contains($hint, 'did you mean') ? $level : 'WARN',
                        sprintf('Method `%s()` is not declared in the example and not found in `%s`%s', $member, $label, $hint));
                } else {
                    $this->issue($mi, $level, sprintf('Method `%s::%s()` not found (object type inferred)%s', $label, $member, $hint));
                }
            }
            return null;
        }
        if ($report) {
            $this->namedArgs($found, $paren, $label . '->' . $member, $level);
        }
        return $this->returnTypes($found, $types['lvl']);
    }

    /** Type of an expression start (new X, X::m(), $var) followed by an optional -> chain; silent. */
    private function exprTypes(int $k): array
    {
        $t = $this->t;
        $tok = $t[$k] ?? null;
        if ($tok === null) {
            return [null, $k];
        }
        $types = null;
        if ($tok->id === T_NEW && VsTok::isName($t[$k + 1] ?? null)) {
            $types = $this->refTypes($this->ref($k + 1, false));
            $j = $k + 2;
            if (($t[$j]->id ?? 0) === 40) {
                $j = VsTok::close($t, $j) + 1;
            }
        } elseif ($tok->id === 40 && ($t[$k + 1]->id ?? 0) === T_NEW) {
            [$types] = $this->exprTypes($k + 1);
            $j = VsTok::close($t, $k) + 1;
        } elseif (VsTok::isName($tok) && ($t[$k + 1]->id ?? 0) === T_DOUBLE_COLON && VsTok::isIdent($t[$k + 2] ?? null) && ($t[$k + 3]->id ?? 0) === 40) {
            $isParent = strtolower($tok->text) === 'parent';
            $ref = $isParent ? $this->parentRef() : $this->ref($k, false);
            $types = $this->staticCall($ref, $k, $k + 2, $k + 3, $isParent, false);
            $j = VsTok::close($t, $k + 3) + 1;
        } elseif ($tok->id === T_VARIABLE && isset($this->vars[$tok->text])) {
            $types = $this->vars[$tok->text];
            $j = $k + 1;
        } else {
            return [null, $k];
        }
        return $this->chain($types, $j, false);
    }

    private function refTypes(?array $ref): ?array
    {
        if ($ref === null || $ref['keys'] === []) {
            return null;
        }
        return ['keys' => $ref['keys'], 'lvl' => $this->level($ref)];
    }

    private function newExpr(int $i): void
    {
        $t = $this->t;
        $nt = $t[$i + 1] ?? null;
        if (!VsTok::isName($nt) || isset(self::PSEUDO[strtolower($nt->text)])) {
            return;
        }
        $ref = $this->ref($i + 1);
        $this->done[$i + 1] = true;
        if (!$this->existCheck($ref, $i + 1, false) || $ref === null || ($t[$i + 2]->id ?? 0) !== 40) {
            return;
        }
        $this->ctorArgs($ref, $i + 2);
        $close = VsTok::close($t, $i + 2);
        $types = $this->refTypes($ref);
        $p2 = $t[$i - 2]->id ?? 0;
        $callParen = in_array($p2, [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE, T_VARIABLE, T_STATIC,
            T_ARRAY, T_ISSET, T_EMPTY, T_LIST, T_EXIT, 41, 93, 125], true);
        if (($t[$i - 1]->id ?? 0) === 40 && !$callParen && ($t[$close + 1]->id ?? 0) === 41) {
            $this->chain($types, $close + 2, true);
        } elseif (in_array($t[$close + 1]->id ?? 0, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true)) {
            $this->chain($types, $close + 1, true);
        }
    }

    private function ctorArgs(array $ref, int $paren): void
    {
        if ($ref['keys'] === []) {
            return;
        }
        [$found] = $this->lookup($ref['keys'], '__construct', false, true);
        if ($found !== []) {
            $this->namedArgs($found, $paren, $ref['label'] . '::__construct', $this->level($ref));
        }
    }

    private function attribute(int $i): void
    {
        $t = $this->t;
        $end = VsTok::close($t, $i);
        $j = $i + 1;
        while ($j < $end && VsTok::isName($t[$j])) {
            $this->done[$j] = true;
            $ref = $this->ref($j);
            $ok = $this->existCheck($ref, $j, false);
            if (($t[$j + 1]->id ?? 0) === 40) {
                if ($ok && $ref !== null) {
                    $this->ctorArgs($ref, $j + 1);
                }
                $j = VsTok::close($t, $j + 1) + 1;
            } else {
                $j++;
            }
            if (($t[$j]->id ?? 0) !== 44) {
                break;
            }
            $j++;
        }
    }

    private function typedParams(int $i): void
    {
        $t = $this->t;
        $j = $i + 1;
        while (isset($t[$j]) && $t[$j]->id !== 40 && $t[$j]->id !== 123 && $t[$j]->id !== 59) {
            $j++;
        }
        if (($t[$j]->id ?? 0) !== 40) {
            return;
        }
        $close = VsTok::close($t, $j);
        $typeTok = null;
        $multi = false;
        $d = 0;
        for ($k = $j + 1; $k < $close; $k++) {
            $id = $t[$k]->id;
            if (VsTok::isOpen($id)) {
                $d++;
                continue;
            }
            if (VsTok::isClose($id)) {
                $d--;
                continue;
            }
            if ($d > 0) {
                continue;
            }
            if ($id === 44) {
                $typeTok = null;
                $multi = false;
            } elseif ($id === 124 || $id === T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG) {
                $multi = true;
            } elseif (VsTok::isName($t[$k])) {
                $typeTok = $typeTok === null ? $k : $typeTok;
            } elseif ($id === T_VARIABLE) {
                if ($typeTok !== null && !$multi) {
                    $types = $this->refTypes($this->ref($typeTok, false));
                    if ($types !== null) {
                        $this->vars[$t[$k]->text] = $types;
                    }
                }
                $typeTok = null;
                $multi = false;
            } elseif ($id === 61) {
                $d = 1000;
            }
            if ($d >= 1000 && $id === 44) {
                $d = 0;
            }
        }
    }
}

// ---------------------------------------------------------------------------------------------
// Application
// ---------------------------------------------------------------------------------------------

final class VsApp
{
    private array $issues = [];
    private array $stats = ['skills' => 0, 'files' => 0, 'blocks' => 0, 'spans' => 0];
    private string $root = '';
    private string $tmp = '';

    public function run(array $argv): int
    {
        @ini_set('memory_limit', '-1');
        try {
            $opt = $this->args($argv);
        } catch (InvalidArgumentException $e) {
            fwrite(STDERR, 'verify-skills: ' . $e->getMessage() . "\nRun with --help for usage.\n");
            return 2;
        }
        if (isset($opt['worker'])) {
            return VsIndexBuilder::worker($opt['worker'], $opt['out'], $opt['iroot']);
        }
        if ($opt['help']) {
            fwrite(STDOUT, VS_HELP . "\n");
            return 0;
        }
        if ($opt['kernel'] === []) {
            fwrite(STDERR, "verify-skills: at least one --kernel=<bitrix/modules dir> is required.\nRun with --help for usage.\n");
            return 2;
        }
        foreach ($opt['kernel'] as $k) {
            if (!is_dir($k)) {
                fwrite(STDERR, "verify-skills: kernel dir not found: $k\n");
                return 2;
            }
        }
        $skillsDir = $opt['skills'] ?? (is_dir(getcwd() . '/skills') ? getcwd() . '/skills' : dirname(__DIR__) . '/skills');
        $skillsDir = rtrim((string)(realpath($skillsDir) ?: $skillsDir), '/');
        if (!is_dir($skillsDir)) {
            fwrite(STDERR, "verify-skills: skills dir not found: $skillsDir\n");
            return 2;
        }
        $this->root = dirname($skillsDir);
        $all = [];
        foreach (scandir($skillsDir) ?: [] as $d) {
            if ($d[0] !== '.' && is_dir($skillsDir . '/' . $d)) {
                $all[] = $d;
            }
        }
        $selected = $opt['names'] === [] ? $all : $opt['names'];
        foreach ($selected as $s) {
            if (!in_array($s, $all, true)) {
                fwrite(STDERR, "verify-skills: unknown skill: $s\n");
                return 2;
            }
        }

        $t0 = microtime(true);
        $this->tmp = sys_get_temp_dir() . '/verify-skills-' . getmypid() . '-' . bin2hex(random_bytes(3));
        @mkdir($this->tmp, 0700, true);
        register_shutdown_function(function (): void {
            foreach (glob($this->tmp . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($this->tmp);
        });

        $log = static function (string $m): void {
            fwrite(STDERR, $m . "\n");
        };
        $idx = new VsIndex();
        $builder = new VsIndexBuilder(__DIR__ . '/.cache', $opt['jobs'], $opt['nocache'], $this->tmp);
        $idxInfo = [];
        foreach ($opt['kernel'] as $k) {
            [$part, $cached, $nfiles] = $builder->load($k, $log);
            $idx->add($part);
            $idxInfo[] = ['dir' => $k, 'cached' => $cached, 'files' => $nfiles, 'classes' => count($part['c'])];
            unset($part);
        }
        if ($idx->isNamespace('Sprint\\Migration')) {
            $idx->roots[] = 'sprint\\migration\\';
        }
        $tIndex = microtime(true) - $t0;

        // Parse every skill (declared classes are collected across all of them).
        $docs = [];
        $userShort = [];
        $userFq = [];
        foreach ($all as $skill) {
            $dir = $skillsDir . '/' . $skill;
            $files = [];
            if (is_file($dir . '/SKILL.md')) {
                $files[] = $dir . '/SKILL.md';
            }
            $more = [];
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS));
            foreach ($it as $path => $info) {
                if (str_ends_with((string)$path, '.md') && $path !== $dir . '/SKILL.md') {
                    $more[] = (string)$path;
                }
            }
            sort($more);
            foreach (array_merge($files, $more) as $f) {
                $content = (string)file_get_contents($f);
                $md = VsMd::parse($content);
                $docs[$skill][$f] = ['content' => $content, 'md' => $md];
                foreach ($md['blocks'] as $b) {
                    if ($b['lang'] === 'php') {
                        $this->collectDeclared($b['code'], $userShort, $userFq);
                    }
                }
            }
        }

        foreach ($selected as $skill) {
            $this->stats['skills']++;
            $this->checkSkillFiles($skill, $skillsDir . '/' . $skill, $docs[$skill] ?? [], $all);
        }
        $this->lintBlocks($selected, $docs, $opt['jobs']);
        $checker = new VsCodeChecker($idx, $userShort, $userFq, $opt['guess']);
        foreach ($selected as $skill) {
            $checker->setSkill($skill);
            foreach ($docs[$skill] ?? [] as $file => $doc) {
                $this->checkSymbols($checker, $skill, $file, $doc['md']);
            }
        }

        $this->stats['checks'] = $checker->counts;
        $elapsed = microtime(true) - $t0;
        $meta = ['index' => $idxInfo, 'index_seconds' => round($tIndex, 2), 'seconds' => round($elapsed, 2),
            'classes' => count($idx->classes)];
        if ($opt['json']) {
            $this->printJson($meta);
        } else {
            $this->printText($opt['warnings'], $meta);
        }
        foreach ($this->issues as $i) {
            if ($i['level'] === 'ERROR') {
                return 1;
            }
        }
        return 0;
    }

    private function args(array $argv): array
    {
        $o = ['kernel' => [], 'skills' => null, 'json' => false, 'nocache' => false, 'warnings' => false,
            'help' => false, 'names' => [], 'jobs' => self::cpus(), 'guess' => 'ERROR'];
        $args = array_slice($argv, 1);
        for ($i = 0, $n = count($args); $i < $n; $i++) {
            $a = $args[$i];
            $val = null;
            if (preg_match('/^(--[a-z-]+)=(.*)$/s', $a, $m)) {
                [$a, $val] = [$m[1], $m[2]];
            }
            $need = function () use (&$val, &$i, $args, $a): string {
                if ($val !== null) {
                    return $val;
                }
                if (!isset($args[$i + 1])) {
                    throw new InvalidArgumentException("option $a needs a value");
                }
                return $args[++$i];
            };
            switch ($a) {
                case '-h':
                case '--help':
                    $o['help'] = true;
                    break;
                case '--kernel':
                    $o['kernel'][] = rtrim($need(), '/');
                    break;
                case '--skills':
                    $o['skills'] = $need();
                    break;
                case '--json':
                    $o['json'] = true;
                    break;
                case '--no-cache':
                    $o['nocache'] = true;
                    break;
                case '--warnings':
                    $o['warnings'] = true;
                    break;
                case '--jobs':
                    $o['jobs'] = max(1, (int)$need());
                    break;
                case '--guess':
                    $g = strtolower($need());
                    if ($g !== 'error' && $g !== 'warn') {
                        throw new InvalidArgumentException('--guess must be error or warn');
                    }
                    $o['guess'] = $g === 'error' ? 'ERROR' : 'WARN';
                    break;
                case '--index-worker':
                    $o['worker'] = $need();
                    break;
                case '--index-out':
                    $o['out'] = $need();
                    break;
                case '--index-root':
                    $o['iroot'] = $need();
                    break;
                default:
                    if (str_starts_with($a, '-')) {
                        throw new InvalidArgumentException("unknown option $a");
                    }
                    $o['names'][] = rtrim($a, '/');
            }
        }
        return $o;
    }

    private static function cpus(): int
    {
        $n = (int)getenv('NUMBER_OF_PROCESSORS');
        if ($n <= 0 && is_readable('/proc/cpuinfo')) {
            $n = substr_count((string)file_get_contents('/proc/cpuinfo'), "\nprocessor") + 1;
        }
        if ($n <= 0 && function_exists('shell_exec')) {
            $n = (int)trim((string)@shell_exec('sysctl -n hw.ncpu 2>/dev/null || nproc 2>/dev/null'));
        }
        return max(1, min(16, $n > 0 ? $n : 4));
    }

    private function rel(string $path): string
    {
        return str_starts_with($path, $this->root . '/') ? substr($path, strlen($this->root) + 1) : $path;
    }

    private function add(string $skill, string $file, int $line, string $level, string $cat, string $msg): void
    {
        $this->issues[] = ['skill' => $skill, 'file' => $this->rel($file), 'line' => $line, 'level' => $level, 'cat' => $cat, 'msg' => $msg];
    }

    private function collectDeclared(string $code, array &$short, array &$fq): void
    {
        try {
            [$t] = VsTok::sig(VsPrep::raw($code)['code']);
        } catch (Throwable) {
            return;
        }
        $ns = '';
        foreach ($t as $i => $tok) {
            if ($tok->id === T_NAMESPACE && isset($t[$i + 1]) && ($t[$i + 1]->id === T_STRING || $t[$i + 1]->id === T_NAME_QUALIFIED)) {
                $ns = $t[$i + 1]->text;
            }
            if (in_array($tok->id, [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true) && ($t[$i - 1]->id ?? 0) !== T_DOUBLE_COLON) {
                $nt = $t[$i + 1] ?? null;
                if (VsTok::isIdent($nt) && $nt->id !== T_EXTENDS && $nt->id !== T_IMPLEMENTS) {
                    $short[strtolower($nt->text)] = true;
                    $fq[strtolower(($ns === '' ? '' : $ns . '\\') . $nt->text)] = true;
                }
            }
        }
    }

    // --- skill files -------------------------------------------------------------------------

    private function checkSkillFiles(string $skill, string $dir, array $docs, array $allSkills): void
    {
        $skillMd = $dir . '/SKILL.md';
        if (!isset($docs[$skillMd])) {
            $this->add($skill, $skillMd, 1, 'ERROR', 'frontmatter', 'SKILL.md is missing');
        }
        $rules = [];
        foreach (array_keys($docs) as $f) {
            if (str_starts_with($f, $dir . '/rules/')) {
                $rules[] = $f;
            }
        }
        foreach ($docs as $file => $doc) {
            $this->stats['files']++;
            $content = $doc['content'];
            $md = $doc['md'];
            $size = strlen($content);
            if ($file === $skillMd) {
                $this->checkFrontmatter($skill, $file, $content);
                $budget = $rules === [] ? VS_BUDGET_SKILL : VS_BUDGET_ROUTER;
                if ($size > $budget) {
                    $this->add($skill, $file, 1, 'WARN', 'size', sprintf('SKILL.md is %.1f KB, budget %.1f KB%s', $size / 1024, $budget / 1024,
                        $rules === [] ? '' : ' (router: has rules/)'));
                }
                $this->checkLayout($skill, $file, $content, $md);
            } elseif (in_array($file, $rules, true) && $size > VS_BUDGET_RULE) {
                $this->add($skill, $file, 1, 'WARN', 'size', sprintf('%s is %.1f KB, budget %.1f KB', basename($file), $size / 1024, VS_BUDGET_RULE / 1024));
            }
            if ($md['unclosed'] !== null) {
                $this->add($skill, $file, $md['unclosed'], 'ERROR', 'content', 'Unclosed code fence');
            }
            $lines = preg_split('/\r?\n/', $content) ?: [];
            foreach ($lines as $k => $line) {
                if (str_contains($line, 'AGENTS.md')) {
                    $this->add($skill, $file, $k + 1, 'ERROR', 'content', 'Reference to AGENTS.md (not installed with skills; use `bitrix-framework` or a skill name)');
                }
            }
            foreach ($md['text'] as $no => $line) {
                $this->checkLinks($skill, $dir, $file, $no, $line, $allSkills);
            }
            foreach ($md['spans'] as [$no, $code]) {
                if (preg_match('/^(?:bitrix|bitrix24)-[a-z0-9-]+$/', $code) === 1 && !isset(VS_NOT_SKILLS[$code])
                    && !in_array($code, $allSkills, true)
                ) {
                    $this->add($skill, $file, $no, 'ERROR', 'link', sprintf('Skill `%s` does not exist in skills/', $code));
                }
            }
        }
        if (isset($docs[$skillMd])) {
            $main = $docs[$skillMd]['content'];
            foreach ($rules as $r) {
                $relRule = substr($r, strlen($dir) + 1);
                if (!str_contains($main, $relRule)) {
                    $this->add($skill, $r, 1, 'WARN', 'link', sprintf('%s is not referenced from SKILL.md', $relRule));
                }
            }
        }
    }

    private function checkFrontmatter(string $skill, string $file, string $content): void
    {
        $fm = VsMd::frontmatter($content);
        if ($fm === null) {
            $this->add($skill, $file, 1, 'ERROR', 'frontmatter', 'No YAML frontmatter (--- name/description ---)');
            return;
        }
        $f = $fm['fields'];
        $name = $f['name'] ?? '';
        if ($name === '') {
            $this->add($skill, $file, 1, 'ERROR', 'frontmatter', 'Frontmatter has no `name`');
        } elseif ($name !== $skill) {
            $this->add($skill, $file, $fm['lines']['name'], 'ERROR', 'frontmatter', sprintf('name `%s` does not match directory `%s`', $name, $skill));
        }
        // YAML traps: the skills CLI parses frontmatter with a real YAML parser and skips the skill on error.
        foreach (['name', 'description'] as $key) {
            $raw = $fm['raw'][$key] ?? '';
            if ($raw === '' || in_array($raw, ['>', '>-', '>+', '|', '|-', '|+'], true)) {
                continue;
            }
            $at = $fm['lines'][$key] ?? 1;
            if ($raw[0] === '"' && str_contains($raw, '\\')) {
                $this->add($skill, $file, $at, 'ERROR', 'frontmatter', sprintf('`%s` is double-quoted and contains `\\` (YAML escape); use single quotes', $key));
            } elseif ($raw[0] !== '"' && $raw[0] !== "'" && (preg_match('/: | #/', $raw) || preg_match('/^[&*!|>%@`{\[\]]/', $raw))) {
                $this->add($skill, $file, $at, 'ERROR', 'frontmatter', sprintf('`%s` is unquoted and contains a YAML special sequence (`: `, ` #` or a leading indicator): the skill fails to install or gets truncated; wrap it in single quotes', $key));
            }
        }
        $desc = $f['description'] ?? '';
        $line = $fm['lines']['description'] ?? 1;
        if (trim($desc) === '') {
            $this->add($skill, $file, $line, 'ERROR', 'frontmatter', 'Frontmatter has no `description`');
            return;
        }
        $len = mb_strlen($desc);
        if ($len > VS_DESCRIPTION_MAX) {
            $this->add($skill, $file, $line, 'ERROR', 'frontmatter', sprintf('description is %d chars, max %d', $len, VS_DESCRIPTION_MAX));
        }
        if (preg_match('/\b(Covers|Applied when|Key terms)\b/', $desc, $m)) {
            $this->add($skill, $file, $line, 'WARN', 'frontmatter', sprintf('description uses "%s"; write "what it is. Use when …"', $m[1]));
        }
    }

    private function checkLayout(string $skill, string $file, string $content, array $md): void
    {
        $lines = preg_split('/\r?\n/', $content) ?: [];
        $fmEnd = VsMd::frontmatter($content)['end'] ?? 0;
        $found = false;
        foreach (array_slice($lines, $fmEnd, 20) as $l) {
            if (preg_match('/^\**(?:Baseline|Scope):?\**.*Verified/i', trim($l)) === 1) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $this->add($skill, $file, $fmEnd + 1, 'WARN', 'content', 'No "Baseline: main 23.0+ · Verified: main X.Y.Z" (or "Scope: … · Verified: …") line after the title');
        }
        $checklistAt = null;
        $items = 0;
        foreach ($md['text'] as $no => $l) {
            if (preg_match('/^#{1,6}\s+(How to use|.*Progressive disclosure)/i', $l, $m) || preg_match('/^Progressive disclosure\b/i', $l, $m)) {
                $this->add($skill, $file, $no, 'WARN', 'content', sprintf('"%s" section: the router table already says what to open', trim($m[1] ?? $m[0])));
            }
            if (preg_match('/^#{1,6}\s/', $l)) {
                if ($checklistAt !== null && $items > VS_CHECKLIST_MAX) {
                    break;
                }
                $checklistAt = preg_match('/^#{1,6}\s+.*checklist/i', $l) === 1 ? $no : null;
                $items = 0;
                continue;
            }
            if ($checklistAt !== null && preg_match('/^\s{0,3}(?:[-*+]|\d+\.)\s/', $l)) {
                $items++;
            }
        }
        if ($checklistAt !== null && $items > VS_CHECKLIST_MAX) {
            $this->add($skill, $file, $checklistAt, 'WARN', 'content', sprintf('Checklist has %d items, max %d', $items, VS_CHECKLIST_MAX));
        }
    }

    private function checkLinks(string $skill, string $dir, string $file, int $no, string $line, array $allSkills): void
    {
        $skillsDir = dirname($dir);
        if (preg_match_all('~(?<![\w./-])(?:((?:bitrix|bitrix24)-[a-z0-9-]+)/)?rules/([A-Za-z0-9_\-/]+\.md)~', $line, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $m) {
                $target = $m[1] !== '' ? $m[1] : $skill;
                if ($m[1] !== '' && !in_array($m[1], $allSkills, true)) {
                    $this->add($skill, $file, $no, 'ERROR', 'link', sprintf('Link to rules of unknown skill `%s`', $m[1]));
                    continue;
                }
                if (!is_file($skillsDir . '/' . $target . '/rules/' . $m[2]) && !$this->ruleOfMentionedSkill($skillsDir, $line, $m[2])) {
                    $this->add($skill, $file, $no, 'ERROR', 'link', sprintf('`%s` does not exist', ($m[1] !== '' ? $m[1] . '/' : '') . 'rules/' . $m[2]));
                }
            }
        }
        if (preg_match_all('~\]\(([^)\s#]+\.md)(?:#[^)]*)?\)~', $line, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $m) {
                $target = $m[1];
                if (preg_match('~^[a-z]+:~i', $target) || str_starts_with($target, '/')) {
                    continue;
                }
                if (preg_match('~^rules/~', $target) && is_file($dir . '/' . $target)) {
                    continue;
                }
                if (!is_file(dirname($file) . '/' . $target)) {
                    $this->add($skill, $file, $no, 'ERROR', 'link', sprintf('Link target `%s` does not exist', $target));
                }
            }
        }
    }

    /** "skill `bitrix-controllers` → `rules/filters.md`": the rules file belongs to a skill named on the same line. */
    private function ruleOfMentionedSkill(string $skillsDir, string $line, string $rule): bool
    {
        preg_match_all('/(?<![\w-])((?:bitrix|bitrix24)-[a-z0-9-]+)/', $line, $mm);
        foreach (array_unique($mm[1]) as $s) {
            if (is_file($skillsDir . '/' . $s . '/rules/' . $rule)) {
                return true;
            }
        }
        return false;
    }

    // --- php blocks --------------------------------------------------------------------------

    private function lintBlocks(array $selected, array $docs, int $jobs): void
    {
        $blocks = [];
        foreach ($selected as $skill) {
            foreach ($docs[$skill] ?? [] as $file => $doc) {
                foreach ($doc['md']['blocks'] as $b) {
                    if ($b['lang'] !== 'php') {
                        continue;
                    }
                    $prep = VsPrep::raw($b['code']);
                    $blocks[] = ['skill' => $skill, 'file' => $file, 'b' => $b, 'prep' => $prep];
                }
            }
        }
        $this->stats['blocks'] = count($blocks);
        $jobsRaw = [];
        foreach ($blocks as $n => $bl) {
            $jobsRaw[$n . ':raw'] = $bl['prep']['code'];
        }
        $res = VsLint::run($jobsRaw, $jobs, $this->tmp);
        $retry = [];
        foreach ($blocks as $n => $bl) {
            if (!$res[$n . ':raw']['ok'] && $bl['prep']['mode'] !== 'template') {
                foreach (['class', 'function', 'array'] as $kind) {
                    $retry[$n . ':' . $kind] = VsPrep::wrap($bl['prep']['code'], $kind);
                }
            }
        }
        if ($retry !== []) {
            $res += VsLint::run($retry, $jobs, $this->tmp);
        }
        foreach ($blocks as $n => $bl) {
            $b = $bl['b'];
            $variants = ['raw', 'class', 'function', 'array'];
            $pass = null;
            $worst = null;
            foreach ($variants as $v) {
                $r = $res[$n . ':' . $v] ?? null;
                if ($r === null) {
                    continue;
                }
                if ($r['ok']) {
                    $pass = $r;
                    break;
                }
                if ($worst === null || $r['err'][0] > $worst['err'][0]) {
                    $worst = $r;
                }
            }
            $map = fn (int $l): int => max($b['line'], min($b['end'] - 1, $b['line'] + $l - 1));
            if ($pass !== null) {
                foreach ($pass['dep'] as [$l, $msg]) {
                    $this->add($bl['skill'], $bl['file'], $map($l), 'WARN', 'lint', 'php -l: ' . $msg);
                }
                continue;
            }
            if ($worst !== null) {
                $this->add($bl['skill'], $bl['file'], $map($worst['err'][0]), 'ERROR', 'lint', 'php -l: ' . $worst['err'][1]
                    . ($bl['prep']['mode'] === 'template' ? '' : ' (also fails wrapped as class/function/array)'));
            }
        }
    }

    private function checkSymbols(VsCodeChecker $checker, string $skill, string $file, array $md): void
    {
        foreach ($md['blocks'] as $b) {
            if ($b['lang'] !== 'php') {
                continue;
            }
            foreach ($checker->check(VsPrep::raw($b['code'])['code'], false) as $is) {
                $line = min($b['end'] - 1, $b['line'] + $is['line'] - 1);
                $this->add($skill, $file, $line, $is['level'], 'symbol', $is['msg']);
            }
        }
        foreach ($md['spans'] as [$no, $code]) {
            if (!str_contains($code, '::') && !str_contains($code, '\\') && !str_contains($code, '#[')) {
                continue;
            }
            if (preg_match('~^[a-z]+://|^/|\s-{1,2}[a-z]~i', $code) === 1) {
                continue;
            }
            $this->stats['spans']++;
            $negative = preg_match(VS_NEGATIVE, str_replace('*', '', $md['text'][$no] ?? '')) === 1;
            foreach ($checker->check('<?php ' . $code, true) as $is) {
                if ($negative && $is['level'] === 'ERROR') {
                    $this->add($skill, $file, $no, 'WARN', 'symbol', $is['msg'] . ' [inline, negative context]');
                    continue;
                }
                $this->add($skill, $file, $no, $is['level'], 'symbol', $is['msg'] . ' [inline]');
            }
        }
    }

    // --- output ------------------------------------------------------------------------------

    private function sorted(): array
    {
        $issues = $this->issues;
        usort($issues, function (array $a, array $b): int {
            $fa = str_ends_with($a['file'], '/SKILL.md') ? '0' : '1';
            $fb = str_ends_with($b['file'], '/SKILL.md') ? '0' : '1';
            return [$a['skill'], $fa . $a['file'], $a['line'], $a['level'], $a['msg']]
                <=> [$b['skill'], $fb . $b['file'], $b['line'], $b['level'], $b['msg']];
        });
        return $issues;
    }

    private function counts(): array
    {
        $c = ['errors' => 0, 'warnings' => 0, 'by_cat' => []];
        foreach ($this->issues as $i) {
            if ($i['level'] === 'ERROR') {
                $c['errors']++;
                $c['by_cat'][$i['cat']] = ($c['by_cat'][$i['cat']] ?? 0) + 1;
            } else {
                $c['warnings']++;
            }
        }
        return $c;
    }

    private function printText(bool $warnings, array $meta): void
    {
        $skill = null;
        $file = null;
        foreach ($this->sorted() as $i) {
            if ($i['level'] === 'WARN' && !$warnings) {
                continue;
            }
            if ($i['skill'] !== $skill) {
                $skill = $i['skill'];
                $file = null;
                echo "\n== " . $skill . "\n";
            }
            if ($i['file'] !== $file) {
                $file = $i['file'];
                echo '-- ' . $file . "\n";
            }
            printf("%-5s %s:%d  %s\n", $i['level'], $i['file'], $i['line'], $i['msg']);
        }
        $c = $this->counts();
        $cats = ['frontmatter', 'lint', 'symbol', 'link', 'content', 'size'];
        $parts = [];
        foreach ($cats as $cat) {
            $parts[] = $cat . ' ' . ($c['by_cat'][$cat] ?? 0);
        }
        $cached = array_filter($meta['index'], fn ($x) => $x['cached']);
        echo "\nSummary\n";
        printf("  Skills %d, files %d, php blocks %d, inline code spans checked %d\n",
            $this->stats['skills'], $this->stats['files'], $this->stats['blocks'], $this->stats['spans']);
        $k = $this->stats['checks'] ?? [];
        printf("  Resolved against the index: %d imports, %d class refs, %d static calls, %d constants, %d inferred instance calls, %d named args\n",
            $k['import'] ?? 0, $k['class'] ?? 0, $k['static'] ?? 0, $k['const'] ?? 0, $k['instance'] ?? 0, $k['named'] ?? 0);
        printf("  ERROR %d (%s)\n", $c['errors'], implode(', ', $parts));
        printf("  WARN  %d%s\n", $c['warnings'], $warnings ? '' : ' (use --warnings to list)');
        printf("  Kernel index: %d classes from %d dir(s)%s; %.1fs index, %.1fs total\n",
            $meta['classes'], count($meta['index']), count($cached) === count($meta['index']) ? ' (cached)' : '',
            $meta['index_seconds'], $meta['seconds']);
    }

    private function printJson(array $meta): void
    {
        $c = $this->counts();
        echo json_encode([
            'summary' => $this->stats + ['errors' => $c['errors'], 'warnings' => $c['warnings'], 'errors_by_category' => $c['by_cat']] + $meta,
            'issues' => $this->sorted(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit((new VsApp())->run($argv));
}
