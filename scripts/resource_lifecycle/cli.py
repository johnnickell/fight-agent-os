"""Deterministic local resource lifecycle; inspection never writes"""
import argparse
import json
import os
from pathlib import Path
import re
import subprocess
import sys

from .preparation import locator, paths, prepare
from .resources import (Hold, Retirement, absolute, atomic, load, locked, process, require, text)
from .runtime import Runtime, storage_admit


def record_path(root, task=None, receipt=None):
    if receipt:
        path = absolute(receipt)
    elif task:
        require(re.fullmatch(r'TASK-\d{5}', task), 'invalid TASK')
        safe = Path(text(['git', 'worktree', 'list', '--porcelain'], root).splitlines()[0][9:])
        path = safe / '.runs/tasks' / task / 'runs/runtime/resources.json'
    else:
        leaf = root / 'var/resource-lifecycle.json'
        if not leaf.exists():
            return None
        require(not leaf.is_symlink(), 'locator alias refused')
        path = absolute(json.loads(leaf.read_bytes())['receipt'])
    doc = load(path)
    require(not task or doc['task'] == task, 'wrong task record')
    require(Path(doc['safe']) / '.runs/tasks' / doc['task'] / 'runs' in path.parents, 'record outside owning task')
    return path


def complete(path, doc, args):
    outcome = absolute(args.outcome)
    require(outcome.is_file() and not outcome.is_symlink(), 'existing final outcome required')
    require(not any(Path(v['id']) == outcome or Path(v['id']) in outcome.parents for v in doc['temporary']),
            'final outcome must survive temporary disposal; record it in the existing TASK/PR')
    root = Path(doc['checkout'])
    oid = text(['git', 'rev-parse', doc['ref']], Path(doc['safe']))
    require(re.fullmatch(r'[0-9a-f]{40}', oid), 'exact source commit required')
    if args.cancel_oid:
        require(args.cancel_oid == oid and args.authority, 'explicit exact cancellation OID and original authority required')
        doc.update(discard_oid=oid, discard_authority=args.authority)
        doc.pop('published_oid', None)
    else:
        require(args.published_oid == oid and args.remote_ref == doc['ref'], 'exact published tip/ref required')
        from .publication import published
        published(doc, oid, args.remote_ref, args.pr)
        doc.update(published_oid=oid, remote_ref=args.remote_ref, pr=args.pr)
        doc.pop('discard_oid', None)
        doc.pop('discard_authority', None)
    doc.update(oid=oid, outcome=str(outcome), remove_checkout=root != Path(doc['safe']))
    atomic(path, doc)


def register(path, doc, args):
    attempt = absolute(args.attempt)
    store = Path(doc['safe']) / '.runs/build' / doc['task']
    require(store in attempt.parents and (attempt / 'dispatch.lock').is_dir(), 'original task monitor attempt required')
    observed = json.loads((attempt / 'process.json').read_bytes())
    workers = []
    for key in ('monitor_pid', 'pi_pid'):
        pid = observed[key]
        require(isinstance(pid, int) and pid > 1, 'invalid worker PID')
        birth = process(pid, Path(doc['safe']))
        require(birth, 'register actual live workers, not invented exit history')
        workers.append({'pid': pid, 'birth': birth})
    for worker in workers:
        if worker not in doc['workers']:
            doc['workers'].append(worker)
    if args.stage == 'land':
        doc['land_attempt'] = str(attempt)
    atomic(path, doc)


