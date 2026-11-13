"""Reopen one fixed TASK revision using native Worktrunk and Compose operations."""
import argparse
from contextlib import contextmanager
import fcntl
import json
import os
from pathlib import Path
import re
import shlex
import shutil
import signal
import subprocess
import sys

from qa_setup import scenario_path, setup, supported
from resource_lifecycle.preparation import identity, prepare, resolve
from resource_lifecycle.resources import Hold, native_ids, query, registrations, require, run, text, write_private
from resource_lifecycle.worktrunk import remove

ROOT = Path(__file__).resolve().parent.parent
QA_REF = re.compile(r'qa/(task-\d{5})/([0-9a-f]{40})')


def location(safe, task, oid):
    return safe.parent / '.worktrees' / ('qa-' + task.lower() + '-' + oid)


def task_record(task):
    require(re.fullmatch(r'TASK-\d{5}', task), 'Expected exact TASK-NNNNN')
    matches = [path for path in (ROOT / 'planning/tasks').rglob(task[5:] + '-TASK.md') if path.is_file()]
    require(len(matches) == 1, 'Unknown or ambiguous TASK; no allocation performed')
    contents = matches[0].read_text()
    require(contents.startswith('---\n') and re.search(r'^id: ' + task + r'$', contents, re.M), 'Invalid TASK identity')
    metadata = contents.split('---', 2)[1]
    return dict(line.split(':', 1) for line in metadata.splitlines() if ':' in line)


def revision(safe, task, ref):
    metadata = task_record(task)
    if ref is None:
        pr = metadata.get('pr', '').strip()
        origin = text(['git', 'remote', 'get-url', 'origin'], safe)
        repository = re.fullmatch(r'(?:git@github\.com:|https://github\.com/)([^/]+/[^/]+?)(?:\.git)?', origin)
        require(repository is not None and re.fullmatch(r'https://github\.com/' + re.escape(repository[1]) + r'/pull/[1-9]\d*', pr),
                'No unambiguous TASK-owned publication; use --ref REVISION')
        proof = query(['gh', 'pr', 'view', pr, '--json',
                       'url,title,state,headRefName,headRefOid,baseRefName'], safe)
        require(proof['url'] == pr and proof['title'].startswith(task + ' — ') and
                re.fullmatch(r'feature/' + task.lower() + r'(?:-[a-z0-9-]+)?', proof['headRefName']) and
                proof['baseRefName'] == 'develop' and proof['state'] in ('OPEN', 'MERGED') and
                re.fullmatch(r'[0-9a-f]{40}', proof['headRefOid']),
                'Publication identity/state ambiguous; use --ref REVISION')
        ref = proof['headRefOid']
        source = pr
    else:
        require(ref and not ref.startswith('-') and not any(c.isspace() for c in ref), 'Invalid explicit revision')
        source = '--ref ' + ref
    local = subprocess.run(['git', 'rev-parse', '--verify', '--end-of-options', ref + '^{commit}'],
                           cwd=safe, capture_output=True, text=True)
    if local.returncode:
        if re.fullmatch(r'[0-9a-f]{40}', ref):
            oid = ref
        else:
            rows = text(['git', 'ls-remote', 'origin', ref, 'refs/heads/' + ref,
                         'refs/tags/' + ref, 'refs/tags/' + ref + '^{}'], safe).splitlines()
            refs = dict(row.split()[::-1] for row in rows)
            # A peeled tag is the same choice, not another matching revision.
            for name in list(refs):
                if name + '^{}' in refs:
                    refs.pop(name)
            require(len(refs) == 1, 'Unavailable or ambiguous remote revision; use an exact full OID')
            oid = next(iter(refs.values()))
        require(re.fullmatch(r'[0-9a-f]{40}', oid), 'Invalid remote revision')
        run(['git', 'fetch', '--no-tags', 'origin', oid], safe, capture=True)
        oid = text(['git', 'rev-parse', '--verify', '--end-of-options', oid + '^{commit}'], safe)
    else:
        require(not local.stderr.strip(), 'Ambiguous local revision; use a full commit OID')
        oid = local.stdout.strip()
    require(re.fullmatch(r'[0-9a-f]{40}', oid), 'Revision did not resolve to a full commit OID')
    return oid, source


