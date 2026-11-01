#!/usr/bin/env python3
"""Explicit shared local HTTPS operations; never enroll trust or operate applications."""
import argparse
import json
import os
from pathlib import Path
import re
import socket
import subprocess
import sys

from resource_lifecycle.preparation import environment, resolve
from resource_lifecycle.resources import Hold, query, require, run, text, write_private

PROJECT = NETWORK = 'fight-local-ingress'
OWNER = 'io.fight.ingress.owner'
NGINX = 'nginx:1.30.6-alpine@sha256:47928bf67eb62a1863cb08d9ecb52d3e704304ba614eaee17884204677702951'
GENERATOR = 'nginxproxy/docker-gen:0.15.0@sha256:dec247f03aedb22b968e5a1d092bef09d78b85998dc08706f723e4210d553e5d'
ROOT = Path(__file__).resolve().parent.parent


def state(safe):
    path = safe / 'var/local-ingress'
    require(path.resolve() == path, 'Aliased ingress state refused')
    return path


def consumer(root, selector=None):
    safe, checkout, project, entry = resolve(root, selector)
    host = project + '.localhost' if selector else 'fight-agent-os.localhost'
    return safe, checkout, project, host


def certificates(safe, host):
    require(re.fullmatch(r'[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.localhost', host), 'Invalid exact local name')
    directory = state(safe) / 'certs' / host
    require(directory.resolve() == directory, 'Aliased certificate directory refused')
    return directory


def openssl_tool():
    executable = os.environ.get('FIGHT_INGRESS_OPENSSL', 'openssl')
    for command, capability in (('x509', '-checkhost'), ('verify', '-verify_hostname')):
        result = subprocess.run([executable, command, '-help'], capture_output=True, text=True)
        require(capability in result.stdout + result.stderr,
                'OpenSSL hostname-verification capability missing; explicitly set FIGHT_INGRESS_OPENSSL to a capable executable')
    return executable


def check_leaf(safe, host):
    directory = certificates(safe, host)
    cert, key = directory / 'localhost.pem', directory / 'localhost-key.pem'
    ca = state(safe) / 'certs/rootCA.pem'
    for path in (cert, key, ca):
        require(path.is_file() and not path.is_symlink() and path.stat().st_size,
                'Missing regular actual-name leaf/key/CA; human: ./bin/https setup --ingress [--worktree SELECTOR]')
    openssl = openssl_tool()
    require('does match certificate' in text([openssl, 'x509', '-in', cert, '-checkhost', host, '-noout'], safe),
            'Leaf does not cover the exact canonical hostname')
    text([openssl, 'x509', '-in', cert, '-checkend', '86400', '-noout'], safe)
    require(text([openssl, 'x509', '-in', cert, '-pubkey', '-noout'], safe) ==
            text([openssl, 'pkey', '-in', key, '-passin', 'pass:', '-pubout'], safe), 'TLS leaf/key mismatch')
    text([openssl, 'verify', '-CAfile', ca, '-verify_hostname', host, cert], safe)
    return directory


def compose(safe):
    return ['docker', 'compose', '--project-directory', str(state(safe)), '--project-name', PROJECT,
            '--env-file', '/dev/null', '--file', str(state(safe) / 'compose.json')]


def owned(safe):
    """Refuse reserved names occupied by another repository/operator configuration."""
    containers = text(['docker', 'ps', '-aq', '--filter', 'label=com.docker.compose.project=' + PROJECT], safe).splitlines()
    if containers:
        for item in query(['docker', 'inspect', *containers], safe):
            require(item['Config']['Labels'].get(OWNER) == str(safe), 'Foreign ingress project; preserve it')
    networks = text(['docker', 'network', 'ls', '-q', '--filter', 'name=^' + NETWORK + '$'], safe).splitlines()
    if networks:
        network = query(['docker', 'network', 'inspect', *networks], safe)[0]
        require((network.get('Labels') or {}).get(OWNER) == str(safe), 'Foreign ingress network; preserve it')
    return bool(networks)


