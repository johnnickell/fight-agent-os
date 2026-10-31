#!/usr/bin/env python3
"""After original Land exits, call the public lifecycle from its safe checkout"""
import argparse
import json
from pathlib import Path
import subprocess
import sys


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--attempt', required=True)
    parser.add_argument('--receipt', required=True)
    parser.add_argument('--apply', action='store_true')
    parser.add_argument('--finish', action='store_true')
    args = parser.parse_args()
    try:
        path, attempt = Path(args.receipt), Path(args.attempt)
        if not all(p.is_absolute() and p == p.resolve() for p in (path, attempt)):
            raise ValueError('canonical original paths required')
        doc = json.loads(path.read_bytes())
        root = Path(doc['safe'])
        if Path.cwd().resolve() != root or doc.get('land_attempt') != str(attempt):
            raise ValueError('wrong safe checkout/original Land attempt')
        if not (attempt / 'dispatch.lock').is_dir():
            raise ValueError('original monitor missing')
        for name in ('exit-code', 'pi-exit-code'):
            if not (attempt / name).read_text().strip().lstrip('-').isdigit():
                raise ValueError('original exit unavailable')
        original = json.loads((attempt / 'process.json').read_bytes())
        for pid in (original['monitor_pid'], original['pi_pid']):
            if not any(v['pid'] == pid and v['birth'] for v in doc['workers']):
                raise ValueError('original worker not bound')
            observed = subprocess.run(['ps', '-p', str(pid), '-o', 'lstart='], capture_output=True, timeout=15)
            if observed.returncode != 1 or observed.stdout.strip() or observed.stderr.strip():
                raise ValueError('original worker live/reused or visibility unavailable')
        action = 'inspect' if not args.apply else ('finish' if args.finish else 'retire')
        argv = [str(root / 'bin/runtime'), action, '--task', doc['task'], '--receipt', str(path)]
        if action == 'finish':
            argv += ['--outcome', doc['outcome']]
        # No source repair, publication, second runtime or internal state manipulation.
        return subprocess.run(argv, cwd=root, timeout=1800).returncode
    except (OSError, ValueError, KeyError, TypeError, subprocess.SubprocessError) as error:
        print(json.dumps({'complete': False, 'hold': str(error)}), file=sys.stderr)
        return 1


if __name__ == '__main__':
    sys.exit(main())
