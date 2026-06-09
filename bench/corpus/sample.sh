#!/usr/bin/env bash
#
# Deploy script for benchmarking the highlighter.
set -euo pipefail
IFS=$'\n\t'

readonly APP_NAME="example-api"
readonly MAX_RETRIES=5
DEPLOY_ENV="${1:-staging}"
VERBOSE="${VERBOSE:-0}"

log() {
  local level="$1"; shift
  printf '[%s] %s: %s\n' "$(date +%H:%M:%S)" "$level" "$*" >&2
}

die() {
  log "ERROR" "$@"
  exit 1
}

require() {
  command -v "$1" >/dev/null 2>&1 || die "missing dependency: $1"
}

retry() {
  local attempt=1
  until "$@"; do
    if (( attempt >= MAX_RETRIES )); then
      die "command failed after ${MAX_RETRIES} attempts: $*"
    fi
    log "WARN" "attempt ${attempt} failed, retrying..."
    sleep $(( 2 ** attempt ))
    (( attempt++ ))
  done
}

build_image() {
  local tag="$1"
  log "INFO" "building ${APP_NAME}:${tag}"
  docker build -t "${APP_NAME}:${tag}" \
    --build-arg "ENV=${DEPLOY_ENV}" \
    --label "built=$(date -u +%Y-%m-%dT%H:%M:%SZ)" .
}

main() {
  require docker
  require curl

  local tag
  tag="$(git rev-parse --short HEAD 2>/dev/null || echo "latest")"

  case "$DEPLOY_ENV" in
    production|staging)
      log "INFO" "deploying to ${DEPLOY_ENV}"
      ;;
    *)
      die "unknown environment: ${DEPLOY_ENV}"
      ;;
  esac

  build_image "$tag"
  retry curl -fsS "https://${DEPLOY_ENV}.example.com/health"

  local services=(api db cache)
  for svc in "${services[@]}"; do
    log "INFO" "restarting ${svc}"
    docker compose restart "$svc" &
  done
  wait

  [[ "$VERBOSE" == "1" ]] && log "DEBUG" "deploy complete: ${tag}"
  log "INFO" "done"
}

main "$@"
