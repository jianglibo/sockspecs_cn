#!/usr/bin/env python3
"""Deploy domestic custom code to claw-host. Dry run unless --apply is supplied."""
import argparse
import datetime
from pathlib import Path
import shlex
import subprocess
import tarfile

HOST = 'claw-host'
REMOTE_ROOT = '/srv/sockspecs-cn'
ROOT = Path(__file__).resolve().parents[2]
PAYLOAD = ('sscn-commerce', 'commerce-libraries', 'parameter-commerce', 'theme', 'tools/deploy', 'tools/seed-local-pages.php')

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--apply', action='store_true')
    parser.add_argument('--bootstrap', action='store_true', help='Fresh install only; refuses existing sites/databases.')
    args = parser.parse_args()
    print('Target:', HOST, REMOTE_ROOT, '; scope:', ', '.join(PAYLOAD), flush=True)
    if not args.apply:
        print('Dry run: no connection or changes. Add --apply to deploy.')
        return
    subprocess.run(['composer', 'install', '--no-dev', '--no-interaction', '--prefer-dist', '--no-scripts', '--no-plugins'], cwd=ROOT / 'commerce-libraries', check=True)
    subprocess.run(['composer', 'check'], cwd=ROOT, check=True)
    stamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%S%fZ')
    directory = ROOT / '.local/deploy'; directory.mkdir(parents=True, exist_ok=True)
    archive = directory / ('sockspecs-cn-' + stamp + '.tar.gz')
    def safe_member(member):
        p = Path(member.name)
        if '__pycache__' in p.parts or p.suffix == '.pyc':
            return None
        if member.issym() or member.islnk():
            raise RuntimeError('Deployment source must not contain symlinks')
        return member
    with tarfile.open(archive, 'w:gz') as t:
        for name in PAYLOAD:
            t.add(ROOT / name, arcname=name, filter=safe_member)
    remote = '/tmp/' + archive.name
    subprocess.run(['scp', '-q', str(archive), HOST + ':' + remote], check=True)
    mode = 'bootstrap' if args.bootstrap else 'sync'
    try:
        subprocess.run(['ssh', '-o', 'BatchMode=yes', HOST, 'sudo python3 - ' + shlex.quote(mode) + ' ' + shlex.quote(remote)], input=(ROOT / 'tools/deploy/remote-site.py').read_bytes(), check=True)
    finally:
        subprocess.run(['ssh', '-o', 'BatchMode=yes', HOST, 'rm -f -- ' + shlex.quote(remote)], check=False)

if __name__ == '__main__':
    main()
