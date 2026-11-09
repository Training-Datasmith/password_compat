#!/usr/bin/env bash
set -euo pipefail

ROOT="/src"
if [[ -d /app ]]; then
    ROOT="/app"
fi

PHP_BIN="$(command -v php)"
if [[ -x /opt/php-5.3.29/bin/php ]]; then
    PHP_BIN="/opt/php-5.3.29/bin/php"
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
"${PHP_BIN}" -v | tee "${php_out}"
if ! assert_stripped_line '^PHP 5\.3\.29 \(cli\)' "${php_out}"; then
    echo 'floor-lint: missing PHP 5.3.29 version line' >&2
    rm -f "${php_out}"
    exit 1
fi
rm -f "${php_out}"

lint_files=()
while IFS= read -r -d '' path; do
    lint_files+=("${path}")
done < <(find "${ROOT}/lib" "${ROOT}/examples" -type f -name '*.php' -print0 | sort -z)
lint_files+=("${ROOT}/version-test.php")

if [[ ${#lint_files[@]} -eq 0 ]]; then
    echo 'floor-lint: no PHP files to lint' >&2
    exit 1
fi

for file in "${lint_files[@]}"; do
  rel="${file#"${ROOT}/"}"
  out="$(mktemp)"
  "${PHP_BIN}" -l "${file}" >"${out}" 2>&1
  cat "${out}"
  if ! assert_stripped_line "^No syntax errors detected in .*/${rel//\//\\/}\$" "${out}"; then
    echo "floor-lint: missing syntax success line for ${rel}" >&2
    rm -f "${out}"
    exit 1
  fi
  rm -f "${out}"
done

echo 'floor-lint: all syntax checks passed.'
