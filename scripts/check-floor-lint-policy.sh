#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DF="${ROOT}/docker/floor-lint/Dockerfile"

if [[ ! -f "${DF}" ]]; then
    echo 'check-floor-lint-policy: missing docker/floor-lint/Dockerfile' >&2
    exit 1
fi

fail=0
patterns=(
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
    if grep -Ei "${pat}" "${DF}" >/dev/null; then
        echo "check-floor-lint-policy: forbidden pattern in floor-lint Dockerfile: ${pat}" >&2
        fail=1
    fi
done

if grep -E 'http://' "${DF}" >/dev/null; then
    echo 'check-floor-lint-policy: floor-lint Dockerfile must not use http:// apt sources' >&2
    fail=1
fi

if [[ ${fail} -ne 0 ]]; then
    exit 1
fi

echo 'check-floor-lint-policy: floor-lint Dockerfile passes verification policy.'
