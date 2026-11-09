#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
USE_SUDO_DOCKER="${USE_SUDO_DOCKER:-0}"
TEST_IMAGE="password-compat-system-test:7.2"
FLOOR_IMAGE="password-compat-floor-lint:5.3.29"

docker_cmd() {
    if [[ "${USE_SUDO_DOCKER}" == "1" ]]; then
        sudo docker "$@"
    else
        docker "$@"
    fi
}

bash "${REPO_ROOT}/scripts/check-floor-lint-policy.sh"

docker_cmd build -f "${REPO_ROOT}/docker/system-test/Dockerfile" -t "${TEST_IMAGE}" "${REPO_ROOT}"
docker_cmd build -f "${REPO_ROOT}/docker/floor-lint/Dockerfile" -t "${FLOOR_IMAGE}" "${REPO_ROOT}"

docker_cmd run --rm \
    -v "${REPO_ROOT}:/app" \
    -w /app \
    "${FLOOR_IMAGE}" \
    bash /app/scripts/floor-lint-inner.sh

docker_cmd run --rm \
    -v "${REPO_ROOT}:/app" \
    -w /app \
    "${TEST_IMAGE}" \
    bash /app/scripts/system-test-inner.sh
