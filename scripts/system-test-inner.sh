#!/usr/bin/env bash
set -euo pipefail

export COMPOSER_ALLOW_SUPERUSER=1

fail_deprecation_output() {
    local label="$1"
    local file="$2"
    local stripped
    stripped="$(mktemp)"
    sed -E 's/\x1b\[[0-9;]*[[:alpha:]]//g' "${file}" >"${stripped}"
    if grep -E '(^|[[:space:]])(PHP[[:space:]]+)?(Warning|Deprecated|Notice):' "${stripped}" >/dev/null; then
        echo "${label}: output contains Warning, Deprecated, or Notice lines" >&2
        grep -E '(^|[[:space:]])(PHP[[:space:]]+)?(Warning|Deprecated|Notice):' "${stripped}" >&2 || true
        rm -f "${stripped}"
        exit 1
    fi
    rm -f "${stripped}"
}

php -v | tee /tmp/php-version.txt
if ! grep -E '^PHP 7\.2\.34 \(cli\)' /tmp/php-version.txt >/dev/null; then
    echo 'system-test: missing PHP 7.2.34 version line' >&2
    exit 1
fi

COMPOSER_URL="https://getcomposer.org/download/2.8.10/composer.phar"
COMPOSER_SHA256="28dbb6bd8bef31479c7985b774c130a8bda37dbe63c35b56f6cb6bc377427573"
curl -fsSL -o /tmp/composer.phar "${COMPOSER_URL}"
echo "${COMPOSER_SHA256}  /tmp/composer.phar" | sha256sum -c -

rm -rf vendor
composer_out="$(mktemp)"
set +e
php /tmp/composer.phar update --no-interaction --prefer-dist --no-progress >"${composer_out}" 2>&1
composer_status=$?
set -e
cat "${composer_out}"
fail_deprecation_output "composer update" "${composer_out}"
if [[ ${composer_status} -ne 0 ]]; then
    rm -f "${composer_out}"
    exit "${composer_status}"
fi
if ! grep -Fxq 'Generating autoload files' "${composer_out}"; then
    echo 'composer update: missing strict success line' >&2
    rm -f "${composer_out}"
    exit 1
fi
rm -f "${composer_out}"

php scripts/check-cluster-pins.php

php scripts/check-rename.php

audit_out="$(mktemp)"
set +e
php /tmp/composer.phar audit --no-interaction --abandoned=report >"${audit_out}" 2>&1
audit_status=$?
set -e
cat "${audit_out}"
fail_deprecation_output "composer audit" "${audit_out}"
if [[ ${audit_status} -ne 0 ]]; then
    exit "${audit_status}"
fi
if ! grep -Fxq 'No security vulnerability advisories found.' "${audit_out}"; then
    echo 'composer audit: missing exact success line' >&2
    exit 1
fi
rm -f "${audit_out}"

phpunit_out="$(mktemp)"
set +e
php -d error_reporting=32767 -d display_errors=0 vendor/bin/phpunit --colors=never --no-coverage >"${phpunit_out}" 2>&1
phpunit_status=$?
set -e
cat "${phpunit_out}"
fail_deprecation_output "phpunit" "${phpunit_out}"
if [[ ${phpunit_status} -ne 0 ]]; then
    exit "${phpunit_status}"
fi
if ! grep -E '^OK \([0-9]+ tests, [0-9]+ assertions\)$' "${phpunit_out}" >/dev/null; then
    echo 'phpunit: missing strict OK success line' >&2
    exit 1
fi
ok_line="$(grep -E '^OK \([0-9]+ tests, [0-9]+ assertions\)$' "${phpunit_out}" | tail -n 1)"
baseline_line="$(grep '^line=' scripts/phpunit-baseline.txt | cut -d= -f2-)"
if [[ "${ok_line}" != "${baseline_line}" ]]; then
    echo "phpunit: expected ${baseline_line}, got ${ok_line}" >&2
    exit 1
fi
rm -f "${phpunit_out}"

mutation_out="$(mktemp)"
set +e
php scripts/demo-mutation-baseline.php >"${mutation_out}" 2>&1
mutation_status=$?
set -e
cat "${mutation_out}"
fail_deprecation_output "demo-mutation" "${mutation_out}"
if [[ ${mutation_status} -ne 0 ]]; then
    exit "${mutation_status}"
fi
if ! grep -Fxq 'demo-mutation: baseline test fails when expectation is inverted.' "${mutation_out}" >/dev/null; then
    echo 'demo-mutation: missing success line' >&2
    exit 1
fi
rm -f "${mutation_out}"

echo 'system-test: all steps passed.'
