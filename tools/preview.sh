#!/usr/bin/env bash
set -euo pipefail
source "$(dirname -- "${BASH_SOURCE[0]}")/local-env.sh"
exec bash "$sscn_root/tools/wp.sh" server --host=127.0.0.1 --port="$SSCN_HTTP_PORT"
