"""Native Compose entrypoints with explicit main/worktree selection."""
import argparse
import json
import os
from pathlib import Path
import subprocess
import sys

from .preparation import output_path
from .resources import Hold, native_ids, require
from .runtime import command, down, finish, selected, storage_admit, up


def main():
    os.umask(0o077)
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--worktree', help='registered path, branch or unambiguous task-NNNNN')
    sub = parser.add_subparsers(dest='action', required=True)
    for name in ('up', 'down', 'inspect'):
        entry = sub.add_parser(name)
        entry.add_argument('--worktree', default=argparse.SUPPRESS)
        if name == 'up':
            entry.add_argument('--build-only', action='store_true')
    docker = sub.add_parser('docker')
    docker.add_argument('argv', nargs=argparse.REMAINDER)
    paths = sub.add_parser('paths')
    paths.add_argument('paths', nargs='+')
    storage = sub.add_parser('storage-admit')
    storage.add_argument('--protect-image', action='append', default=[])
    image = sub.add_parser('image-name')
    image.add_argument('name')
    remove = sub.add_parser('finish')
    remove.add_argument('--worktree', required=True)
    remove.add_argument('--outcome', required=True)
    remove.add_argument('--published-oid', required=True)
    remove.add_argument('--pr', required=True)
    args = parser.parse_args()
    root = Path.cwd().resolve()
    try:
        if args.action == 'up':
            return up(root, args.worktree, args.build_only)
        if args.action == 'down':
            return down(root, args.worktree)
        if args.action == 'finish':
            return finish(root, args.worktree, args.outcome, args.published_oid, args.pr)
        if args.action == 'docker':
            require(args.argv, 'Missing Docker command')
            return command(root, args.argv, args.worktree)
        safe, checkout, project, entry, isolated = selected(root, args.worktree)
        if args.action == 'inspect':
            print(json.dumps({'checkout': str(checkout), 'project': project,
                'resources': {kind: native_ids(safe, kind, project) for kind in ('containers', 'volumes', 'networks')}}))
        elif args.action == 'storage-admit':
            storage_admit(checkout)
        elif args.action == 'image-name':
            print(project + '-' + args.name if isolated else 'fight-agent-os-client-browser:node24.22.1-playwright1.61.0')
        elif args.action == 'paths':
            for value in args.paths:
                target = checkout / value
                require(checkout in target.resolve().parents, 'Output path must remain beneath selected checkout')
                if isolated:
                    target = output_path(safe, checkout, project, target)
                target.mkdir(parents=True, exist_ok=True)
        return 0
    except (Hold, OSError, ValueError, KeyError, subprocess.SubprocessError) as error:
        print('Runtime operation incomplete: ' + str(error), file=sys.stderr)
        return 1


if __name__ == '__main__':
    sys.exit(main())