def environments(safe):
    """Native QA refs/registrations plus existing interruption/teardown locators"""
    entries = {}
    registered = registrations(safe)
    for entry in registered:
        match = QA_REF.fullmatch(entry['branch'])
        if match:
            require(Path(entry['path']) == location(safe, *match.groups()), 'QA ref registered at an unexpected path; inspect ownership')
            entries[entry['path']] = {**entry, 'task': match[1].upper(), 'revision': match[2]}
    for locator in (safe / 'var/worktrees').glob('fight-wt-*/locator.json'):
        entry = json.loads(locator.read_text())
        match = QA_REF.fullmatch(entry.get('branch', ''))
        if match:
            checkout = location(safe, *match.groups())
            require(entry['path'] == str(checkout) and entry['project'] == identity(safe, checkout) and
                    locator.parent.name == entry['project'], 'Invalid QA teardown locator')
            entries.setdefault(entry['path'], {**entry, 'task': match[1].upper(), 'revision': match[2]})
    # Worktrunk can leave an intentionally retained unmerged ref without source.
    # It is reported separately rather than mistaken for a running environment.
    for row in text(['git', 'for-each-ref', '--format=%(refname:short) %(objectname)', 'refs/heads/qa/'], safe).splitlines():
        branch, actual = row.split()
        match = QA_REF.fullmatch(branch)
        require(match is not None, 'Unknown QA-purpose ref; inspect native Git inventory')
        checkout = location(safe, *match.groups())
        entries.setdefault(str(checkout), {'path': str(checkout), 'branch': branch,
                                          'task': match[1].upper(), 'revision': match[2],
                                          'ref_revision': actual, 'ref_only': True})
    return list(entries.values())


def retired(safe, entry):
    """Distinguish a protected ref annotation from a live or partial session"""
    if not entry.get('ref_only') or entry['ref_revision'] != entry['revision']:
        return False
    checkout = Path(entry['path'])
    project = identity(safe, checkout)
    state_directory = safe / 'var/worktrees' / project
    if (checkout.exists() or checkout.is_symlink() or state_directory.exists() or state_directory.is_symlink() or
            any(e['path'] == entry['path'] or e['branch'] == entry['branch'] for e in registrations(safe))):
        return False
    if any(native_ids(safe, kind, project) for kind in ('containers', 'volumes', 'networks')):
        return False
    if any(tag.startswith(project + '-') for tag in text([
            'docker', 'image', 'ls', '--format', '{{.Repository}}:{{.Tag}}'], safe).splitlines()):
        return False
    if project in text(['docker', 'buildx', 'ls', '--format', '{{.Name}}'], safe).splitlines():
        return False
    return not text(['docker', 'volume', 'ls', '-q', '--filter',
                     'name=^buildx_buildkit_' + project + '0_state$'], safe)


def state(safe, entry):
    project = identity(safe, Path(entry['path']))
    ids = native_ids(safe, 'containers', project)
    containers = query(['docker', 'inspect', *ids], safe) if ids else []
    # Do not serialize inspected environments, mounts, or private config.
    services = {c['Config']['Labels'].get('com.docker.compose.service'): c for c in containers}
    expected = {'web', 'https', 'https_php', 'postgres', 'postgres_test', 'mailcatcher'}
    complete = expected <= services.keys() and all(c['State']['Running'] and
        c['State'].get('Health', {}).get('Status', 'healthy') == 'healthy' for c in services.values())
    checkout = Path(entry['path'])
    actual = text(['git', 'rev-parse', 'HEAD'], checkout) if checkout.exists() else None
    registered = any(e['path'] == entry['path'] and e['branch'] == entry['branch'] for e in registrations(safe))
    ready = registered and actual == entry['revision'] and scenario_path(safe, project).is_file()
    status = 'partial' if any(c['State']['Running'] for c in containers) else 'stopped'
    url = None
    if complete and ready:
        from local_ingress import live, ready as ingress_ready
        ingress_ready(safe)
        from local_domain import selected_host, domain_host
        host = selected_host(safe, Path(entry['path']), project + '.localhost')
        live(safe, host)
        for form in ('invitation_activation', 'login'):
            text(['curl', '--noproxy', '*', '--fail', '--silent', '--show-error', '--connect-timeout', '3',
                  '--max-time', '10', *([] if domain_host(host) else ['--resolve', host + ':443:127.0.0.1']), '--output', '/dev/null',
                  'https://' + host + '/api/v1/validations/' + form], safe)
        status, url = 'running', 'https://' + host
    elif not actual or not ready:
        status = 'partial'
    if entry.get('ref_only'):
        actual = actual or entry['ref_revision']
        status = 'stopped (retained ref only)' if retired(safe, entry) else 'partial'
    return {'task': entry['task'], 'revision': entry['revision'], 'actual_revision': actual,
            'checkout': entry['path'], 'project': project, 'status': status, 'url': url,
            'down': shlex.join([str(ROOT / 'bin/qa'), 'down', entry['path']])}


