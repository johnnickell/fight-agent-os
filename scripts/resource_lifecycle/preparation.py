"""Resolve registered checkouts and generate explicit disposable Compose overrides."""
import hashlib
import json
import os
from pathlib import Path
import re
import secrets

from .resources import Hold, query, registrations, require, text, write_private


def identity(safe, checkout):
    # Always include the checkout hash: adding a second same-task checkout cannot
    # rename a running project. Branch changes do not change a checkout's identity.
    key = hashlib.sha256((str(safe) + '\0' + str(checkout)).encode()).hexdigest()[:12]
    return 'fight-wt-' + key


def matches(selector, entry):
    branch = entry['branch']
    return selector in (entry['path'], branch, Path(entry['path']).name) or (
        re.fullmatch(r'task-\d{5}', selector) and
        (branch == 'feature/' + selector or branch.startswith('feature/' + selector + '-')))


def resolve(root, selector=None, removed=False):
    entries = registrations(root)
    safe = Path(entries[0]['path'])
    if selector is None:
        return safe, safe, 'fight-agent-os', None
    found = [v for v in entries if matches(selector, v)]
    if len(found) > 1:
        raise Hold('Ambiguous worktree; use its exact registered path or full branch')
    if found:
        checkout = Path(found[0]['path'])
        return safe, checkout, identity(safe, checkout), found[0]
    if removed:
        candidates = []
        for path in (safe / 'var/worktrees').glob('fight-wt-*/locator.json'):
            entry = json.loads(path.read_text())
            if matches(selector, entry):
                require(entry['project'] == identity(safe, Path(entry['path'])), 'Invalid teardown locator')
                candidates.append(entry)
        require(len(candidates) <= 1, 'Ambiguous removed worktree; use its exact original path')
        if candidates:
            entry = candidates[0]
            return safe, Path(entry['path']), entry['project'], entry
        # No locator or native project: a repeated cleanup is already complete.
        return safe, None, None, None
    raise Hold('No matching registered worktree: ' + selector)


def environment(isolated=False):
    env = os.environ.copy()
    # Explicit file/project/env selection must not inherit ambient Compose modes.
    for key in list(env):
        if key.startswith('COMPOSE_') or (isolated and key.startswith(('APP_', 'POSTGRES_', 'FIGHT_TASK_'))):
            env.pop(key)
    return env


def compose(safe, checkout, project, isolated=False, shared=False):
    args = ['docker', 'compose', '--project-directory', str(checkout), '--project-name', project,
            '--file', str(checkout / 'compose.yaml')]
    if isolated:
        state = safe / 'var/worktrees' / project
        args += ['--env-file', str(state / 'task.env'), '--file', str(state / 'override.yaml')]
        if (state / 'ingress.yaml').exists():
            args += ['--file', str(state / 'ingress.yaml')]
    else:
        args += ['--env-file', str(checkout / '.env') if (checkout / '.env').exists() else '/dev/null']
        if shared:
            args += ['--file', str(safe / 'var/local-ingress/main/ingress.yaml')]
    return args


def gateway(root, host):
    template = (root / 'etc/docker/https/default.conf').read_text()
    return template.replace('localhost:18443', host).replace('SERVER_PORT 18443', 'SERVER_PORT 443').replace(
        'SERVER_NAME localhost', 'SERVER_NAME ' + host).replace('server_name localhost', 'server_name ' + host)


def override(services):
    lines = ['services:']
    for name, settings in services.items():
        lines += ['  ' + name + ':', '    ports: !reset []']
        lines += ['    ' + key + ': ' + json.dumps(value) for key, value in settings.items()]
    return '\n'.join(lines) + '\n'


def output_path(safe, checkout, project, value):
    candidate = Path(value)
    for original, target in ((checkout / 'var', safe / 'var/worktrees' / project / 'output'),
                             (checkout / '.runs', safe / '.runs/compose' / project)):
        if candidate == original or original in candidate.parents:
            return target / candidate.relative_to(original)
    return candidate


