<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$scanPaths = array(
    $root . '/lib',
    $root . '/test',
    $root . '/examples',
    $root . '/README.md',
    $root . '/CHANGELOG.md',
    $root . '/ARCHITECTURE.md',
);

$oldNames = array(
    'PasswordCompat',
    'PasswordGetInfoTest',
    'PasswordHashTest',
    'PasswordNeedsRehashTest',
    'PasswordVerifyTest',
    '$resultLength',
    'testFuncExists',
    'testStringLength',
    'testHash',
    'testKnownSalt',
    'testRawSalt',
    'testNullBehavior',
    'testIntegerBehavior',
    'testInvalidAlgo',
    'testInvalidAlgo2',
    'testInvalidPassword',
    'testInvalidSalt',
    'testInvalidBcryptCostLow',
    'testInvalidBcryptCostHigh',
    'testInvalidBcryptCostInvalid',
    'testInvalidBcryptSaltShort',
    'testFailedType',
    'testSaltOnly',
    'testValidPassword',
    'testInValidHash',
    'provideInfo',
    'provideCases',
    'testInfo',
    'testCases',
    'PHPUnit_Framework_TestCase',
    'PHPUnit_Framework_Error',
);

$allowlistPath = __DIR__ . '/rename-allowlist.txt';
/** @var list<string> $allowlist */
$allowlist = array();
if (is_readable($allowlistPath)) {
    $lines = file($allowlistPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '' && $line[0] !== '#') {
                $allowlist[] = $line;
            }
        }
    }
}

$failures = 0;

foreach ($scanPaths as $path) {
    if (is_file($path)) {
        $content = file_get_contents($path);
        if ($content === false) {
            continue;
        }
        check_old_names($path, $content, $oldNames, $failures);
        if (substr($path, -4) === '.php') {
            check_camel_case_php($path, $content, $allowlist, $failures);
        }
        continue;
    }
    if (!is_dir($path)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }
        $file = $fileInfo->getPathname();
        if (substr($file, -4) !== '.php' && substr($file, -3) !== '.md') {
            continue;
        }
        $content = file_get_contents($file);
        if ($content === false) {
            continue;
        }
        check_old_names($file, $content, $oldNames, $failures);
        if (substr($file, -4) === '.php') {
            check_camel_case_php($file, $content, $allowlist, $failures);
        }
    }
}

if (!run_allowlist_site_demo($allowlist)) {
    $failures++;
}

$baselinePath = __DIR__ . '/phpunit-baseline.txt';
if (is_readable($baselinePath)) {
    $requiredMethods = array(
        'test_func_exists', 'test_string_length', 'test_hash', 'test_known_salt',
        'test_raw_salt', 'test_null_behavior', 'test_integer_behavior',
        'test_invalid_algo', 'test_invalid_algo2', 'test_invalid_password',
        'test_invalid_salt', 'test_invalid_bcrypt_cost_low', 'test_invalid_bcrypt_cost_high',
        'test_invalid_bcrypt_cost_invalid', 'test_invalid_bcrypt_salt_short',
        'test_failed_type', 'test_salt_only', 'test_invalid_password', 'test_valid_password',
        'test_in_valid_hash', 'test_info', 'test_cases',
    );
    foreach ($requiredMethods as $method) {
        $found = false;
        foreach (glob($root . '/test/Unit/*_Test.php') as $testFile) {
            $src = file_get_contents($testFile);
            if ($src !== false && preg_match('/function\s+' . preg_quote($method, '/') . '\s*\(/', $src)) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            fwrite(STDERR, "check-rename: baseline method missing: {$method}\n");
            $failures++;
        }
    }
}

if ($failures > 0) {
    exit(1);
}

fwrite(STDOUT, "check-rename: no old names or unlisted camelCase identifiers remain.\n");
exit(0);

/**
 * @param list<string> $oldNames
 */
function check_old_names(string $file, string $content, array $oldNames, int &$failures): void
{
    foreach ($oldNames as $name) {
        if (strpos($content, $name) !== false) {
            fwrite(STDERR, "check-rename: old name {$name} in {$file}\n");
            $failures++;
        }
    }
}

/**
 * @param list<string> $allowlist
 */
