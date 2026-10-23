#!/usr/bin/env python3
"""Root-only claw-host bootstrap and atomic domestic custom-code deployment."""
import datetime
import json
import os
from pathlib import Path
import pwd
import secrets
import shutil
import subprocess
import sys
import tarfile
import zipfile

SITE = Path('/srv/sockspecs-cn')
PUBLIC = SITE / 'public'
SECRET = Path('/etc/sockspecs-cn/credentials.json')
DB = 'sockspecs_cn'
ACCOUNT = 'sockspecs-cn'
WP_VERSION, WC_VERSION = '7.1.3', '11.2.1'

def run(args, **kwargs):
    return subprocess.run(args, check=True, **kwargs)

def wp(*args, **kwargs):
    return run(['sudo', '-u', ACCOUNT, 'env', 'HOME=' + str(SITE),
                'WP_CLI_CACHE_DIR=' + str(SITE / 'cache/wp-cli'),
                '/usr/local/bin/wp', '--path=' + str(PUBLIC), *args], **kwargs)

def download(url, dest):
    run(['curl', '-fsSL', '--retry', '3', '--max-time', '180', url, '-o', str(dest)])

def unzip_tree(archive, destination, prefix):
    with zipfile.ZipFile(archive) as z:
        for item in z.infolist():
            if not item.filename.startswith(prefix):
                raise RuntimeError('Unexpected archive prefix')
            name = Path(item.filename[len(prefix):])
            if name.is_absolute() or '..' in name.parts:
                raise RuntimeError('Unsafe archive path')
            if not name.parts:
                continue
            target = destination / name
            if item.is_dir():
                target.mkdir(parents=True, exist_ok=True)
            else:
                target.parent.mkdir(parents=True, exist_ok=True)
                target.write_bytes(z.read(item))

def install_translation(kind, version, target):
    archive = SITE / 'cache' / (kind.replace('/', '-') + '-zh_CN.zip')
    download(f'https://downloads.wordpress.org/translation/{kind}/{version}/zh_CN.zip', archive)
    target.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(archive) as z:
        for item in z.infolist():
            p = Path(item.filename)
            if len(p.parts) == 1 and p.suffix in ('.mo', '.po', '.json'):
                (target / p.name).write_bytes(z.read(item))

def activate_release(release):
    link = SITE / 'current.next'
    link.symlink_to(release)
    link.replace(SITE / 'current')

