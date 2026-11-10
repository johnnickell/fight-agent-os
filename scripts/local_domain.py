#!/usr/bin/env python3
"""Machine-local project registration; shared by CLI startup and future local Project creation."""
import argparse
from contextlib import contextmanager
import fcntl
import hashlib
import json
import os
from pathlib import Path
import re
import sys
import uuid

from resource_lifecycle.preparation import resolve
from resource_lifecycle.resources import Hold, require, text, write_private

NAMESPACE = 'local.fight-agent.dev'
ZONE_PATTERN = r'local\.(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}'
HOST_PATTERN = r'(?:task-[0-9]{5}-[a-f0-9]{10}\.)?' + ZONE_PATTERN


def namespace(host):
    require(isinstance(host, str) and len(host) <= 253 and re.fullmatch(HOST_PATTERN, host)
            and not host.endswith(('.test', '.localhost', '.local')), 'Expected a narrow local.{domain} hostname')
    return re.sub(r'^task-[0-9]{5}-[a-f0-9]{10}\.', '', host)


def domain_host(host):
    try:
        namespace(host)
        return True
    except Hold:
        return False


def directory(safe):
    target = safe / 'var/local-ingress/domains'
    require(target.resolve() == target, 'Aliased local-domain state refused')
    return target


def load(safe):
    path = directory(safe).parent / 'hosts.json'
    require(not path.is_symlink(), 'Aliased local-domain registry refused')
    data = json.loads(path.read_text()) if path.exists() else {'version': 1, 'projects': {}}
    require(data.get('version') == 1 and isinstance(data.get('projects'), dict), 'Unknown host registry; inspect before mutation')
    zones, hosts, ids = set(), set(), set()
    for root, project in data['projects'].items():
        zone = project['namespace']
        require(namespace(zone) == zone and isinstance(project['bindings'], dict)
                and Path(root).is_absolute(), 'Invalid registered project')
        require(not any(zone == v or zone.endswith('.' + v) or v.endswith('.' + zone) for v in zones),
                'Duplicate or overlapping project namespaces')
        zones.add(zone)
        for key, binding in project['bindings'].items():
            identifier = str(uuid.UUID(binding['id']))
            expected = zone if binding['task'] is None else hostname(binding['task'], identifier, zone)
            require(identifier == binding['id'] and binding['host'] == expected and expected not in hosts
                    and identifier not in ids and Path(key).as_posix() == key
                    and not Path(key).is_absolute() and '..' not in Path(key).parts,
                    'Invalid or conflicting Checkout registration')
            hosts.add(expected)
            ids.add(identifier)
    return data


def digest(data):
    return hashlib.sha256(json.dumps(data, sort_keys=True).encode()).hexdigest()


def native(checkout):
    common = Path(text(['git', 'rev-parse', '--path-format=absolute', '--git-common-dir'], checkout)).resolve()
    admin = Path(text(['git', 'rev-parse', '--absolute-git-dir'], checkout)).resolve()
    require(admin == common or common in admin.parents, 'Git administrative directory is outside this repository')
    marker = admin / 'fight-local-checkout-id'
    require(not marker.is_symlink(), 'Aliased Checkout identity refused')
    identifier = marker.read_text().strip() if marker.exists() else None
    if identifier is not None:
        require(str(uuid.UUID(identifier)) == identifier, 'Invalid Checkout UUID')
    return admin.relative_to(common).as_posix(), marker, identifier


def hostname(task, identifier, zone=NAMESPACE):
    require(isinstance(task, str) and re.fullmatch(r'TASK-[0-9]{5}', task), 'Expected an actual TASK-NNNNN identity')
    host = task.lower() + '-' + hashlib.sha256(identifier.encode('ascii')).hexdigest()[:10] + '.' + zone
    namespace(host)
    return host


def selection(root, selector=None):
    require(root.is_absolute() and root == root.resolve(), 'Select an exact canonical project root')
    safe, checkout, project, _ = resolve(root, selector)
    require(safe == root, 'Project root must be the canonical checkout')
    if selector is None and (safe / 'etc/local-tooling.json').is_file():
        project = json.loads((safe / 'etc/local-tooling.json').read_text())['project']
    return safe, checkout, project


def binding(safe, checkout):
    root, _, _, _ = resolve(checkout)
    project = load(safe)['projects'].get(str(root))
    if not project:
        return None
    key, _, identifier = native(checkout)
    result = project['bindings'].get(key)
    require(not result or result['id'] == identifier, 'Checkout identity changed; explicitly retire stale registration')
    return result


