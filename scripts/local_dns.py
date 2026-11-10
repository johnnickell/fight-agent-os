#!/usr/bin/env python3
"""Operate scoped local DNS from the Fight host registry, independently of any front door."""
import argparse
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import time
import uuid

from local_domain import digest, load, locked
from resource_lifecycle.preparation import resolve
from resource_lifecycle.resources import Hold, require, write_private

ROOT = Path(__file__).resolve().parent.parent
SAFE = resolve(ROOT)[0]
STATE = SAFE / 'var/local-ingress/dns'
IMAGE = 'coredns/coredns:1.15.0@sha256:0a9d8c8ddf34347eb59aad9a8784f0710de88d6d112726d11d271f9bc6daca26'
PORT = 15353


def run(*args):
    return subprocess.check_output([str(a) for a in args], cwd=ROOT, text=True, stderr=subprocess.PIPE).strip()


def names():
    return sorted(p['namespace'] for p in load(SAFE)['projects'].values())


def corefile(values):
    blocks = []
    for name in values:
        # A/AAAA answers are authoritative, with no forwarding. Unknown/deeper
        # names receive NXDOMAIN. Routing still requires an enrolled app gateway.
        pattern = '^(?:[a-z0-9-]+\\.)?' + name.replace('.', '\\.') + '\\.$'
        blocks.append(f'''{name}:5353 {{
    template IN A {{
        match "{pattern}"
        answer "{{{{ .Name }}}} 5 IN A 127.0.0.1"
        fallthrough
    }}
    template IN AAAA {{
        match "{pattern}"
        rcode NOERROR
        fallthrough
    }}
    template ANY ANY {{ rcode NXDOMAIN }}
}}
''')
    return ''.join(blocks) + '.:5353 {\n    template ANY ANY { rcode REFUSED }\n}\n'


def dc(*args):
    return run('docker', 'compose', '--project-directory', STATE, '--project-name', 'fight-local-dns',
               '--env-file', '/dev/null', '-f', STATE / 'compose.json', *args)


def query(name, port=PORT, kind='A', tcp=False):
    return run('dig', '@127.0.0.1', '-p', port, name, kind, '+time=1', '+tries=1', '+noall', '+comments', '+answer',
               '+tcp' if tcp else '+notcp')


def check(config, values):
    name = 'fight-local-dns-check-' + uuid.uuid4().hex[:10]
    try:
        run('docker', 'run', '--detach', '--name', name, '--read-only', '--user', '65532:65532',
            '--cap-drop', 'ALL', '--cap-add', 'NET_BIND_SERVICE', '--security-opt', 'no-new-privileges:true', '--memory', '64m', '--cpus', '0.25',
            '--pids-limit', '32', '--publish', '127.0.0.1::5353/udp', '--publish', '127.0.0.1::5353/tcp',
            '--mount', f'type=bind,source={config},target=/config,readonly', IMAGE, '-conf', '/config/Corefile')
        port = run('docker', 'port', name, '5353/udp').rsplit(':', 1)[1]
        tcpport = run('docker', 'port', name, '5353/tcp').rsplit(':', 1)[1]
        for attempt in range(20):
            try:
                require('status: REFUSED' in query('unrelated.invalid', port), 'Unrelated zone must be refused')
                break
            except (subprocess.CalledProcessError, ValueError):
                if attempt == 19:
                    raise
                time.sleep(0.25)
        for zone in values:
            for host in (zone, 'task-00589-0123456789.' + zone):
                require('127.0.0.1' in query(host, port), 'Missing local A answer')
                require('status: NOERROR' in query(host, port, 'AAAA'), 'AAAA must not escape to public DNS')
                require('127.0.0.1' in query(host, tcpport, tcp=True), 'TCP DNS failed')
            require('status: NXDOMAIN' in query('too.deep.' + zone, port), 'Nested name must fail locally')
    except Exception:
        logs = subprocess.run(['docker', 'logs', '--tail', '20', name], cwd=ROOT, capture_output=True, text=True)
        print(logs.stdout + logs.stderr)
        raise
    finally:
        subprocess.run(['docker', 'stop', '--time', '2', name], cwd=ROOT, capture_output=True)
        subprocess.run(['docker', 'rm', name], cwd=ROOT, capture_output=True)
        for attempt in range(20):
            if not run('docker', 'ps', '-aq', '--filter', 'name=^/' + name + '$'):
                break
            time.sleep(0.1)
        else:
            raise ValueError('DNS qualification container remains')



def configuration():
    require(STATE == STATE.resolve(), 'Aliased DNS state refused')
    return {'services': {'dns': {
        'image': IMAGE, 'restart': 'unless-stopped', 'user': '65532:65532',
        'command': ['-conf', '/config/Corefile'], 'read_only': True,
        'cap_drop': ['ALL'], 'cap_add': ['NET_BIND_SERVICE'],
        'security_opt': ['no-new-privileges:true'], 'mem_limit': '64m', 'cpus': 0.25, 'pids_limit': 32,
        'labels': {'io.fight.dns.owner': str(SAFE)},
        'ports': [f'127.0.0.1:{PORT}:5353/udp', f'127.0.0.1:{PORT}:5353/tcp'],
        'volumes': [{'type': 'bind', 'source': str(STATE / 'config'), 'target': '/config', 'read_only': True,
                     'bind': {'create_host_path': False}}], 'networks': ['dns']}},
        'networks': {'dns': {'name': 'fight-local-dns', 'labels': {'io.fight.dns.owner': str(SAFE)}}}}


