#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DF="${ROOT}/docker/floor-lint/Dockerfile"

check_floor_lint_dockerfile() {
    local file="$1"
    local label="$2"
    local fail=0

    if [[ ! -f "${file}" ]]; then
        echo "check-floor-lint-policy: missing Dockerfile (${label})" >&2
        return 1
    fi

    local patterns=(
        'Dir::Bin::gpg'
        'gpgv-allow-expired'
        'gpgv-wrapper'
        'KEYEXPIRED'
        'GOODSIG'
        'allow-unauthenticated'
        'AllowInsecureRepositories'
        'trusted=yes'
    )

    for pat in "${patterns[@]}"; do
        if grep -Ei "${pat}" "${file}" >/dev/null; then
            echo "check-floor-lint-policy: forbidden pattern in floor-lint Dockerfile (${label}): ${pat}" >&2
            fail=1
        fi
    done

    if grep -E 'http://' "${file}" >/dev/null; then
        echo "check-floor-lint-policy: floor-lint Dockerfile must not use http:// apt sources (${label})" >&2
        fail=1
    fi

    if grep -E 'Acquire::Check-Valid-Until[[:space:]]+"false"' "${file}" >/dev/null; then
        echo "check-floor-lint-policy: unscoped Acquire::Check-Valid-Until must not appear (${label})" >&2
        fail=1
    fi

    if [[ "${label}" == "committed" ]]; then
        if ! grep -Fq 'Acquire::Check-Valid-Until::"archive.debian.org" "false"' "${file}"; then
            echo 'check-floor-lint-policy: committed floor-lint Dockerfile must set archive.debian.org-scoped Check-Valid-Until' >&2
            fail=1
        fi
    fi

  return "${fail}"
}

if ! check_floor_lint_dockerfile "${DF}" "committed"; then
    exit 1
fi

planted="$(mktemp)"
trap 'rm -f "${planted}"' EXIT
cp "${DF}" "${planted}"
printf '%s\n' 'Acquire::Check-Valid-Until "false";' >> "${planted}"
if check_floor_lint_dockerfile "${planted}" "in-memory unscoped plant"; then
    echo 'check-floor-lint-policy: in-memory unscoped Check-Valid-Until plant must be rejected' >&2
    exit 1
fi

echo 'check-floor-lint-policy: floor-lint Dockerfile passes verification policy.'
