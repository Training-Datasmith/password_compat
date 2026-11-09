#!/usr/bin/env bash
set -euo pipefail

ROOT="/src"
if [[ -d /app ]]; then
    ROOT="/app"
fi

php -v | tee /tmp/php-floor-version.txt
if ! grep -E '^PHP 5\.3\.29 \(cli\)' /tmp/php-floor-version.txt >/dev/null; then
    echo 'floor-lint: missing PHP 5.3.29 version line' >&2
    exit 1
fi

for file in lib/password.php examples/basic_usage.php version-test.php; do
    out="$(mktemp)"
    php -l "${ROOT}/${file}" >"${out}" 2>&1
    cat "${out}"
    if ! grep -Eq "^No syntax errors detected in .*/${file}$" "${out}"; then
        echo "floor-lint: missing syntax success line for ${file}" >&2
        rm -f "${out}"
        exit 1
    fi
    rm -f "${out}"
done

echo 'floor-lint: all syntax checks passed.'
