#!/usr/bin/env bash
set -euo pipefail

readonly LOG_DIR="${HOME}/logs"
declare -a files=()

log() {
    local level="$1"; shift
    printf '[%s] %s\n' "$level" "$*" >&2
}

cleanup() {
    rm -f "${TMP:-/tmp/none}"
}
trap cleanup EXIT

for f in "$LOG_DIR"/*.log; do
    [[ -e "$f" ]] || continue
    if grep -qE 'ERROR|WARN' "$f"; then
        files+=("$f")
        log INFO "matched $(basename "$f")"
    fi
done

echo "Found ${#files[@]} files" && exit 0
