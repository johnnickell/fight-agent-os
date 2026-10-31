"""Bind one selected checkout using reviewed project configuration and owned artifacts"""
import json
import os
from pathlib import Path
import re
import secrets

from .resources import (KINDS, absolute, atomic, engine, fingerprint, inventory, locked, load,
                        path_identity, query, require, text)
from .runtime import Runtime, binding, compose


def owned(path, authority, empty_only=False):
    return {'id': str(path), 'identity': path_identity(path), 'disposable': True,
            'authority': authority, 'empty_only': empty_only}


def directories(path):
    missing = []
    while not path.exists():
        missing.append(path)
        path = path.parent
    created = []
    for candidate in reversed(missing):
        candidate.mkdir(mode=0o700)
        created.append(owned(candidate, 'Lifecycle-created empty parent', True))
    return created


def paths(record, doc, root, values):
    for value in values:
        candidate = root / value
        require(candidate.resolve() == candidate and root in candidate.parents, 'canonical path beneath selected checkout required')
        created = directories(candidate)
        if created:
            # Only an explicitly requested NEW output leaf owns its contents. Never
            # turn var/, .runs/ or a dependency installation into a disposable tree.
            created[-1]['empty_only'] = candidate in (root / 'var', root / '.runs', root / 'client/node_modules')
            doc['temporary'].extend(created)
            atomic(record, doc)


def locator(record, doc):
    root = Path(doc['checkout'])
    leaf = root / 'var/resource-lifecycle.json'
    expected = {'version': 2, 'task': doc['task'], 'receipt': str(record), 'checkout': str(root)}
    if leaf.exists():
        require(not leaf.is_symlink() and json.loads(leaf.read_bytes()) == expected, 'locator conflicts with selected binding')
        if not any(v['id'] == str(leaf) for v in doc['temporary']):
            # The prior v2 creator wrote exactly this locator. Qualify only this
            # known metadata file, never its pre-existing var/ parent or old history.
            doc['temporary'].append(owned(leaf, 'Exact lifecycle locator; current-task continuation disposal'))
            atomic(record, doc)
        return
    doc['temporary'].extend(directories(leaf.parent))
    atomic(leaf, expected)
    doc['temporary'].append(owned(leaf, 'Lifecycle-created exact locator'))
    atomic(record, doc)