def prepare(safe, checkout, project, entry):
    state = safe / 'var/worktrees' / project
    state.mkdir(parents=True, exist_ok=True, mode=0o700)
    require(state.resolve() == state, 'Runtime state alias refused')
    host = project + '.localhost'
    private = state / 'task.env'
    if not private.exists():
        values = {'APP_ENV': 'local', 'APP_DEBUG': 'false', 'APP_AUTH_TRANSPORT': 'direct',
                  'APP_AUTH_TRUSTED_PROXIES': '[]', 'APP_BROWSER_ORIGIN': 'https://' + host,
                  'APP_ACTIVATION_ORIGIN': 'https://' + host, 'APP_ACTIVATION_PATH': '/app/activate',
                  'APP_PASSWORD_RESET_ORIGIN': 'https://' + host, 'APP_PASSWORD_RESET_PATH': '/app/reset-password/complete',
                  'APP_CREDENTIAL_DELIVERY_ADAPTER': 'null', 'POSTGRES_DB': 'agent_os',
                  'POSTGRES_USER': 'agent_os', 'POSTGRES_PASSWORD': secrets.token_hex(24),
                  'POSTGRES_TEST_DB': 'agent_os_test', 'POSTGRES_TEST_USER': 'agent_os_test',
                  'POSTGRES_TEST_PASSWORD': secrets.token_hex(24), 'FIGHT_AGENT_OS_USER': f'{os.getuid()}:{os.getgid()}'}
        for key in ('APP_CSRF_MAC_KEY', 'APP_AUTH_RATE_KEY', 'APP_ACTIVATION_RATE_KEY', 'APP_CREDENTIAL_DELIVERY_KEY'):
            values[key] = secrets.token_hex(32)
        values['APP_ACCESS_TOKEN_CONFIG'] = json.dumps({'algorithm': 'HS256', 'issuer': 'https://' + host,
            'audience': 'agent-os-api', 'active_kid': 'local',
            'keys': [{'kid': 'local', 'secret_hex': secrets.token_hex(32)}]}, separators=(',', ':'))
        write_private(private, ''.join(key + "='" + value + "'\n" for key, value in values.items()))
    require(not private.is_symlink(), 'Private environment alias refused')
    # Worktree source and its ignored output remain separate from main. Compose
    # resolves inherited relative mounts/build contexts against THIS checkout.
    # Nested mount targets must exist beneath the read-only source bind. These
    # are ignored empty directories, not dependency installs or generated source.
    for relative in ('.runs', 'var', 'client/node_modules/.cache', 'client/node_modules/.vite-temp'):
        (checkout / relative).mkdir(parents=True, exist_ok=True)
    output = state / 'output'
    evidence = safe / '.runs/compose' / project
    for path in (output, output / 'client/build', evidence / 'cache/client/dependency-cache', evidence / 'cache/client/vite-temp'):
        path.mkdir(parents=True, exist_ok=True)
    mounts = [{'type': 'bind', 'source': str(output), 'target': '/app/var'},
              {'type': 'bind', 'source': str(evidence), 'target': '/app/.runs'},
              {'type': 'bind', 'source': str(evidence / 'cache/client/dependency-cache'), 'target': '/app/client/node_modules/.cache'},
              {'type': 'bind', 'source': str(evidence / 'cache/client/vite-temp'), 'target': '/app/client/node_modules/.vite-temp'}]
    # A registered worktree's .git file contains an absolute common-dir locator.
    # Tooling must read that SAME native metadata; never copy it or expose writes.
    git_common = Path(text(['git', 'rev-parse', '--path-format=absolute', '--git-common-dir'], checkout))
    mounts.append({'type': 'bind', 'source': str(git_common), 'target': str(git_common),
                   'read_only': True, 'bind': {'create_host_path': False}})
    services = {'web': {'image': project + '-web', 'volumes': mounts},
                'composer': {'image': project + '-web', 'volumes': mounts},
                'https_php': {'image': project + '-https_php', 'volumes': mounts[:1]},
                'https': {'volumes': [{'type': 'bind', 'source': str(output / 'client/build'),
                                       'target': '/app/var/client/build', 'read_only': True}]}, 'mailcatcher': {}}
    for name, settings in services.items():
        settings['labels'] = {'io.fight.checkout': str(checkout), 'io.fight.runtime': 'worktree'}
    write_private(state / 'override.yaml', override(services))
    write_private(state / 'locator.json', json.dumps({**entry, 'project': project}, indent=2) + '\n')
    args = compose(safe, checkout, project, True)
    model = query([*args, '--profile', '*', 'config', '--format', 'json'], checkout, environment(True))
    # Teardown config contains no secrets or source/config dependencies. Native
    # Compose project labels discover all containers/orphans even after source removal.
    cleanup = {'services': {}, 'volumes': model.get('volumes', {}), 'networks': model.get('networks', {})}
    write_private(state / 'down.json', json.dumps(cleanup) + '\n')
    return args


def ingress(safe, checkout, project, isolated=True):
    """Consume the explicit supplier; never provision ingress, certificates or trust"""
    from local_ingress import check_leaf, ready
    ready(safe)
    host = project + '.localhost' if isolated else 'fight-agent-os.localhost'
    state = safe / ('var/worktrees/' + project if isolated else 'var/local-ingress/main')
    certs = check_leaf(safe, host)
    write_private(state / 'gateway.conf', gateway(checkout, host))
    settings = {'https': {'environment': {'FIGHT_LOCAL_INGRESS': '1', 'VIRTUAL_HOST': host,
                                         'VIRTUAL_PORT': '8443', 'VIRTUAL_PROTO': 'https'},
                  'networks': ['default', 'ingress'], 'volumes': [
                      {'type': 'bind', 'source': str(state / 'gateway.conf'), 'target': '/etc/nginx/conf.d/default.conf',
                       'read_only': True, 'bind': {'create_host_path': False}},
                      {'type': 'bind', 'source': str(certs), 'target': '/etc/nginx/certs',
                       'read_only': True, 'bind': {'create_host_path': False}}]}, 'web': {}, 'mailcatcher': {}}
    origin = {'APP_BROWSER_ORIGIN': 'https://' + host, 'APP_ACTIVATION_ORIGIN': 'https://' + host,
              'APP_PASSWORD_RESET_ORIGIN': 'https://' + host, 'APP_AUTH_TRANSPORT': 'direct',
              'APP_AUTH_TRUSTED_PROXIES': '[]'}
    settings['web']['environment'] = origin
    settings['https_php'] = {'environment': origin}
    config = override(settings) + 'networks:\n  ingress: {external: true, name: fight-local-ingress}\n'
    target = state / 'ingress.yaml'
    write_private(target, config)
    return target, 'https://' + host
