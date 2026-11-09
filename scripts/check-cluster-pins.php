<?php

declare(strict_types=1);

/**
 * Verify cluster package pins and Packagist gap guard for phpunit/phpunit.
 */

const INDEX_COMMIT = '8d62da008404c9bdbbd7f3f86da9054d7ea9c48a';

/** @var array<string, array{alias: string, reference: string}> */
$expected = array(
);

$forbidden_org_refs = array(
    'a37d66fabf01af0eedabffe271b70dbe71723001',
    '698cbbc1e75a1ef1968f9cbfec89be056b288d0f',
);

$installedPhpPath = dirname(__DIR__) . '/vendor/composer/installed.php';
$installedJsonPath = dirname(__DIR__) . '/vendor/composer/installed.json';

if (!is_readable($installedPhpPath)) {
    fwrite(STDERR, "check-cluster-pins: vendor/composer/installed.php not found; run composer update first.\n");
    exit(1);
}
if (!is_readable($installedJsonPath)) {
    fwrite(STDERR, "check-cluster-pins: vendor/composer/installed.json not found; run composer update first.\n");
    exit(1);
}

/** @var array{versions?: array<string, array<string, mixed>>} $installedPhp */
$installedPhp = require $installedPhpPath;
$phpVersions = $installedPhp['versions'] ?? array();

$jsonRaw = file_get_contents($installedJsonPath);
if ($jsonRaw === false) {
    fwrite(STDERR, "check-cluster-pins: could not read installed.json.\n");
    exit(1);
}

/** @var array{packages?: list<array<string, mixed>>, 'packages-dev'?: list<array<string, mixed>>} $jsonData */
$jsonData = json_decode($jsonRaw, true);
if (!is_array($jsonData)) {
    fwrite(STDERR, "check-cluster-pins: invalid installed.json.\n");
    exit(1);
}

/** @var array<string, array{reference: string, aliases: list<string>}> $jsonByName */
$jsonByName = array();
foreach (array_merge($jsonData['packages'] ?? array(), $jsonData['packages-dev'] ?? array()) as $pkg) {
    if (!isset($pkg['name'], $pkg['source']['reference']) || !is_string($pkg['name'])) {
        continue;
    }
    $jsonByName[$pkg['name']] = array(
        'reference' => strtolower((string) $pkg['source']['reference']),
        'aliases' => isset($pkg['aliases']) && is_array($pkg['aliases']) ? $pkg['aliases'] : array(),
    );
}

function check_php_pin(string $name, string $wantRef, string $wantAlias, array $phpSide): bool
{
    if (!isset($phpSide[$name])) {
        return false;
    }
    $pkg = $phpSide[$name];
    if (!in_array($wantAlias, $pkg['aliases'], true)) {
        return false;
    }

    return hash_equals(strtolower($wantRef), strtolower($pkg['reference']));
}

function check_json_pin(string $name, string $wantRef, array $jsonSide): bool
{
    if (!isset($jsonSide[$name])) {
        return false;
    }

    return hash_equals(strtolower($wantRef), $jsonSide[$name]['reference']);
}

/** @var array<string, array{reference: string, aliases: list<string>}> $phpSide */
$phpSide = array();
foreach ($phpVersions as $name => $meta) {
    if ($name === '' || $name[0] === '/') {
        continue;
    }
    $phpSide[$name] = array(
        'reference' => isset($meta['reference']) ? strtolower((string) $meta['reference']) : '',
        'aliases' => isset($meta['aliases']) && is_array($meta['aliases']) ? $meta['aliases'] : array(),
    );
}

$failed = 0;
foreach ($expected as $name => $want) {
    if (!check_php_pin($name, $want['reference'], $want['alias'], $phpSide)) {
        fwrite(STDERR, "check-cluster-pins [installed.php]: {$name} mismatch.\n");
        $failed++;
    }
    if (!check_json_pin($name, $want['reference'], $jsonByName)) {
        fwrite(STDERR, "check-cluster-pins [installed.json]: {$name} mismatch.\n");
        $failed++;
    }
}

