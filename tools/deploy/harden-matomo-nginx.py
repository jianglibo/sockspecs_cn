#!/usr/bin/env python3
"""Apply the domestic-only Matomo YAML access restriction, with tested rollback."""
import datetime
import os
from pathlib import Path
import subprocess


def main():
    if os.geteuid() != 0 or '172.16.3.53' not in subprocess.check_output(['hostname', '-I'], text=True).split():
        raise RuntimeError('Root on claw-host required')
    target = Path('/etc/nginx/conf.d/sockspecs-cn.conf')
    source = Path(__file__).with_name('nginx.conf').read_text()
    block = '    # Matomo vendor configuration is not a public web asset.\n    location ~* ^/wp-content/plugins/matomo/.*\\.ya?ml$ { deny all; }\n'
    old = target.read_text()
    if old == source:
        print('Domestic Matomo YAML restriction already installed')
        return
    if source.count(block) != 1 or old != source.replace(block, ''):
        raise RuntimeError('Unexpected domestic Nginx configuration; refusing to overwrite')
    directory = Path('/var/backups/sockspecs-cn/analytics-20261020')
    directory.mkdir(mode=0o700, parents=True, exist_ok=True)
    backup = directory / ('nginx-' + datetime.datetime.now(datetime.timezone.utc).strftime('%H%M%S%f') + '.conf')
    backup.write_text(old)
    try:
        target.write_text(source)
        subprocess.run(['nginx', '-t'], check=True)
        subprocess.run(['systemctl', 'reload', 'nginx'], check=True)
    except BaseException:
        target.write_text(old)
        subprocess.run(['nginx', '-t'], check=True)
        subprocess.run(['systemctl', 'reload', 'nginx'], check=True)
        raise
    print('Domestic Matomo YAML restriction applied; backup:', backup)


if __name__ == '__main__':
    main()
