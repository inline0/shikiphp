#!/usr/bin/env bash
set -euo pipefail

readonly LOG_DIR="${LOG_DIR:-/var/log}"

cleanup() {
  echo "cleaning up..." >&2
  rm -f /tmp/lock.*
}
trap cleanup EXIT

process() {
  local file="$1"
  while IFS= read -r line; do
    if [[ "$line" =~ ^ERROR ]]; then
      echo "found: $line"
    fi
  done < "$file"
}

for f in "$LOG_DIR"/*.log; do
  [[ -f "$f" ]] && process "$f"
done