def main():
    os.umask(0o077)
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--checkout', default=os.getcwd())
    sub = parser.add_subparsers(dest='action', required=True)
    for name in ('prepare', 'inspect', 'retire', 'finish', 'register-worker', 'complete', 'reconcile'):
        entry = sub.add_parser(name)
        entry.add_argument('--task', required=True)
        entry.add_argument('--checkout', default=argparse.SUPPRESS)
        if name != 'prepare':
            entry.add_argument('--receipt')
        if name == 'prepare':
            entry.add_argument('--source-only', action='store_true')
        if name == 'finish':
            entry.add_argument('--outcome', required=True)
        if name == 'register-worker':
            entry.add_argument('--stage', choices=('work', 'review', 'qa', 'land'), required=True)
            entry.add_argument('--attempt', required=True)
        if name == 'complete':
            entry.add_argument('--outcome', required=True)
            disposition = entry.add_mutually_exclusive_group(required=True)
            disposition.add_argument('--cancel-oid')
            disposition.add_argument('--published-oid')
            entry.add_argument('--authority')
            entry.add_argument('--remote-ref')
            entry.add_argument('--pr')
    docker = sub.add_parser('docker')
    docker.add_argument('argv', nargs=argparse.REMAINDER)
    output = sub.add_parser('paths')
    output.add_argument('paths', nargs='+')
    admit = sub.add_parser('storage-admit')
    admit.add_argument('--protect-image', action='append', default=[])
    image = sub.add_parser('image-name')
    image.add_argument('name')
    args = parser.parse_args()
    try:
        root = absolute(args.checkout)
        if args.action == 'prepare':
            path = prepare(root, args.task, args.source_only)
            print(json.dumps({'task': args.task, 'record': str(path), 'project': load(path)['project']}))
            return 0
        path = record_path(root, getattr(args, 'task', None), getattr(args, 'receipt', None))
        if path is None:
            branch = text(['git', 'symbolic-ref', '--quiet', '--short', 'HEAD'], root, codes=(0, 1))
            require(not re.match(r'feature/task-\d{5}-', branch), 'prepare the selected TASK before wrappers')
            require(args.action in ('docker', 'paths', 'storage-admit'), 'no task binding')
            if args.action == 'docker':
                return subprocess.run(['docker', *args.argv], cwd=root).returncode
            if args.action == 'storage-admit':
                storage_admit(root)
            else:
                for value in args.paths:
                    (root / value).mkdir(parents=True, exist_ok=True)
            return 0
        if args.action == 'inspect':
            # No lock creation, atomic save, registration, reconciliation or mkdir.
            doc = load(path)
            report = {'task': doc['task'], 'project': doc['project'], 'record': str(path),
                      'pending': doc['pending'], 'workers': doc['workers'], 'binding_hold': None}
            try:
                Runtime(Path(doc['checkout']), path, check_docker=bool(doc['config_files']))
            except (Hold, OSError, ValueError, subprocess.SubprocessError) as error:
                report['binding_hold'] = str(error)
            print(json.dumps(report))
            os.chdir(doc['safe'])
            Retirement(path).run(runtime_only=not bool(doc.get('outcome')))
            return 1 if report['binding_hold'] or doc['pending'] or doc.get('preparing') else 0
        with locked(path):
            doc = load(path)
            if args.action in ('retire', 'finish'):
                require(root == Path(doc['safe']) or (args.action == 'retire' and root == Path(doc['checkout'])),
                        'finish must run from canonical safe checkout')
                os.chdir(doc['safe'])
                if args.action == 'finish':
                    require(doc.get('outcome') == str(absolute(args.outcome)) and Path(args.outcome).is_file(),
                            'existing validated completion outcome required before finish')
                return Retirement(path, doc, apply=True).run(runtime_only=args.action == 'retire', finish=args.action == 'finish')
            if args.action == 'complete':
                complete(path, doc, args)
            elif args.action == 'register-worker':
                register(path, doc, args)
            else:
                runtime = Runtime(root, path, check_docker=args.action in ('docker', 'reconcile', 'storage-admit'))
                if args.action == 'reconcile':
                    runtime.reconcile()
                elif args.action == 'docker':
                    return runtime.docker(args.argv)
                elif args.action == 'storage-admit':
                    storage_admit(root)
                elif args.action == 'image-name':
                    require(re.fullmatch(r'[a-z0-9.-]+', args.name), 'invalid image suffix')
                    print(doc['project'] + '-' + args.name)
                elif args.action == 'paths':
                    paths(path, runtime.doc, root, args.paths)
        return 0
    except (Hold, OSError, ValueError, KeyError, TypeError, subprocess.SubprocessError) as error:
        print(json.dumps({'complete': False, 'hold': str(error)}), file=sys.stderr)
        return 1


if __name__ == '__main__':
    sys.exit(main())