@contextmanager
def serialize(safe):
    # An OS advisory lock, not a session record/PID gate. Kernel releases it on
    # interruption; list never opens it or mutates state. One lock covers up/down.
    directory = safe / 'var/worktrees'
    directory.mkdir(parents=True, exist_ok=True)
    require(directory.resolve() == directory, 'Aliased runtime state refused')
    path = directory / '.qa.lock'
    descriptor = os.open(path, os.O_CREAT | os.O_RDWR | os.O_NOFOLLOW, 0o600)
    with os.fdopen(descriptor, 'w') as stream:
        fcntl.flock(stream, fcntl.LOCK_EX)
        yield


def creation_hooks(safe, oid=None, checkout=None):
    phases = ('pre-switch', 'pre-start', 'post-start', 'post-switch')
    for directory in dict.fromkeys((safe, checkout or safe)):
        for phase in phases:
            require(query(['wt', 'hook', 'show', phase, '--format=json'], directory) == [],
                    'Unqualified effective Worktrunk ' + phase + ' hook; no host execution authorized')
    if oid:
        config = subprocess.run(['git', 'show', oid + ':.config/wt.toml'], cwd=safe, capture_output=True, text=True)
        require(config.returncode == 0, 'Unsupported revision: missing Worktrunk hook policy')
        require(config.stdout == (ROOT / '.config/wt.toml').read_text(),
                'Unsupported revision: changed project hooks; no host execution authorized')
    # git worktree add invokes post-checkout independently of Worktrunk hooks.
    configured = subprocess.run(['git', 'config', '--get', 'core.hooksPath'], cwd=safe,
                                capture_output=True, text=True)
    require(configured.returncode in (0, 1) and
            (configured.returncode == 1 or Path(configured.stdout.strip()).is_absolute()),
            'Relative/unavailable Git hooksPath cannot be qualified before allocation')
    hooks = text(['git', 'rev-parse', '--path-format=absolute', '--git-path', 'hooks'], safe)
    hook = Path(hooks) / 'post-checkout'
    require(not hook.exists() or not os.access(hook, os.X_OK), 'Executable Git post-checkout hook; no host execution authorized')


def allocate(safe, task, oid):
    checkout = location(safe, task, oid)
    branch = 'qa/' + task.lower() + '/' + oid
    require(checkout.parent.resolve() == checkout.parent and not checkout.exists() and not checkout.is_symlink(),
            'QA path occupied/aliased; never adopt/reuse unknown source')
    existing_ref = text(['git', 'for-each-ref', '--format=%(refname) %(objectname)', 'refs/heads/' + branch], safe)
    require(not existing_ref or existing_ref == 'refs/heads/' + branch + ' ' + oid,
            'QA ref changed; unique work retained')
    require(not any(e['branch'] == branch for e in registrations(safe)), 'QA ref already has a registered owner')
    project = identity(safe, checkout)
    entry = {'path': str(checkout), 'branch': branch, 'project': project}
    # The existing locator precedes native creation so abrupt partial allocation
    # remains discoverable, even if Git registration or source is not completed.
    state_directory = safe / 'var/worktrees' / project
    require(state_directory.resolve() == state_directory, 'QA locator directory alias refused')
    write_private(state_directory / 'locator.json', json.dumps(entry) + '\n')
    argv = ['wt', 'switch', branch, '--path', checkout, '--no-cd', '--format=json']
    if not existing_ref:
        argv += ['--create', '--base', oid]
    try:
        run(argv, safe, capture=True)
        require(any(e['path'] == str(checkout) and e['branch'] == branch for e in registrations(safe)) and
                text(['git', 'rev-parse', 'HEAD'], checkout) == oid, 'Worktrunk did not allocate the exact QA revision')
        creation_hooks(safe, checkout=checkout)
        from local_domain import ensure
        ensure(safe, safe, str(checkout), task)
        # Explicit qa up already owns disposable installation preparation.
        prepare(safe, checkout, project, entry, initialize=True)
    except (Exception, KeyboardInterrupt):
        cleanup_failed(safe, {'path': str(checkout), 'task': task, 'revision': oid})
        raise
    return checkout, project


