#!/usr/bin/env python3
"""Add only the two preview names to /etc/hosts; never update public DNS."""
import datetime
import os
from pathlib import Path
import shutil
import sys

def main():
    if os.geteuid() != 0:
        raise RuntimeError('Root is required to edit /etc/hosts')
    mode = sys.argv[1]
    if mode not in ('client', 'origin'):
        raise RuntimeError('Choose client or origin')
    ip = '139.196.167.200' if mode == 'client' else '172.16.3.52'
    hosts = Path('/etc/hosts'); old = hosts.read_text()
    domains = {'sockspecs.cn', 'www.sockspecs.cn'}
    lines = []
    for line in old.splitlines():
        if line == '# SockSpecs CN hosts-only preview':
            continue
        content, sep, comment = line.partition('#')
        words = content.split()
        if len(words) > 1 and domains.intersection(words[1:]):
            remaining = [name for name in words[1:] if name not in domains]
            if remaining:
                lines.append(words[0] + '\t' + ' '.join(remaining) + (' #' + comment if sep else ''))
            elif sep:
                lines.append('#' + comment)
        else:
            lines.append(line)
    updated = '\n'.join(lines).rstrip() + '\n\n# SockSpecs CN hosts-only preview\n' + ip + '\tsockspecs.cn www.sockspecs.cn\n'
    if old != updated:
        backup = hosts.with_name('hosts.before-sockspecs-cn-' + datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%S%fZ'))
        shutil.copy2(hosts, backup)
        hosts.write_text(updated)
        print('Hosts backup:', backup)
    print('Preview mapping:', ip, 'sockspecs.cn www.sockspecs.cn')

if __name__ == '__main__':
    main()