def configuration(safe):
    path = state(safe)
    context = query(['docker', 'context', 'inspect'], safe)[0]['Endpoints']['docker']['Host']
    require(context.startswith('unix://'), 'Only an explicit local Unix Docker socket is supported')
    socket_path = Path(context.removeprefix('unix://'))
    require(socket_path.is_socket(), 'Local Docker socket unavailable')

    def mount(source, target, readonly=True):
        return {'type': 'bind', 'source': str(source), 'target': target, 'read_only': readonly,
                'bind': {'create_host_path': False}}

    limits = {'read_only': True, 'tmpfs': ['/tmp:rw,nosuid,noexec,size=32m'], 'cap_drop': ['ALL'],
              'security_opt': ['no-new-privileges:true'], 'mem_limit': '128m', 'cpus': 0.5,
              'pids_limit': 64, 'restart': 'on-failure:3', 'labels': {OWNER: str(safe)}}
    model = {'x-fight-owner': str(safe), 'services': {
        'nginx': {**limits, 'image': NGINX, 'user': '0:0', 'cap_add': ['CHOWN', 'SETUID', 'SETGID'],
            'entrypoint': ['nginx'], 'command': ['-g', 'daemon off;'],
            'ports': ['127.0.0.1:80:80', '127.0.0.1:443:443'],
            'networks': ['ingress'], 'volumes': [mount(path / 'nginx.conf', '/etc/nginx/nginx.conf'),
                mount(path / 'routes', '/etc/nginx/routes'), mount(path / 'certs', '/etc/nginx/certs')],
            'healthcheck': {'test': ['CMD', 'nginx', '-t'], 'interval': '3s', 'timeout': '3s', 'retries': 10}},
        'docker-gen': {**limits, 'image': GENERATOR, 'network_mode': 'none',
            'command': ['-endpoint', 'unix:///tmp/docker.sock', '-watch', '-wait', '500ms:2s',
                '-notify-sighup', PROJECT + '-nginx-1', '/etc/docker-gen/routes.tmpl', '/etc/nginx/routes/routes.conf'],
            'depends_on': {'nginx': {'condition': 'service_healthy'}},
            'volumes': [mount(socket_path, '/tmp/docker.sock'),
                mount(path / 'routes.tmpl', '/etc/docker-gen/routes.tmpl'),
                mount(path / 'routes', '/etc/nginx/routes', False), mount(path / 'certs', '/etc/nginx/certs')]}},
        'networks': {'ingress': {'external': True, 'name': NETWORK}}}
    return model


def validate_configuration(safe):
    path = state(safe)
    require((path / 'compose.json').is_file() and not (path / 'compose.json').is_symlink(),
            'Run explicit ./bin/ingress setup first')
    require(json.loads((path / 'compose.json').read_text()) == configuration(safe),
            'Unknown/changed ingress configuration or Docker context; inspect privately, then explicitly setup')
    for name in ('nginx.conf', 'routes.tmpl'):
        require((path / name).is_file() and not (path / name).is_symlink() and
                (path / name).read_bytes() == (ROOT / 'etc/docker/ingress' / name).read_bytes(),
                'Ingress source/configuration changed; run explicit setup and up before app startup')


def setup(safe):
    path = state(safe)
    require(not (path / 'compose.json').exists() or
            json.loads((path / 'compose.json').read_text()).get('x-fight-owner') == str(safe),
            'Unknown ingress configuration; inspect it before setup')
    network_exists = owned(safe)
    model = configuration(safe)
    for name in ('routes', 'certs'):
        target = path / name
        require(target.resolve() == target, 'Aliased ingress directory refused')
        target.mkdir(parents=True, exist_ok=True, mode=0o700)
    for name in ('nginx.conf', 'routes.tmpl'):
        write_private(path / name, (ROOT / 'etc/docker/ingress' / name).read_text())
    if not (path / 'routes/routes.conf').exists():
        write_private(path / 'routes/routes.conf', '# No application gateways enrolled\n')
    write_private(path / 'compose.json', json.dumps(model, indent=2) + '\n')
    # Native syntax validation before the only external setup effect.
    text([*compose(safe), 'config', '--quiet'], safe)
    if not network_exists:
        text(['docker', 'network', 'create', '--label', OWNER + '=' + str(safe), NETWORK], safe)
    print('Prepared explicit ingress configuration/network. No listeners, certificates, trust or applications changed.')


