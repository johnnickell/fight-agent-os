"""Connect an explicitly selected project worktree to the shared ingress."""
import json
import os
from pathlib import Path
import re

from resource_lifecycle.preparation import resolve
from resource_lifecycle.resources import query, require, run, text, write_private


def target(root, selector):
    require(root.is_absolute() and root == root.resolve() and root.is_dir(),
            'Consumer root must be an exact canonical checkout path')
    safe, checkout, project, entry = resolve(root, selector)
    require(safe == root, 'Consumer root must be the canonical primary checkout')
    if selector is not None:
        require(entry and checkout != safe and str(checkout) == selector and checkout == checkout.resolve(),
                'Expected an exact registered isolated consumer checkout path')
        require((checkout / 'etc/local-tooling.json').is_file(), 'Selected checkout has no local tooling profile')
    return safe, checkout, project


def up(supplier, root, selector):
    from local_ingress import NETWORK, NGINX, check_leaf, live, ready

    require(selector is not None, 'Project preview requires an exact --worktree path')
    safe, checkout, project = target(root, selector)
    profile = json.loads((checkout / 'etc/local-tooling.json').read_text()).get('https_preview')
    require(isinstance(profile, dict), 'Selected project has no HTTPS preview profile')
    service, network, port = profile['service'], profile['network'], profile['port']
    require(all(isinstance(name, str) and re.fullmatch(r'[a-z0-9][a-z0-9-]*', name) for name in (service, network))
            and type(port) is int and 1 <= port <= 65535, 'Invalid project preview profile')

    host = project + '.localhost'
    ready(supplier)
    certs = check_leaf(supplier, host)
    command = [checkout / 'bin/runtime', 'docker', '--worktree', str(checkout), 'compose']
    model = query([*command, 'config', '--format=json'], checkout)
    backend = model['services'].get(service, {})
    app_network = model.get('networks', {}).get(network, {})
    require(backend.get('labels', {}).get('io.fight.checkout') == str(checkout)
            and network in backend.get('networks', {})
            and app_network.get('name') == project + '_' + network
            and any(binding.get('target') == port for binding in backend.get('ports', [])),
            'Preview profile must select the checkout-owned HTTP service, private network and port')

    running = text([*command, 'ps', '--status', 'running', '--services'], checkout).splitlines()
    require(service in running, 'Start the selected worktree with its repository bin/up before preview')

    state = safe / 'var/worktrees' / project
    require(state.is_dir() and state == state.resolve(), 'Missing or aliased consumer runtime state')
    gateway = state / 'ingress-preview'
    config = gateway / 'config'
    require(config == config.resolve(), 'Aliased project gateway configuration')
    # Bind the directory so an atomic config rewrite never changes the mount inode.
    write_private(config / 'nginx.conf', f'''pid /tmp/nginx.pid;
error_log stderr;
events {{}}
http {{
    access_log off;
    client_body_temp_path /tmp/client;
    proxy_temp_path /tmp/proxy;
    fastcgi_temp_path /tmp/fastcgi;
    uwsgi_temp_path /tmp/uwsgi;
    scgi_temp_path /tmp/scgi;
    server {{
        listen 8443 ssl;
        server_name {host};
        ssl_certificate /etc/nginx/certs/localhost.pem;
        ssl_certificate_key /etc/nginx/certs/localhost-key.pem;
        if ($http_host != "{host}") {{ return 421; }}
        if ($ssl_server_name != "{host}") {{ return 421; }}
        location / {{
            resolver 127.0.0.11 valid=5s ipv6=off;
            set $backend http://{service}:{port};
            proxy_pass $backend;
            proxy_set_header Host $http_host;
        }}
    }}
}}
''')

    def bind(source, destination):
        return {'type': 'bind', 'source': str(source), 'target': destination, 'read_only': True,
                'bind': {'create_host_path': False}}

    overlay = gateway / 'compose.json'
    write_private(overlay, json.dumps({
        'services': {'fight-https-preview': {
            'image': NGINX, 'user': f'{os.getuid()}:{os.getgid()}',
            'entrypoint': ['nginx'], 'command': ['-c', '/etc/fight-preview/nginx.conf', '-g', 'daemon off;'],
            'read_only': True, 'tmpfs': ['/tmp:rw,nosuid,noexec,size=32m'], 'cap_drop': ['ALL'],
            'security_opt': ['no-new-privileges:true'], 'mem_limit': '128m', 'cpus': 0.5, 'pids_limit': 64,
            'labels': {'io.fight.checkout': str(checkout)},
            'environment': {'FIGHT_LOCAL_INGRESS': '1',
                            'VIRTUAL_HOST': host, 'VIRTUAL_PROTO': 'https', 'VIRTUAL_PORT': '8443'},
            'networks': [network, 'fight-ingress'],
            'volumes': [bind(config, '/etc/fight-preview'), bind(certs, '/etc/nginx/certs')],
        }},
        'networks': {'fight-ingress': {'external': True, 'name': NETWORK}},
    }) + '\n')
    run([*command, '--file', overlay, 'up', '--detach', '--no-deps', '--force-recreate',
         'fight-https-preview'], checkout)
    live(supplier, host, retry=True, preview=True)
    print('Project preview ready: https://' + host)