def selected_host(safe, checkout, fallback):
    registered = binding(safe, checkout)
    return registered['host'] if registered else fallback


@contextmanager
def locked(safe):
    state = directory(safe)
    state.mkdir(parents=True, exist_ok=True, mode=0o700)
    lock = state / 'bindings.lock'
    require(not lock.is_symlink(), 'Aliased registration lock refused')
    with lock.open('a') as handle:
        fcntl.flock(handle, fcntl.LOCK_EX)
        yield


def stopped(safe, project):
    ids = text(['docker', 'ps', '-q', '--filter', 'label=com.docker.compose.project=' + project], safe).splitlines()
    if ids:
        items = json.loads(text(['docker', 'inspect', *ids], safe))
        require(not any(v['Config']['Labels'].get('com.docker.compose.service') in ('https', 'fight-https-preview') for v in items),
                'Stop the selected HTTPS gateway before changing its canonical domain; preserve its data')


def task_identity(checkout, task):
    if task is None:
        branch = text(['git', 'branch', '--show-current'], checkout)
        match = re.match(r'^feature/(task-[0-9]{5})(?:-|$)', branch)
        task = match[1].upper() if match else None
    require(task and re.fullmatch(r'TASK-[0-9]{5}', task), 'Select --task TASK-NNNNN for this new Checkout registration')
    record = checkout / 'planning/tasks' / (task[5:] + '-TASK.md')
    require(record.is_file() and re.search(r'^id: ' + task + r'$', record.read_text(), re.MULTILINE),
            'Actual TASK record missing in selected checkout')
    return task


def desired_binding(checkout, primary, task, zone, identifier):
    task = None if primary else task_identity(checkout, task)
    identifier = identifier or str(uuid.uuid4())
    return {'id': identifier, 'task': task, 'host': zone if primary else hostname(task, identifier, zone)}


def persist(safe, data, root, checkout, desired, zone):
    key, marker, identifier = native(checkout)
    project = data['projects'].setdefault(str(root), {'namespace': zone, 'bindings': {}})
    if desired:
        require(not identifier or identifier == desired['id'], 'Checkout identity is immutable')
        for other_root, value in data['projects'].items():
            for other_key, other in value['bindings'].items():
                require((other_root == str(root) and other_key == key) or
                        (other['host'] != desired['host'] and other['id'] != desired['id']), 'Conflicting hostname or Checkout UUID')
        if not identifier:
            with marker.open('x') as handle:
                handle.write(desired['id'] + '\n')
        project['bindings'][key] = desired
    else:
        project['bindings'].pop(key, None)
    write_private(directory(safe).parent / 'hosts.json', json.dumps(data, indent=2) + '\n')


def plan(safe, root, selector, primary, task, remove, zone):
    root, checkout, project = selection(root, selector)
    data = load(safe)
    registered = data['projects'].get(str(root))
    zone = zone or (registered['namespace'] if registered else None)
    require(zone and namespace(zone) == zone, 'Initial enrollment requires --namespace local.{domain}')
    require(not registered or registered['namespace'] == zone, 'Retargeting an enrolled project namespace is not supported')
    require(not any(r != str(root) and (zone == p['namespace'] or zone.endswith('.' + p['namespace'])
                    or p['namespace'].endswith('.' + zone)) for r, p in data['projects'].items()), 'Namespace already owned or overlapping')
    key, _, identifier = native(checkout)
    current = binding(safe, checkout)
    require(not remove or current, 'Selected checkout has no registration')
    require(remove or primary != bool(task), 'Select either --primary or --task TASK-NNNNN')
    desired = None if remove else desired_binding(checkout, primary, task, zone, identifier)
    proposal = {'version': 1, 'owner': str(safe), 'root': str(root), 'checkout': str(checkout), 'project': project,
                'namespace': zone, 'git_admin': key, 'head': text(['git', 'rev-parse', 'HEAD'], checkout),
                'previous': digest(data), 'marker': identifier, 'desired': desired}
    path = directory(safe) / 'plans' / (str(uuid.uuid4()) + '.json')
    write_private(path, json.dumps(proposal, indent=2) + '\n')
    print(json.dumps({'plan': str(path), 'checkout': str(checkout), 'previous_host': current['host'] if current else None,
                      'next_host': desired['host'] if desired else None,
                      'effects': ['Register project namespace and Checkout UUID; enrolled bin/up subsequently maintains bindings'],
                      'separate_operations': ['DNS enrollment', 'Project leaf preparation', 'Supplier and app startup']}, indent=2))


