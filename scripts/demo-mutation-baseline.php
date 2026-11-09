<?php

declare(strict_types=1);

/**
 * Prove baseline verify test can fail (inverted expectation).
 * Inverting password_verify() itself is not possible on PHP 7.2+ where natives load first.
 */

$root = dirname(__DIR__);
$testFile = $root . '/test/Unit/Password_Verify_Test.php';
$source = file_get_contents($testFile);
if ($source === false) {
    fwrite(STDERR, "demo-mutation: cannot read verify test.\n");
    exit(1);
}

if (function_exists('password_verify') && version_compare(PHP_VERSION, '5.5.0', '>=')) {
    fwrite(STDOUT, "demo-mutation: password_verify polyfill not active on PHP " . PHP_VERSION . "; cannot invert implementation in-process.\n");
}

$needle = "        \$this->assertTrue(password_verify('rasmuslerdorf', '\$2a\$07\$usesomesillystringfore2uDLvp1Ii2e./U9C8sBjqp8I90dH6hi'));";
$replacement = "        \$this->assertFalse(password_verify('rasmuslerdorf', '\$2a\$07\$usesomesillystringfore2uDLvp1Ii2e./U9C8sBjqp8I90dH6hi'));";

if (strpos($source, $needle) === false) {
    fwrite(STDERR, "demo-mutation: could not locate baseline assertion.\n");
    exit(1);
}

$mutated = str_replace($needle, $replacement, $source);

$phpunit = $root . '/vendor/bin/phpunit';
if (!is_executable($phpunit)) {
    fwrite(STDERR, "demo-mutation: run composer update before demo.\n");
    exit(1);
}

$tmpDir = sys_get_temp_dir() . '/password-compat-mutation-' . getmypid();
mkdir($tmpDir, 0700, true);
$tmpTest = $tmpDir . '/Password_Verify_Test.php';
file_put_contents($tmpTest, $mutated);

$cmd = escapeshellarg(PHP_BINARY)
    . ' -d error_reporting=32767 -d display_errors=0 '
    . escapeshellarg($phpunit)
    . ' --colors=never --no-coverage --bootstrap '
    . escapeshellarg($root . '/lib/password.php')
    . ' --filter test_valid_password '
    . escapeshellarg($tmpTest);

$output = array();
$exit = 0;
exec($cmd . ' 2>&1', $output, $exit);
$joined = implode("\n", $output);

@unlink($tmpTest);
@rmdir($tmpDir);

fwrite(STDOUT, $joined . "\n");

if ($exit === 0) {
    fwrite(STDERR, "demo-mutation: mutated baseline test must not pass.\n");
    exit(1);
}

if (strpos($joined, 'FAILURES!') === false && strpos($joined, 'Failed asserting') === false) {
    fwrite(STDERR, "demo-mutation: expected PHPUnit failure output.\n");
    exit(1);
}

fwrite(STDOUT, "demo-mutation: baseline test fails when expectation is inverted.\n");
exit(0);
