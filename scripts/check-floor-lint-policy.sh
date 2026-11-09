#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

collect_policy_files() {
    git -C "${ROOT}" ls-files -z \
        'Dockerfile' \
        'Dockerfile.*' \
        ':(glob)**/Dockerfile' \
        ':(glob)**/Dockerfile.*' \
        ':(glob)**/*.dockerfile' \
        'docker/**' \
        ':(glob)**/apt.conf' \
        ':(glob)**/apt.conf.d/**' \
        ':(glob)**/*apt.conf*'
}

check_policy_file() {
    local file="$1"
    local label="$2"
    local require_archive_scope="${3:-0}"
    local fail=0

    if [[ ! -f "${file}" ]]; then
        echo "check-floor-lint-policy: missing file (${label})" >&2
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
            echo "check-floor-lint-policy: forbidden pattern in ${file} (${label}): ${pat}" >&2
            fail=1
        fi
    done

    if grep -E 'http://' "${file}" >/dev/null; then
        echo "check-floor-lint-policy: ${file} must not use http:// apt sources (${label})" >&2
        fail=1
    fi

    if grep -E 'Acquire::Check-Valid-Until[[:space:]]+"false"' "${file}" >/dev/null; then
        echo "check-floor-lint-policy: unscoped Acquire::Check-Valid-Until must not appear in ${file} (${label})" >&2
        fail=1
    fi

    if [[ "${require_archive_scope}" == "1" ]]; then
        if ! grep -Fq 'Acquire::Check-Valid-Until::"archive.debian.org" "false"' "${file}"; then
            echo "check-floor-lint-policy: ${file} must set archive.debian.org-scoped Check-Valid-Until (${label})" >&2
            fail=1
        fi
    fi

    return "${fail}"
}

mapfile -d '' -t POLICY_FILES < <(collect_policy_files)

if [[ ${#POLICY_FILES[@]} -eq 0 ]]; then
    echo 'check-floor-lint-policy: no policy files found to scan' >&2
    exit 1
fi

for rel in "${POLICY_FILES[@]}"; do
    path="${ROOT}/${rel}"
    require_scope=0
    if [[ "${rel}" == docker/floor-lint/Dockerfile || "${rel}" == docker/system-test/Dockerfile ]]; then
        require_scope=1
    fi
    if ! check_policy_file "${path}" "committed:${rel}" "${require_scope}"; then
        exit 1
    fi
done

plant_unscoped() {
    local source_file="$1"
    local plant_label="$2"
    local planted
    planted="$(mktemp)"
    cp "${source_file}" "${planted}"
    printf '%s\n' 'Acquire::Check-Valid-Until "false";' >> "${planted}"
    if check_policy_file "${planted}" "${plant_label}" 0; then
        rm -f "${planted}"
        echo "check-floor-lint-policy: in-memory unscoped plant must be rejected (${plant_label})" >&2
        exit 1
    fi
    rm -f "${planted}"
}

plant_unscoped "${ROOT}/docker/floor-lint/Dockerfile" "floor-lint in-memory unscoped plant"
plant_unscoped "${ROOT}/docker/system-test/Dockerfile" "system-test in-memory unscoped plant"

echo 'check-floor-lint-policy: all Docker and apt policy files pass verification policy.'
