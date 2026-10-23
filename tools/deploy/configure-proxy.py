#!/usr/bin/env python3
"""Install only SockSpecs CN's HAProxy route on claw-hub, with validation/rollback."""
import datetime
import os
from pathlib import Path
import shutil
import subprocess
import time

def run(args, **kwargs):
    return subprocess.run(args, check=True, **kwargs)

def status(host, path='/'):
    return subprocess.check_output(['curl', '--noproxy', '*', '--resolve', host + ':443:127.0.0.1', '--max-time', '15', '-sS', '-o', '/dev/null', '-w', '%{http_code}', 'https://' + host + path], text=True)

def main():
    if os.geteuid() != 0 or '172.16.3.52' not in subprocess.check_output(['hostname', '-I'], text=True).split():
        raise RuntimeError('Only root on claw-hub is supported')
    config = Path('/etc/haproxy/haproxy.cfg')
    cert = Path('/etc/haproxy/certs/sockspecs_cn_fullchain.pem')
    key = Path('/etc/haproxy/certs/sockspecs_cn_private.key')
    bundle = Path('/etc/haproxy/certs/sockspecs_cn_haproxy.pem')
    san = subprocess.check_output(['openssl', 'x509', '-in', str(cert), '-noout', '-ext', 'subjectAltName'], text=True)
    if 'DNS:sockspecs.cn' not in san or 'DNS:*.sockspecs.cn' not in san:
        raise RuntimeError('Certificate must cover both apex and wildcard')
    run(['openssl', 'verify', '-CApath', '/etc/ssl/certs', '-untrusted', str(cert), str(cert)])
    pub = subprocess.check_output(['openssl', 'x509', '-in', str(cert), '-pubkey', '-noout'])
    if pub != subprocess.check_output(['openssl', 'pkey', '-in', str(key), '-pubout'], stderr=subprocess.DEVNULL):
        raise RuntimeError('Certificate and key mismatch')
    original = config.read_text()
    if 'backend be_sockspecs_cn' in original:
        raise RuntimeError('Route already exists; inspect before making a further proxy change')
    if status('api.cjj365.cc', '/health') != '200':
        raise RuntimeError('API baseline unhealthy; no proxy mutation performed')
    health = subprocess.check_output(['curl', '--noproxy', '*', '-H', 'Host: sockspecs.cn', '--max-time', '10', '-sS', '-o', '/dev/null', '-w', '%{http_code}', 'http://172.16.3.53:18081/sscn-health'], text=True)
    if health != '200':
        raise RuntimeError('Domestic origin not ready')
    lines = original.splitlines(keepends=True)
    binds = [i for i,l in enumerate(lines) if l.strip().startswith('bind :443 ssl ')]
    assert len(binds) == 1
    i = binds[0]
    if str(bundle) not in lines[i]:
        lines[i] = lines[i].rstrip('\n') + ' crt ' + str(bundle) + '\n'
    source = ''.join(lines)
    marker = 'frontend fe_bb_http\n'
    assert source.count(marker) == 1
    source = source.replace(marker, marker + '    http-request redirect scheme https code 301 if { hdr(host),lower,field(1,:) -m str sockspecs.cn www.sockspecs.cn }\n', 1)
    marker = '    use_backend be_bb_api if { var(txn.req_host) -m str api.cjj365.cc }'
    assert source.count(marker) == 1
    source = source.replace(marker, '    http-request redirect prefix https://sockspecs.cn code 301 if { var(txn.req_host) -m str www.sockspecs.cn }\n    use_backend be_sockspecs_cn if { var(txn.req_host) -m str sockspecs.cn }\n' + marker, 1)
    source += '''
backend be_sockspecs_cn
    timeout server 120s
    http-request set-header X-Forwarded-For %[src]
    http-request set-header X-Forwarded-Proto https
    http-request set-header X-Forwarded-Host sockspecs.cn
    option httpchk
    http-check send meth GET uri /sscn-health ver HTTP/1.1 hdr Host sockspecs.cn
    http-check expect status 200
    server sockspecs_cn 172.16.3.53:18081 check inter 5s fall 3 rise 2
'''
    os.umask(0o077)
    backup = Path('/var/backups') / ('sockspecs-cn-proxy-' + datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ'))
    backup.mkdir(mode=0o700)
    shutil.copy2(config, backup / 'haproxy.cfg.before')
    shutil.copy2(bundle, backup / 'certificate-bundle.before')
    candidate = backup / 'haproxy.cfg.after'; candidate.write_text(source)
    try:
        bundle.write_bytes(cert.read_bytes().rstrip() + b'\n' + key.read_bytes().rstrip() + b'\n')
        bundle.chmod(0o600); key.chmod(0o600)
        run(['haproxy', '-c', '-f', str(candidate)])
        if config.read_text() != original:
            raise RuntimeError('HAProxy changed concurrently')
        config.write_text(source)
        run(['systemctl', 'reload', 'haproxy'])
        time.sleep(3)
        checks = {'API': status('api.cjj365.cc', '/health'), 'SockSpecs': status('sockspecs.cn'), 'www': status('www.sockspecs.cn')}
        if checks != {'API': '200', 'SockSpecs': '200', 'www': '301'}:
            raise RuntimeError('Post-reload checks failed: ' + str(checks))
    except Exception:
        shutil.copy2(backup / 'haproxy.cfg.before', config)
        shutil.copy2(backup / 'certificate-bundle.before', bundle)
        bundle.chmod(0o600)
        run(['haproxy', '-c', '-f', str(config)])
        run(['systemctl', 'reload', 'haproxy'])
        raise
    print('HTTPS route installed:', checks, '; rollback files:', backup)

if __name__ == '__main__':
    main()
