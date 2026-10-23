#!/usr/bin/env bash
# Explicit fresh local installation only. No production or import operations.
set -euo pipefail
cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.."
for dependency in php composer docker curl openssl; do
  command -v "$dependency" >/dev/null || { echo "Missing dependency: $dependency" >&2; exit 1; }
done
[[ ! -L .env && ! -L .local ]] || { echo 'Environment/runtime symlinks are not allowed.' >&2; exit 1; }
if [[ ! -e .env ]]; then
  (umask 077
   {
    printf 'SSCN_DB_PASSWORD=%s\n' "$(openssl rand -hex 24)"
    printf 'SSCN_DB_ROOT_PASSWORD=%s\n' "$(openssl rand -hex 24)"
    printf 'SSCN_ADMIN_PASSWORD=%s\n' "$(openssl rand -hex 16)"
    printf 'SSCN_DB_PORT=33089\nSSCN_HTTP_PORT=8089\nSSCN_WP_VERSION=7.1.3\nSSCN_WC_VERSION=11.2.1\n'
   } > .env)
fi
source tools/local-env.sh
mkdir -p .local/bin
if [[ ! -f .local/bin/wp-cli.phar ]]; then
  curl -fsSL --retry 3 https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o .local/bin/wp-cli.phar
  curl -fsSL --retry 3 https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar.sha512 -o .local/bin/wp-cli.phar.sha512
fi
php -r 'if (trim(file_get_contents(".local/bin/wp-cli.phar.sha512")) !== hash_file("sha512", ".local/bin/wp-cli.phar")) { fwrite(STDERR, "WP-CLI checksum mismatch\n"); exit(1); }'
docker compose --project-name sockspecs-cn --file "$PWD/compose.yaml" --env-file "$PWD/.env" up -d --wait db
if [[ ! -f .local/wordpress/wp-includes/version.php ]]; then
  bash tools/wp.sh core download "https://wordpress.org/wordpress-${SSCN_WP_VERSION}.zip"
fi
bash tools/wp.sh core verify-checksums
if [[ ! -f .local/wordpress/wp-config.php ]]; then
  printf '%s\n' "$SSCN_DB_PASSWORD" | bash tools/wp.sh config create --dbname=sockspecs_cn --dbuser=sockspecs_cn --dbhost="127.0.0.1:$SSCN_DB_PORT" --prompt=dbpass >/dev/null
  bash tools/wp.sh config set WP_ENVIRONMENT_TYPE local
  bash tools/wp.sh config set DISABLE_WP_CRON true --raw
  bash tools/wp.sh config set WP_DEBUG true --raw
  bash tools/wp.sh config set WP_DEBUG_LOG true --raw
  bash tools/wp.sh config set WP_DEBUG_DISPLAY false --raw
fi
mkdir -p .local/wordpress/wp-content/mu-plugins
cp tools/local-mail-blocker.php .local/wordpress/wp-content/mu-plugins/sockspecs-cn-local-mail.php
if ! bash tools/wp.sh core is-installed >/dev/null 2>&1; then
  printf '%s\n' "$SSCN_ADMIN_PASSWORD" | bash tools/wp.sh core install --url="http://127.0.0.1:$SSCN_HTTP_PORT" --title='SockSpecs 国内开发站' --admin_user=sockspecs_cn --admin_email=admin@example.invalid --skip-email --prompt=admin_password >/dev/null
fi
if [[ ! -e .local/wordpress/wp-content/plugins/parameter-commerce ]]; then
  ln -s "$PWD/parameter-commerce" .local/wordpress/wp-content/plugins/parameter-commerce
fi
if ! bash tools/wp.sh plugin is-installed woocommerce; then
  bash tools/wp.sh plugin install woocommerce --version="$SSCN_WC_VERSION"
fi
bash tools/wp.sh plugin activate woocommerce parameter-commerce
bash tools/wp.sh language core install zh_CN
bash tools/wp.sh site switch-language zh_CN
if ! bash tools/wp.sh language plugin install woocommerce zh_CN; then
  # Some local networks reject the updater's URL validation. Fetch the same
  # official translation archive over HTTPS without weakening WP HTTP checks.
  curl -fsSL --retry 3 "https://downloads.wordpress.org/translation/plugin/woocommerce/${SSCN_WC_VERSION}/zh_CN.zip" -o .local/bin/woocommerce-zh_CN.zip
  php tools/install-local-translations.php
fi
bash tools/wp.sh option update blog_public 0
bash tools/wp.sh option update woocommerce_coming_soon yes
bash tools/wp.sh option update woocommerce_store_pages_only yes
bash tools/wp.sh option update woocommerce_allow_tracking no
bash tools/wp.sh option update woocommerce_currency CNY
bash tools/wp.sh option update woocommerce_default_country CN:CN12
bash tools/wp.sh option update woocommerce_allowed_countries specific
bash tools/wp.sh option update woocommerce_specific_allowed_countries '["CN"]' --format=json
bash tools/wp.sh option update woocommerce_ship_to_countries specific
bash tools/wp.sh option update woocommerce_specific_ship_to_countries '["CN"]' --format=json
bash tools/wp.sh option update woocommerce_review_rating_verification_required yes
bash tools/wp.sh option update timezone_string Asia/Shanghai
bash tools/wp.sh option update woocommerce_price_num_decimals 2
bash tools/wp.sh option update woocommerce_weight_unit g
bash tools/wp.sh option update woocommerce_dimension_unit cm
if [[ ! -e .local/wordpress/wp-content/themes/sockspecs-cn ]]; then
  ln -s "$PWD/theme" .local/wordpress/wp-content/themes/sockspecs-cn
fi
bash tools/wp.sh theme activate sockspecs-cn
bash tools/wp.sh eval-file tools/seed-local-pages.php
composer install --no-interaction --prefer-dist
printf 'Domestic setup complete. Preview: bash tools/preview.sh\nCredentials: sockspecs_cn; password is in the private .env.\n'