if (!isset($phpVersions['phpunit/phpunit'])) {
    fwrite(STDERR, "check-cluster-pins: phpunit/phpunit gap guard failed: package not installed.\n");
    $failed++;
} else {
    $phpUnit = $phpVersions['phpunit/phpunit'];
    $version = isset($phpUnit['pretty_version']) ? (string) $phpUnit['pretty_version'] : '';
    if ($version === '' || !preg_match('/^8\.5\.(5[2-9]|[6-9][0-9])$/', $version)) {
        fwrite(STDERR, "check-cluster-pins: phpunit/phpunit must be Packagist ^8.5.52 (got {$version}).\n");
        $failed++;
    }
    $ref = isset($phpUnit['reference']) ? strtolower((string) $phpUnit['reference']) : '';
    if (in_array($ref, $forbidden_org_refs, true)) {
        fwrite(STDERR, "check-cluster-pins: phpunit/phpunit must not resolve to Training-Datasmith index tip.\n");
        $failed++;
    }
    $url = '';
    if (isset($jsonByName['phpunit/phpunit'])) {
        foreach (array_merge($jsonData['packages-dev'] ?? array(), $jsonData['packages'] ?? array()) as $pkg) {
            if (($pkg['name'] ?? '') === 'phpunit/phpunit') {
                $url = (string) ($pkg['source']['url'] ?? '');
                break;
            }
        }
    }
    if ($url !== '' && stripos($url, 'Training-Datasmith/phpunit') !== false) {
        fwrite(STDERR, "check-cluster-pins: phpunit/phpunit source must be Packagist/sebastianbergmann, not org fork.\n");
        $failed++;
    }
    if ($url !== '' && stripos($url, 'sebastianbergmann/phpunit') === false) {
        fwrite(STDERR, "check-cluster-pins: phpunit/phpunit unexpected source URL.\n");
        $failed++;
    }
}

// In-memory wrong-SHA demos (never write vendor/).

$demoExpected = array(
    'demo/pin' => array(
        'alias' => '1.2.3',
        'reference' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
    ),
);

$demoPhp = $phpSide;
$demoPhp['demo/pin'] = array(
    'reference' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
    'aliases' => array('1.2.3'),
);
$demoJson = $jsonByName;
$demoJson['demo/pin'] = array(
    'reference' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
    'aliases' => array('1.2.3'),
);

$demoOk = true;
foreach ($demoExpected as $name => $want) {
    if (!check_php_pin($name, $want['reference'], $want['alias'], $demoPhp) || !check_json_pin($name, $want['reference'], $demoJson)) {
        $demoOk = false;
    }
}
if (!$demoOk) {
    fwrite(STDERR, "check-cluster-pins: demo control failed.\n");
    $failed++;
}

$wrongPhp = $demoPhp;
$wrongPhp['demo/pin']['reference'] = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
$phpShouldFail = !check_php_pin('demo/pin', $demoExpected['demo/pin']['reference'], $demoExpected['demo/pin']['alias'], $wrongPhp);
$jsonShouldPass = check_json_pin('demo/pin', $demoExpected['demo/pin']['reference'], $demoJson);
if (!$phpShouldFail || !$jsonShouldPass) {
    fwrite(STDERR, "check-cluster-pins: installed.php wrong-SHA demo invalid.\n");
    $failed++;
}

$wrongJson = $demoJson;
$wrongJson['demo/pin']['reference'] = 'cccccccccccccccccccccccccccccccccccccccc';
$jsonShouldFail = !check_json_pin('demo/pin', $demoExpected['demo/pin']['reference'], $wrongJson);
$phpShouldPass = check_php_pin('demo/pin', $demoExpected['demo/pin']['reference'], $demoExpected['demo/pin']['alias'], $demoPhp);
if (!$jsonShouldFail || !$phpShouldPass) {
    fwrite(STDERR, "check-cluster-pins: installed.json wrong-SHA demo invalid.\n");
    $failed++;
}

if ($failed > 0) {
    exit(1);
}

fwrite(STDOUT, 'check-cluster-pins: all cluster packages match expected SHAs and aliases.' . "\n");
exit(0);