def main():
    os.umask(0o022)
    if os.geteuid() != 0 or '172.16.3.53' not in subprocess.check_output(['hostname', '-I'], text=True).split():
        raise RuntimeError('Only root on claw-host is supported')
    mode, archive_name = sys.argv[1:]
    if mode == 'finish-bootstrap':
        if not SECRET.is_file() or not (SITE / 'current').exists() or (SITE / 'DEPLOYED').exists():
            raise RuntimeError('Only an incomplete domestic bootstrap can be resumed')
        wp('eval', "if (DB_NAME !== 'sockspecs_cn' || !defined('SSCN_DOMESTIC_SITE') || !SSCN_DOMESTIC_SITE || get_option('woocommerce_coming_soon') !== 'yes') throw new RuntimeException('Not the domestic preview');")
        finish_install((SITE / 'current').resolve())
        return
    if mode not in ('bootstrap', 'sync'):
        raise RuntimeError('Expected bootstrap or sync')
    if mode == 'bootstrap':
        if SITE.exists() or SECRET.exists():
            raise RuntimeError('Fresh bootstrap refuses existing site')
        count = subprocess.check_output(['mysql', '-N', '-B', '-e', "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='sockspecs_cn'; SELECT COUNT(*) FROM mysql.user WHERE User='sockspecs_cn';"], text=True)
        if any(line != '0' for line in count.splitlines()):
            raise RuntimeError('Database or login already exists')
        for file in ('/etc/nginx/conf.d/sockspecs-cn.conf', '/etc/php/8.3/fpm/pool.d/sockspecs-cn.conf', '/etc/cron.d/sockspecs-cn'):
            if Path(file).exists():
                raise RuntimeError('Domestic configuration already exists')
        run(['useradd', '--system', '--user-group', '--home-dir', str(SITE), '--shell', '/usr/sbin/nologin', ACCOUNT])
        SITE.mkdir(mode=0o755)
    elif not (SITE / 'DEPLOYED').is_file() or not SECRET.is_file():
        raise RuntimeError('Existing domestic deployment marker required')

    previous_visibility = wp('option', 'get', 'woocommerce_coming_soon', capture_output=True, text=True).stdout.strip() if mode == 'sync' else None
    stamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%S%fZ')
    release = SITE / 'releases' / stamp
    release.mkdir(parents=True)
    with tarfile.open(archive_name) as t:
        for m in t.getmembers():
            p = Path(m.name)
            if p.is_absolute() or '..' in p.parts or p.parts[0] not in ('sscn-commerce', 'commerce-libraries', 'parameter-commerce', 'theme', 'tools') or not (m.isfile() or m.isdir()):
                raise RuntimeError('Unsafe deployment archive member')
        t.extractall(release, filter='data')
    run(['chown', '-R', 'root:root', str(release)])
    previous = (SITE / 'current').resolve() if (SITE / 'current').exists() else None
    commerce_link = PUBLIC / 'wp-content/plugins/sscn-commerce'
    was_commerce_active = False
    created_commerce_link = False
    if mode == 'sync':
        was_commerce_active = subprocess.run(['sudo', '-u', ACCOUNT, 'wp', '--path=' + str(PUBLIC), 'plugin', 'is-active', 'sscn-commerce'], capture_output=True).returncode == 0
        if commerce_link.is_symlink():
            if str(commerce_link.readlink()) != str(SITE / 'current/sscn-commerce'):
                raise RuntimeError('Unexpected domestic commerce link')
        elif commerce_link.exists():
            raise RuntimeError('Refusing to overwrite existing commerce plugin')
        else:
            commerce_link.symlink_to(SITE / 'current/sscn-commerce'); created_commerce_link = True
    activate_release(release)
    if mode == 'sync':
        try:
            wp('plugin', 'activate', 'sscn-commerce')
            wp('eval', "if (DB_NAME !== 'sockspecs_cn' || !defined('SSCN_DOMESTIC_SITE') || !SSCN_DOMESTIC_SITE || !class_exists('ParameterCommerce\\Plugin')) { throw new RuntimeException('Deployment check failed'); } echo 'Domestic code loaded; visibility checked separately.\\n';")
            if wp('option', 'get', 'woocommerce_coming_soon', capture_output=True, text=True).stdout.strip() != previous_visibility:
                raise RuntimeError('Code sync changed store visibility')
            run(['systemctl', 'reload', 'php8.3-fpm'])
        except Exception:
            if not was_commerce_active:
                wp('plugin', 'deactivate', 'sscn-commerce')
            if created_commerce_link:
                commerce_link.unlink(missing_ok=True)
            activate_release(previous)
            run(['systemctl', 'reload', 'php8.3-fpm'])
            raise
        print('Custom code release:', release, '; rollback release:', previous)
        return

    account = pwd.getpwnam(ACCOUNT)
    for sub in ('public', 'cache', 'log'):
        p = SITE / sub
        p.mkdir(); os.chown(p, account.pw_uid, account.pw_gid)
    SECRET.parent.mkdir(mode=0o700)
    credentials = {'database': DB, 'db_user': DB, 'db_password': secrets.token_hex(24),
                   'admin_user': 'sockspecs_cn', 'admin_password': secrets.token_hex(20), 'url': 'https://sockspecs.cn'}
    SECRET.write_text(json.dumps(credentials, indent=2) + '\n'); SECRET.chmod(0o600)
    sql = f"CREATE DATABASE `{DB}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER '{DB}'@'localhost' IDENTIFIED BY '{credentials['db_password']}'; GRANT ALL PRIVILEGES ON `{DB}`.* TO '{DB}'@'localhost';"
    run(['mysql'], input=sql, text=True)
    download(f'https://wordpress.org/wordpress-{WP_VERSION}.zip', SITE / 'cache/wordpress.zip')
    unzip_tree(SITE / 'cache/wordpress.zip', PUBLIC, 'wordpress/')
    download(f'https://downloads.wordpress.org/plugin/woocommerce.{WC_VERSION}.zip', SITE / 'cache/woocommerce.zip')
    unzip_tree(SITE / 'cache/woocommerce.zip', PUBLIC / 'wp-content/plugins/woocommerce', 'woocommerce/')
    install_translation('core', WP_VERSION, PUBLIC / 'wp-content/languages')
    install_translation('plugin/woocommerce', WC_VERSION, PUBLIC / 'wp-content/languages/plugins')
    defines = {'DB_NAME': DB, 'DB_USER': DB, 'DB_PASSWORD': credentials['db_password'], 'DB_HOST': 'localhost',
               'DB_CHARSET': 'utf8mb4', 'DB_COLLATE': '', 'WP_ENVIRONMENT_TYPE': 'staging',
               'WP_HOME': credentials['url'], 'WP_SITEURL': credentials['url']}
    for key in ('AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'):
        defines[key] = secrets.token_hex(32)
    config = '<?php\n' + ''.join(f"define('{key}', '{value}');\n" for key, value in defines.items())
    config += "define('SSCN_DOMESTIC_SITE', true);\ndefine('DISABLE_WP_CRON', true);\ndefine('DISALLOW_FILE_EDIT', true);\ndefine('WP_DEBUG', false);\ndefine('FORCE_SSL_ADMIN', true);\ndefine('AUTOMATIC_UPDATER_DISABLED', true);\n$table_prefix = 'wp_';\nif (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');\nrequire_once ABSPATH . 'wp-settings.php';\n"
    (PUBLIC / 'wp-config.php').write_text(config)
    (PUBLIC / 'wp-content/plugins/sscn-commerce').symlink_to(SITE / 'current/sscn-commerce')
    (PUBLIC / 'wp-content/plugins/parameter-commerce').symlink_to(SITE / 'current/parameter-commerce')
    (PUBLIC / 'wp-content/themes/sockspecs-cn').symlink_to(SITE / 'current/theme')
    mu = PUBLIC / 'wp-content/mu-plugins'; mu.mkdir(exist_ok=True)
    (mu / 'sockspecs-cn-preview.php').symlink_to(SITE / 'current/tools/deploy/preview-guard.php')
    (PUBLIC / 'sscn-health.txt').write_text('sockspecs-cn ready\n')
    run(['chown', '-R', '-h', ACCOUNT + ':' + ACCOUNT, str(PUBLIC), str(SITE / 'cache'), str(SITE / 'log')])
    (PUBLIC / 'wp-config.php').chmod(0o600)
    wp('core', 'verify-checksums')
    wp('core', 'install', '--url=https://sockspecs.cn', '--title=SockSpecs', '--admin_user=sockspecs_cn', '--admin_email=hello@sockspecs.com', '--skip-email', '--prompt=admin_password', input=credentials['admin_password'] + '\n', text=True, stdout=subprocess.DEVNULL)
    wp('site', 'switch-language', 'zh_CN')
    wp('plugin', 'activate', 'woocommerce', 'parameter-commerce', 'sscn-commerce')
    wp('theme', 'activate', 'sockspecs-cn')
    wp('eval-file', str(release / 'tools/deploy/configure-preview.php'))
    wp('eval-file', str(release / 'tools/seed-local-pages.php'))
    finish_install(release)

