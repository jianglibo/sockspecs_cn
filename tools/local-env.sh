#!/usr/bin/env bash
# Source only from this repository's local tools. Never resolves another checkout.
set -euo pipefail
sscn_root=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)
[[ -f "$sscn_root/docs/source-baseline.json" && "$sscn_root" != /home/jianglibo/parameter-socks ]] || { echo 'Not the domestic project.' >&2; exit 1; }
[[ -f "$sscn_root/.env" && ! -L "$sscn_root/.env" ]] || { echo 'Run bash tools/setup-local.sh to create a fresh local environment.' >&2; exit 1; }
[[ ! -L "$sscn_root/.local" && ! -L "$sscn_root/.local/wordpress" ]] || { echo 'Runtime directory must not link to another project.' >&2; exit 1; }
set -a
source "$sscn_root/.env"
set +a
[[ "${SSCN_DB_PORT:-}" =~ ^[0-9]+$ && "${SSCN_HTTP_PORT:-}" =~ ^[0-9]+$ ]] || { echo 'Domestic ports required.' >&2; exit 1; }
[[ "$SSCN_DB_PORT" != 33088 && "$SSCN_HTTP_PORT" != 8088 && "$SSCN_DB_PORT" != "$SSCN_HTTP_PORT" ]] || { echo 'Ports must be separate from the international preview.' >&2; exit 1; }
[[ -n "${SSCN_DB_PASSWORD:-}" && -n "${SSCN_DB_ROOT_PASSWORD:-}" && -n "${SSCN_ADMIN_PASSWORD:-}" ]] || { echo 'Fresh domestic credentials required.' >&2; exit 1; }