function check_camel_case_php(string $file, string $content, array $allowlist, int &$failures): void
{
    $tokens = token_get_all($content);
    $class = '';
    $method = '';
    $skipUntil = -1;
    $i = 0;
    $count = count($tokens);
    while ($i < $count) {
        if ($i <= $skipUntil) {
            $i++;
            continue;
        }
        $tok = $tokens[$i];
        if (is_array($tok)) {
            if ($tok[0] === T_CLASS) {
                $i++;
                while ($i < $count && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
                    $i++;
                }
                if ($i < $count && is_array($tokens[$i]) && $tokens[$i][0] === T_STRING) {
                    $class = $tokens[$i][1];
                }
            } elseif ($tok[0] === T_FUNCTION) {
                $i++;
                while ($i < $count && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
                    $i++;
                }
                if ($i < $count && is_array($tokens[$i]) && $tokens[$i][0] === T_STRING) {
                    $method = $tokens[$i][1];
                }
            } elseif ($tok[0] === T_EXTENDS || $tok[0] === T_USE || $tok[0] === T_IMPLEMENTS) {
                $skipUntil = find_statement_end($tokens, $i);
            } elseif ($tok[0] === T_NEW || $tok[0] === T_CATCH) {
                $skipUntil = find_statement_end($tokens, $i);
            } elseif ($tok[0] === T_STRING) {
                $name = $tok[1];
                if (($i > 0 && $tokens[$i - 1] === '\\') || ($i + 1 < $count && $tokens[$i + 1] === '\\')) {
                    $i++;
                    continue;
                }
                if (preg_match('/[a-z][A-Z]/', $name)) {
                    $site = site_for_token($tokens, $i, $class, $method, $name);
                    if (!in_array($site, $allowlist, true)) {
                        fwrite(STDERR, "check-rename: unlisted camelCase {$name} at {$file}:{$tok[2]} (site {$site})\n");
                        $failures++;
                    }
                }
            } elseif ($tok[0] === T_VARIABLE) {
                $var = $tok[1];
                if (preg_match('/\$[a-z][A-Z]/', $var)) {
                    $site = $class . '::' . $method . '::' . $var;
                    if (!in_array($site, $allowlist, true)) {
                        fwrite(STDERR, "check-rename: camelCase variable {$var} at {$file}:{$tok[2]}\n");
                        $failures++;
                    }
                }
            }
        }
        $i++;
    }
}

/**
 * @param array<int, array{0:int,1:string,2:int}|string> $tokens
 */
function find_statement_end(array $tokens, int $start): int
{
    $depth = 0;
    for ($i = $start; $i < count($tokens); $i++) {
        $tok = $tokens[$i];
        if ($tok === '(') {
            $depth++;
        } elseif ($tok === ')') {
            $depth--;
        } elseif ($depth === 0 && ($tok === ';' || $tok === '{')) {
            return $i;
        }
    }

    return count($tokens) - 1;
}

/**
 * @param array<int, array{0:int,1:string,2:int}|string> $tokens
 */
function site_for_token(array $tokens, int $i, string $class, string $method, string $name): string
{
    if ($i > 0 && $tokens[$i - 1] === '->' && $class !== '' && $method !== '') {
        return $class . '::' . $method . '::' . $name;
    }
    if ($name === 'algoName') {
        return '::password_get_info::algoName';
    }

    return $class . '::' . $method . '::' . $name;
}

/**
 * @param list<string> $allowlist
 */
function run_allowlist_site_demo(array $allowlist): bool
{
    $allowedSite = 'Password_Verify_Test::test_valid_password::assertTrue';
    if (!in_array($allowedSite, $allowlist, true)) {
        fwrite(STDERR, "check-rename: allowlist missing {$allowedSite}\n");
        return false;
    }

    $good = <<<'PHP'
<?php
class Password_Verify_Test extends PHPUnit\Framework\TestCase {
    public function test_valid_password() {
        $this->assertTrue(true);
    }
}
PHP;

    $bad = <<<'PHP'
<?php
class Other_Test extends PHPUnit\Framework\TestCase {
    public function test_other() {
        $this->assertTrue(true);
    }
}
PHP;

    $failGood = 0;
    $failBad = 0;
    check_camel_case_php('demo-good.php', $good, $allowlist, $failGood);
    if ($failGood > 0) {
        fwrite(STDERR, "check-rename: allowlist demo good file unexpected failure\n");
        return false;
    }

    check_camel_case_php('demo-bad.php', $bad, $allowlist, $failBad);
    if ($failBad === 0) {
        fwrite(STDERR, "check-rename: allowlist demo must reject assertTrue outside listed site\n");
        return false;
    }

    fwrite(STDOUT, "check-rename: allowlist site demo passed.\n");
    return true;
}