def ready(safe):
    validate_configuration(safe)
    require(owned(safe),
            'Shared ingress absent; human: ./bin/ingress setup, then ./bin/ingress up --bind-ports')
    ids = text(['docker', 'ps', '-q', '--filter', 'label=com.docker.compose.project=' + PROJECT], safe).splitlines()
    items = query(['docker', 'inspect', *ids], safe) if ids else []
    require({v['Config']['Labels'].get('com.docker.compose.service') for v in items} == {'nginx', 'docker-gen'},
            'Shared ingress services not running; operate ./bin/ingress explicitly')
    require(all(v['State']['Running'] and v['State'].get('Health', {}).get('Status', 'healthy') == 'healthy' for v in items),
            'Shared ingress services are not healthy')
    text([*compose(safe), 'exec', '-T', 'nginx', 'nginx', '-t'], safe)


def live(safe, host):
    check_leaf(safe, host)
    for suffix in ('/', '/api/v1/auth/csrf'):
        text(['curl', '--fail', '--silent', '--show-error', '--connect-timeout', '3', '--max-time', '10',
              '--resolve', host + ':443:127.0.0.1', '--output', '/dev/null', 'https://' + host + suffix], safe)


def operate(safe, action, bind_ports=False):
    require((state(safe) / 'compose.json').is_file(), 'Run explicit ./bin/ingress setup first')
    owned(safe)
    if action == 'up':
        require(bind_ports, 'Human operation required: ./bin/ingress up --bind-ports (loopback 80/443)')
        validate_configuration(safe)
        # Check native listeners too; own running listeners are retained on refresh.
        running = text(['docker', 'ps', '-q', '--filter', 'label=com.docker.compose.project=' + PROJECT], safe)
        if not running:
            for port in (80, 443):
                with socket.socket() as listener:
                    try:
                        listener.bind(('127.0.0.1', port))
                    except OSError:
                        raise Hold('Port ' + str(port) + ' unavailable; operator must resolve it without foreign teardown')
        require(owned(safe), 'Ingress network missing; run explicit setup')
        # Check every installed leaf before listener mutation, including expiry/key/CA.
        for leaf in sorted((state(safe) / 'certs').glob('*.localhost')):
            check_leaf(safe, leaf.name)
        run([*compose(safe), 'up', '--detach', '--force-recreate', '--wait', '--wait-timeout', '60'], safe, env=environment())
        ready(safe)
        text([*compose(safe), 'exec', '-T', 'nginx', 'nginx', '-s', 'reload'], safe)
        print('Ingress services ready; application URLs require their own verified root/CSRF readiness.')
    elif action == 'status':
        ready(safe)
        config = text([*compose(safe), 'exec', '-T', 'nginx', 'nginx', '-T'], safe)
        hosts = sorted(set(re.findall(r'^    server_name ([a-z0-9-]+\.localhost);$', config, re.M)))
        for host in hosts:
            live(safe, host)
        print(json.dumps({'services_ready': True, 'verified_application_origins': ['https://' + v for v in hosts],
                          'trust_client': 'curl default store', 'config': str(state(safe) / 'compose.json')}))
    else:
        validate_configuration(safe)
        run([*compose(safe), 'down', '--remove-orphans'], safe, env=environment())
        require(not text(['docker', 'ps', '-aq', '--filter', 'label=com.docker.compose.project=' + PROJECT], safe),
                'Ingress containers remain; cleanup incomplete')
        if action == 'remove' and owned(safe):
            # Native rm refuses any remaining app/foreign network attachment.
            run(['docker', 'network', 'rm', NETWORK], safe)
        print('Ingress stopped; applications, leaves, CA trust and foreign resources preserved.' +
              (' Network removed; use setup before restarting.' if action == 'remove' else ' Network retained.'))