def cleanup_failed(safe, entry):
    try:
        dispose(safe, entry)
    except (Exception, KeyboardInterrupt) as error:
        if isinstance(error, Hold):
            print('Cleanup hold: ' + str(error), file=sys.stderr)
        print('Owned startup cleanup incomplete; retry: ' + shlex.join([
            str(ROOT / 'bin/qa'), 'down', entry['path']]), file=sys.stderr)


def dispose(safe, entry):
    checkout = Path(entry['path'])
    expected = entry['revision']
    # Always Docker first, including dirty/unique/hook-protected source. The SAME
    # shared remover then rechecks native source/hooks/ref policy before removal.
    run([safe / 'bin/down', '--worktree', checkout], safe)
    if checkout.exists():
        require(any(e['path'] == str(checkout) and
                    e['branch'] == 'qa/' + entry['task'].lower() + '/' + expected for e in registrations(safe)),
                'QA registration changed; source/dependencies retained after Docker teardown')
        require(text(['git', 'rev-parse', 'HEAD'], checkout) == expected and
                not text(['git', 'status', '--porcelain', '--untracked-files=all'], checkout),
                'Dirty/unique QA source retained; Docker teardown completed')
        # Only known dependency installations are disposable. Other private files
        # stay protected by the shared remover; never blanket git clean/force.
        for relative in ('vendor', 'client/node_modules'):
            target = checkout / relative
            require(not target.is_symlink(), 'Aliased dependency output retained')
            if target.exists():
                shutil.rmtree(target)
        for directory, dirs, files in os.walk(checkout, topdown=False):
            path = Path(directory)
            if path == checkout or files or path.is_symlink():
                continue
            try:
                path.rmdir()
            except OSError:
                pass
    result = remove(safe, str(checkout), expected, qa_revision=True)
    # Role reports live elsewhere. Retire only this QA project's reserved tool
    # cache after successful source/config closeout, never another role's report.
    evidence = safe / '.runs/compose' / identity(safe, checkout)
    require(evidence.resolve() == evidence, 'QA execution cache alias retained')
    if evidence.exists():
        shutil.rmtree(evidence)
    return result


def up(safe, task, ref):
    oid, source = revision(safe, task, ref)
    checkout = location(safe, task, oid)
    project = identity(safe, checkout)
    print(json.dumps({'selected_revision': oid, 'source': source, 'planned_checkout': str(checkout),
                      'project': project}), flush=True)
    supported(ROOT, oid)
    creation_hooks(safe, oid)
    with serialize(safe):
        # Unchanged refs left by completed non-force removal are annotations,
        # not exclusive sessions. Native leftovers, changed refs and registered
        # (even physically missing) owners still block switching or allocation.
        existing = [e for e in environments(safe) if e['task'] == task and not retired(safe, e)]
        require(len(existing) <= 1, 'Ambiguous QA sessions; list and down an exact checkout')
        if existing:
            require(existing[0]['revision'] == oid, 'A different QA revision exists; down its exact checkout before up')
            require(existing[0].get('ref_revision', oid) == oid, 'QA ref changed; unique work retained')
        if existing:
            entry = existing[0]
            observed = state(safe, entry)
            require(observed['status'] == 'running', 'Existing QA environment is stopped/partial; use its exact down command, then up')
        else:
            from local_ingress import check_leaf, ready
            ready(safe)
            try:
                from local_domain import load
                enrolled = load(safe)['projects'].get(str(safe))
                check_leaf(safe, enrolled['namespace'] if enrolled else project + '.localhost')
            except Hold as error:
                raise Hold(str(error) + '; before allocation, explicitly prepare the exact leaf: ' + shlex.join([
                    str(ROOT / 'bin/https'), 'setup', '--ingress', '--worktree', str(checkout)])) from error
            entry = {'path': str(checkout), 'branch': 'qa/' + task.lower() + '/' + oid,
                     'task': task, 'revision': oid}
            checkout, project = allocate(safe, task, oid)
            try:
                setup(ROOT, safe, checkout, project)
                observed = state(safe, entry)
                require(observed['status'] == 'running', 'Application/scenario readiness incomplete')
            except (Exception, KeyboardInterrupt):
                # Only this attempt's deterministic allocation. Never dispose an
                # existing user's QA environment because a reuse probe failed.
                cleanup_failed(safe, entry)
                raise
        print(json.dumps({**observed, 'scenario': str(scenario_path(safe, project)),
                          'scenario_state': 'activate only a pending synthetic account; no password printed'}))
    return 0


