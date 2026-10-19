#!/usr/bin/env bash
set -euo pipefail
source "$(dirname -- "${BASH_SOURCE[0]}")/local-env.sh"
for arg in "$@"; do
  case "$arg" in --path|--path=*|--ssh|--ssh=*|--http|--http=*) echo 'This wrapper is local-only; target overrides are disabled.' >&2; exit 1;; esac
done
sscn_site="$sscn_root/.local/wordpress"
sscn_cli="$sscn_root/.local/bin/wp-cli.phar"
if [[ -f "$sscn_site/wp-config.php" ]]; then
  [[ ! -L "$sscn_site/wp-config.php" ]] || exit 1
  [[ "$(php "$sscn_cli" --path="$sscn_site" config get DB_NAME)" == sockspecs_cn ]] || { echo 'Refusing a non-domestic database.' >&2; exit 1; }
  [[ "$(php "$sscn_cli" --path="$sscn_site" config get DB_HOST)" == "127.0.0.1:$SSCN_DB_PORT" ]] || { echo 'Refusing a different database host.' >&2; exit 1; }
fi
exec php -d sendmail_path=/bin/false "$sscn_cli" --path="$sscn_site" "$@"