def owned():
    for noun, label in [('container', 'label=com.docker.compose.project=fight-local-dns'),
                        ('network', 'name=^fight-local-dns$')]:
        ids = run('docker', noun, 'ls', *(['--all'] if noun == 'container' else []), '-q', '--filter', label).splitlines()
        if ids:
            for item in json.loads(run('docker', noun, 'inspect', *ids)):
                labels = item['Config']['Labels'] if noun == 'container' else item['Labels']
                require(labels.get('io.fight.dns.owner') == str(SAFE), 'Foreign DNS runtime holds operation')


def current():
    require((STATE / 'compose.json').read_text() == json.dumps(configuration(), indent=2) + '\n', 'DNS runtime configuration changed')
    require((STATE / 'config/Corefile').read_text() == corefile(names()), 'Registrations changed; prepare and apply DNS plan')
    owned()


def main():
    os.umask(0o077)
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('action', choices=['plan', 'apply', 'check', 'up', 'down', 'status', 'enrollment'])
    args = parser.parse_args()
    require(STATE == STATE.resolve(), 'Aliased DNS state refused')
    if args.action == 'plan':
        plan = {'registry': digest(load(SAFE)), 'namespaces': names(), 'port': PORT,
                'source': digest({'corefile': corefile(names()), 'compose': configuration()})}
        write_private(STATE / 'plan.json', json.dumps(plan, indent=2) + '\n')
        print(json.dumps({**plan, 'effects': ['Qualify and update only Fight DNS on loopback TCP/UDP 15353'],
                          'human_step': 'OS resolver enrollment remains explicit; bin/local-dns enrollment prints commands'}, indent=2))
    elif args.action == 'apply':
        with locked(SAFE):
            plan = json.loads((STATE / 'plan.json').read_text())
            require(plan == {'registry': digest(load(SAFE)), 'namespaces': names(), 'port': PORT,
                            'source': digest({'corefile': corefile(names()), 'compose': configuration()})},
                    'DNS plan stale; preview again')
            owned()
            scratch = ROOT / '.runs/local-domain/dns'
            scratch.mkdir(parents=True, exist_ok=True)
            with tempfile.TemporaryDirectory(dir=scratch) as temporary:
                path = Path(temporary)
                path.chmod(0o755)
                (path / 'Corefile').write_text(corefile(names()))
                (path / 'Corefile').chmod(0o644)
                check(path, names())
            write_private(STATE / 'compose.json', json.dumps(configuration(), indent=2) + '\n')
            config = STATE / 'config/Corefile'
            require(config == config.resolve(), 'Aliased DNS configuration refused')
            if config.exists():
                write_private(STATE / 'Corefile.previous', config.read_text())
            write_private(config, corefile(names()))
            config.parent.chmod(0o755)
            config.chmod(0o644)
            print(dc('up', '-d', '--no-deps', '--force-recreate', 'dns'))
            for zone in names():
                for attempt in range(20):
                    try:
                        require('127.0.0.1' in query(zone), 'DNS startup incomplete; inspect status and retry up')
                        break
                    except subprocess.CalledProcessError:
                        if attempt == 19:
                            raise
                        time.sleep(0.25)
            (STATE / 'plan.json').unlink()
            print('DNS applied; OS resolver, trust, proxy and applications unchanged.')
    elif args.action == 'enrollment':
        for zone in names():
            print(f'# Inspect existing /etc/resolver/{zone} before enrolling. Human-run macOS commands:\n'
                  f"sudo mkdir -p /etc/resolver\n"
                  f"printf 'nameserver 127.0.0.1\\nport {PORT}\\n' | sudo tee /etc/resolver/{zone}\n"
                  f"dscacheutil -q host -a name {zone}\n")
    elif args.action == 'check':
        scratch = ROOT / '.runs/local-domain/dns'
        scratch.mkdir(parents=True, exist_ok=True)
        with tempfile.TemporaryDirectory(dir=scratch) as temporary:
            path = Path(temporary)
            path.chmod(0o755)
            (path / 'Corefile').write_text(corefile(names()))
            (path / 'Corefile').chmod(0o644)
            check(path, names())
        print('Isolated UDP/TCP DNS qualification passed; temporary container removed.')
    else:
        owned()
        if args.action == 'down':
            print(dc('stop', 'dns'))
        elif args.action == 'up':
            current()
            print(dc('up', '-d', '--no-deps', 'dns'))
        else:
            current()
            print(dc('ps', '--all', 'dns'))
            for zone in names():
                print(query(zone))
                print(query(zone, kind='AAAA'))


if __name__ == '__main__':
    try:
        main()
    except (Hold, ValueError, OSError, KeyError, subprocess.CalledProcessError) as error:
        print('DNS operation incomplete: ' + str(error), file=sys.stderr)
        raise SystemExit(1)