def apply(safe, path):
    require(path.is_absolute() and path == path.resolve() and path.parent == directory(safe) / 'plans',
            'Apply requires the exact prepared local-domain plan path')
    proposal = json.loads(path.read_text())
    require(proposal['version'] == 1 and proposal['owner'] == str(safe), 'Foreign or unknown proposal')
    root = Path(proposal['root'])
    _, checkout, project = selection(root, None if proposal['checkout'] == str(root) else proposal['checkout'])
    with locked(safe):
        data = load(safe)
        key, _, identifier = native(checkout)
        require(digest(data) == proposal['previous'] and key == proposal['git_admin'] and project == proposal['project']
                and text(['git', 'rev-parse', 'HEAD'], checkout) == proposal['head'] and identifier == proposal['marker'],
                'Registrations or checkout changed since preview; prepare a fresh plan')
        desired, zone = proposal['desired'], proposal['namespace']
        require(namespace(zone) == zone, 'Invalid project namespace')
        require(all(r == str(root) or not (zone == p['namespace'] or zone.endswith('.' + p['namespace'])
                    or p['namespace'].endswith('.' + zone)) for r, p in data['projects'].items()), 'Conflicting namespace')
        require(str(root) not in data['projects'] or data['projects'][str(root)]['namespace'] == zone, 'Namespace changed')
        if desired:
            require(str(uuid.UUID(desired['id'])) == desired['id'] and desired == desired_binding(
                checkout, desired['task'] is None, desired['task'], zone, desired['id']), 'Invalid planned binding')
        if desired != binding(safe, checkout):
            stopped(safe, project)
        persist(safe, data, root, checkout, desired, zone)
        path.unlink()
    print('Local registration applied; DNS, trust, proxy and application state were not changed.')


def ensure(safe, root, selector=None, task=None):
    """Idempotently register a checkout of an explicitly enrolled local project"""
    root, checkout, project = selection(root, selector)
    with locked(safe):
        data = load(safe)
        registered = data['projects'].get(str(root))
        if registered is None:
            return None
        current = binding(safe, checkout)
        if current:
            require(not task or current['task'] == task, 'Checkout already belongs to a different TASK; preview a change explicitly')
            return current
        require(checkout != root, 'Assign the primary checkout explicitly with plan --primary')
        desired = desired_binding(checkout, False, task, registered['namespace'], native(checkout)[2])
        stopped(safe, project)
        persist(safe, data, root, checkout, desired, registered['namespace'])
        return desired


def main():
    os.umask(0o077)
    parser = argparse.ArgumentParser(description=__doc__)
    sub = parser.add_subparsers(dest='action', required=True)
    for action in ('plan', 'ensure'):
        entry = sub.add_parser(action)
        entry.add_argument('--project-root', type=Path)
        entry.add_argument('--worktree')
        entry.add_argument('--task')
        if action == 'plan':
            entry.add_argument('--primary', action='store_true')
            entry.add_argument('--remove', action='store_true')
            entry.add_argument('--namespace')
    commit = sub.add_parser('apply')
    commit.add_argument('plan', type=Path)
    sub.add_parser('status')
    sub.add_parser('install')
    args = parser.parse_args()
    source = Path(__file__).resolve().parent.parent
    safe, _, _, _ = resolve(source)
    if args.action == 'plan':
        require(not args.remove or (not args.primary and not args.task), 'Removal cannot also select a new name')
        plan(safe, args.project_root or safe, args.worktree, args.primary, args.task, args.remove, args.namespace)
    elif args.action == 'ensure':
        print(json.dumps(ensure(safe, args.project_root or safe, args.worktree, args.task)))
    elif args.action == 'apply':
        apply(safe, args.plan)
    elif args.action == 'install':
        from ingress_client import config_path
        path = config_path()
        require(not path.exists() or json.loads(path.read_text()) == {'supplier': str(source)},
                'Another local supplier is configured; inspect and explicitly remove that pointer before replacement')
        write_private(path, json.dumps({'supplier': str(source)}, indent=2) + '\n')
        print('Local supplier discovery: ' + str(path) + '\nRegistry: ' + str(directory(safe).parent / 'hosts.json'))
    else:
        print(json.dumps(load(safe), indent=2))
    return 0


if __name__ == '__main__':
    try:
        sys.exit(main())
    except (Hold, OSError, ValueError, KeyError) as error:
        print('Local-domain operation incomplete: ' + str(error), file=sys.stderr)
        sys.exit(1)