def finish_install(release):
    config = PUBLIC / 'wp-config.php'
    source = config.read_text()
    if "define('DISALLOW_FILE_MODS'" not in source:
        assert source.count('$table_prefix =') == 1
        config.write_text(source.replace('$table_prefix =', "define('DISALLOW_FILE_MODS', true);\n$table_prefix =", 1))
    nginx = Path('/etc/nginx/conf.d/sockspecs-cn.conf'); pool = Path('/etc/php/8.3/fpm/pool.d/sockspecs-cn.conf')
    shutil.copyfile(release / 'tools/deploy/nginx.conf', nginx)
    shutil.copyfile(release / 'tools/deploy/php-fpm.conf', pool)
    try:
        run(['nginx', '-t']); run(['php-fpm8.3', '-t'])
        run(['systemctl', 'reload', 'php8.3-fpm']); run(['systemctl', 'reload', 'nginx'])
    except Exception:
        nginx.unlink(missing_ok=True); pool.unlink(missing_ok=True)
        run(['systemctl', 'reload', 'php8.3-fpm']); run(['systemctl', 'reload', 'nginx'])
        raise
    Path('/etc/cron.d/sockspecs-cn').write_text('*/5 * * * * sockspecs-cn /usr/bin/flock -n /srv/sockspecs-cn/cache/cron.lock /usr/local/bin/wp --path=/srv/sockspecs-cn/public cron event run --due-now --quiet >>/srv/sockspecs-cn/log/cron.log 2>&1\n')
    Path('/etc/logrotate.d/sockspecs-cn').write_text('/srv/sockspecs-cn/log/*.log {\n weekly\n rotate 4\n compress\n missingok\n notifempty\n copytruncate\n su sockspecs-cn sockspecs-cn\n}\n')
    (SITE / 'DEPLOYED').write_text('Domestic staging installation. Source release: ' + release.name + '\n')
    print('Domestic installation complete; credential file:', SECRET)

if __name__ == '__main__':
    main()