def certificate_operation(root, action, selector):
    safe, checkout, project, host = consumer(root, selector)
    directory = certificates(safe, host)
    if action == 'setup':
        # Explicit human invocation only. Preflight capabilities before any leaf/CA writes.
        # Never mkcert -install, DNS or .env edits here.
        openssl_tool()
        text(['mkcert', '-version'], safe)
        ca_root = Path(text(['mkcert', '-CAROOT'], safe))
        ca = ca_root / 'rootCA.pem'
        require(ca.is_file() and not ca.is_symlink(), 'Enroll mkcert CA trust explicitly in your terminal first')
        shared_ca = state(safe) / 'certs/rootCA.pem'
        if shared_ca.exists():
            require(shared_ca.read_bytes() == ca.read_bytes(), 'Shared CA differs; coordinate all consumers before rotation')
        directory.mkdir(parents=True, exist_ok=True, mode=0o700)
        require(all(not (directory / name).is_symlink() for name in ('localhost.pem', 'localhost-key.pem')),
                'Aliased leaf/key refused before generation')
        write_private(shared_ca, ca.read_text())
        run(['mkcert', '-cert-file', directory / 'localhost.pem', '-key-file', directory / 'localhost-key.pem', host], safe)
        for name in ('localhost.pem', 'localhost-key.pem'):
            (directory / name).chmod(0o600)
        check_leaf(safe, host)
        print('Actual-name leaf prepared for ' + host + '; no trust/DNS/origin change. Refresh ingress and the selected app explicitly.')
    elif action == 'status':
        check_leaf(safe, host)
        ready(safe)
        live(safe, host)
        print('Verified exact-name HTTPS root/CSRF with curl default trust: https://' + host)
    elif action == 'remove':
        # Do not break a live route by unlinking keys beneath Nginx.
        if owned(safe):
            attached = query(['docker', 'network', 'inspect', NETWORK], safe)[0].get('Containers') or {}
            for item in attached.values():
                current = query(['docker', 'inspect', item['Name']], safe)[0]
                require('VIRTUAL_HOST=' + host not in current['Config']['Env'], 'Stop the selected app before removing its leaf')
        for name in ('localhost.pem', 'localhost-key.pem'):
            leaf = directory / name
            require(not leaf.is_symlink(), 'Aliased certificate refused')
            leaf.unlink(missing_ok=True)
        print('Removed only ' + host + ' leaf/key; shared CA and trust preserved.')
    else:
        raise Hold('Ingress certificates support setup, status and remove; operate services with bin/ingress')


def main():
    os.umask(0o077)
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('action', choices=['setup', 'up', 'down', 'status', 'remove', 'certificate'])
    parser.add_argument('--bind-ports', action='store_true')
    parser.add_argument('--certificate-action', choices=['setup', 'status', 'remove'])
    parser.add_argument('--worktree')
    args = parser.parse_args()
    if args.action == 'certificate':
        require(args.certificate_action, 'Certificate action required')
        certificate_operation(ROOT, args.certificate_action, args.worktree)
    else:
        require(not args.worktree, 'Ingress is shared, not a worktree service')
        safe, _, _, _ = resolve(ROOT)
        if args.action == 'setup':
            setup(safe)
        else:
            operate(safe, args.action, args.bind_ports)
    return 0


if __name__ == '__main__':
    try:
        sys.exit(main())
    except (Hold, OSError, ValueError, KeyError, subprocess.SubprocessError):
        error = sys.exc_info()[1]
        print('Ingress operation incomplete: ' + (str(error) if isinstance(error, Hold) else
              'native tool/configuration unavailable; inspect privately, partial resources may remain'), file=sys.stderr)
        sys.exit(1)
