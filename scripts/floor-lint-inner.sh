#!/usr/bin/env bash
set -euo pipefail

ROOT="/src"
if [[ -d /app ]]; then
    ROOT="/app"
fi

strip_ansi() {
    sed -E 's/\x1b\[[0-9;]*[[:alpha:]]//g' "$@"
}

assert_stripped_line() {
    local pattern="$1"
    local file="$2"
    local stripped
    stripped="$(mktemp)"
    strip_ansi "${file}" >"${stripped}"
    if [[ "${pattern}" == ^* ]]; then
        if ! grep -E "${pattern}" "${stripped}" >/dev/null; then
            rm -f "${stripped}"
            return 1
        fi
    else
        if ! grep -Fxq "${pattern}" "${stripped}"; then
            rm -f "${stripped}"
            return 1
        fi
    fi
    rm -f "${stripped}"
    return 0
}

php_out="$(mktemp)"
php -v | tee "${php_out}"
if ! assert_stripped_line '^PHP 5\.3\.29 \(cli\)' "${php_out}"; then
    echo 'floor-lint: missing PHP 5.3.29 version line' >&2
    rm -f "${php_out}"
    exit 1
fi
rm -f "${php_out}"

for file in lib/password.php examples/basic_usage.php version-test.php; do
    out="$(mktemp)"
    php -l "${ROOT}/${file}" >"${out}" 2>&1
    cat "${out}"
    if ! assert_stripped_line "^No syntax errors detected in .*/${file}\$" "${out}"; then
        echo "floor-lint: missing syntax success line for ${file}" >&2
        rm -f "${out}"
        exit 1
    fi
    rm -f "${out}"
done

echo 'floor-lint: all syntax checks passed.'