def down(safe, selector):
    with serialize(safe):
        entries = environments(safe)
        matches = [e for e in entries if selector in (e['path'], e['branch'], e['task'], Path(e['path']).name)]
        require(len(matches) <= 1, 'Ambiguous QA selector; use the exact checkout path')
        if matches:
            entry = matches[0]
        else:
            # After locator retirement only the exact original QA path is a
            # repeatable cleanup selector. Never redirect it to an engineering ref.
            path = Path(selector)
            match = re.fullmatch(r'qa-(task-\d{5})-([0-9a-f]{40})', path.name)
            require(path.is_absolute() and match and path == location(safe, *match.groups()),
                    'No matching QA environment; use the exact original QA checkout path')
            entry = {'path': str(path), 'revision': match[2], 'task': match[1].upper()}
        return dispose(safe, entry)


def main():
    os.umask(0o077)
    parser = argparse.ArgumentParser(description=__doc__)
    sub = parser.add_subparsers(dest='action', required=True)
    start = sub.add_parser('up')
    start.add_argument('task')
    start.add_argument('--ref')
    sub.add_parser('list')
    stop = sub.add_parser('down')
    stop.add_argument('selector')
    args = parser.parse_args()
    safe, _, _, _ = resolve(ROOT)
    if args.action == 'up':
        return up(safe, args.task, args.ref)
    if args.action == 'down':
        return down(safe, args.selector)
    entries = environments(safe)
    failed = False
    # Inventory is mandatory even for an empty native registration result.
    try:
        text(['docker', 'info', '--format', '{{.ID}}'], safe)
    except (Hold, OSError, subprocess.SubprocessError):
        print(json.dumps({'inventory': 'unavailable', 'environments': [
            {'task': e['task'], 'revision': e['revision'], 'checkout': e['path'], 'status': 'unavailable',
             'down': shlex.join([str(ROOT / 'bin/qa'), 'down', e['path']])} for e in entries]}))
        return 1
    rows = []
    for entry in entries:
        try:
            rows.append(state(safe, entry))
        except (Hold, OSError, ValueError, subprocess.SubprocessError):
            failed = True
            rows.append({'task': entry['task'], 'revision': entry['revision'], 'checkout': entry['path'],
                         'status': 'unavailable', 'url': None,
                         'down': shlex.join([str(ROOT / 'bin/qa'), 'down', entry['path']])})
    print(json.dumps({'inventory': 'incomplete' if failed else 'available', 'environments': rows}))
    return 1 if failed else 0


if __name__ == '__main__':
    def interrupted(signum, frame):
        raise KeyboardInterrupt()
    signal.signal(signal.SIGTERM, interrupted)
    try:
        sys.exit(main())
    except (Hold, OSError, ValueError, KeyError, subprocess.SubprocessError) as error:
        print('QA operation incomplete: ' + (str(error) if isinstance(error, Hold) else
              'native tooling/setup unavailable; inspect privately and use bin/qa list'), file=sys.stderr)
        sys.exit(1)
    except KeyboardInterrupt:
        print('QA operation interrupted; inspect bin/qa list and retry exact down', file=sys.stderr)
        sys.exit(130)