def prepare(root, task, source_only=False):
    require(re.fullmatch(r'TASK-\d{5}', task), 'invalid TASK')
    branch = text(['git', 'symbolic-ref', 'HEAD'], root)
    require(re.fullmatch('refs/heads/feature/task-' + task[5:] + '-[a-z0-9-]+', branch), 'wrong task branch')
    safe = absolute(text(['git', 'worktree', 'list', '--porcelain'], root).splitlines()[0][9:])
    record = safe / '.runs/tasks' / task / 'runs/runtime/resources.json'
    require(root == safe or root not in record.parents, 'record must be outside disposable checkout')
    leaf = root / 'var/resource-lifecycle.json'
    if leaf.exists():
        loc = json.loads(leaf.read_bytes())
        require(loc['task'] == task and loc['checkout'] == str(root), 'another task owns this checkout')
        record = absolute(loc['receipt'])
        require(safe / '.runs/tasks' / task / 'runs' in record.parents, 'record outside task scope')
    parents = directories(record.parent)
    with locked(record):
        if record.exists():
            runtime = Runtime(root, record, check_docker=False)
            require(runtime.doc['task'] == task and runtime.doc['safe'] == str(safe), 'task binding conflicts')
            require(not runtime.doc.get('preparing'), 'preparation interrupted; original binding retained, no duplicate allocation')
            require(bool(runtime.doc['config_files']) != source_only, 'existing source-only/runtime mode conflicts; reuse original binding')
            locator(record, runtime.doc)
            return record
        project = 'task' + task[5:] + '-' + secrets.token_hex(6)
        doc = {'version': 2, 'task': task, 'safe': str(safe), 'checkout': str(root), 'ref': branch,
               'oid': text(['git', 'rev-parse', 'HEAD'], root), 'project': project, 'engine': None,
               'config_files': [], 'env_files': [], 'services': {},
               'authority': 'Approved ' + task + ' temporary local workload and standing owned disposal',
               'resources': {kind: [] for kind in KINDS}, 'temporary': [], 'record_parents': parents,
               'workers': [], 'pending': None, 'preparing': True}
        # Persist allocation identity before creating generated artifacts or external resources.
        doc['inputs'] = {}
        atomic(record, doc)
        locator(record, doc)
        if not source_only:
            require((root / 'compose.yaml').is_file(), 'reviewed compose.yaml required; use --source-only for Git-only work')
            output = record.parent / 'outputs'
            for name in ('runs', 'var', 'cache', 'vite'):
                paths(record, doc, safe, [str((output / name).relative_to(safe))])
            private = record.parent / 'task.env'
            require(not private.exists(), 'unexpected private configuration; do not overwrite')
            host = 'https://localhost:18443'
            env = {'FIGHT_TASK_CHECKOUT': str(root), 'FIGHT_TASK_OUTPUT': str(output),
                   'FIGHT_AGENT_OS_USER': f'{os.getuid()}:{os.getgid()}',
                   'POSTGRES_DB': project.replace('-', '_'), 'POSTGRES_USER': project.replace('-', '_'),
                   'POSTGRES_PASSWORD': secrets.token_hex(24),
                   'POSTGRES_TEST_DB': 'task' + task[5:] + '_test', 'POSTGRES_TEST_USER': 'task' + task[5:] + '_test',
                   'POSTGRES_TEST_PASSWORD': secrets.token_hex(24), 'APP_ENV': 'local', 'APP_DEBUG': 'false',
                   'APP_BROWSER_ORIGIN': host, 'APP_CREDENTIAL_DELIVERY_ADAPTER': 'null',
                   'APP_ACTIVATION_ORIGIN': host, 'APP_ACTIVATION_PATH': '/app/activate',
                   'APP_PASSWORD_RESET_ORIGIN': host, 'APP_PASSWORD_RESET_PATH': '/app/reset-password'}
            for key in ('APP_CSRF_MAC_KEY', 'APP_AUTH_RATE_KEY', 'APP_ACTIVATION_RATE_KEY', 'APP_CREDENTIAL_DELIVERY_KEY'):
                env[key] = secrets.token_hex(32)
            env['APP_ACCESS_TOKEN_CONFIG'] = json.dumps({'algorithm': 'HS256', 'issuer': host, 'audience': 'agent-os-api',
                'active_kid': 'task', 'keys': [{'kid': 'task', 'secret_hex': secrets.token_hex(32)}]}, separators=(',', ':'))
            with private.open('x') as stream:
                stream.write(''.join(k + "='" + v.replace("'", "\\'") + "'\n" for k, v in env.items()))
            doc['temporary'].append(owned(private, 'Lifecycle-generated disposable local test configuration'))
            doc['config_files'] = [str(root / 'compose.yaml'), str(Path(__file__).resolve().with_name('task.compose.yaml'))]
            doc['env_files'] = [str(private)]
            doc['services'] = {name: 'disposable' for name in ('web', 'composer', 'postgres', 'postgres_test', 'https', 'https_php', 'mailcatcher', 'oneoff')}
            doc['engine'] = engine(root)
            model = query([*compose(doc), '--profile', '*', 'config', '--format', 'json'], root)
            require(set(model['services']) <= set(doc['services']), 'unreviewed services; no automatic data classification')
            for kind in ('containers', 'networks', 'volumes'):
                require(not any((v.get('Config', {}).get('Labels') or v.get('Labels') or {}).get('com.docker.compose.project') == project
                                for v in inventory(root, kind)), 'project already allocated; do not adopt it')
            doc['resolved_sha256'] = fingerprint(model)
        doc['inputs'] = binding(doc)
        doc.pop('preparing', None)
        atomic(record, doc)
    return record
