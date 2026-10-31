#!/usr/bin/env python3
"""Select the trusted Harness package once for both execution and passive observation"""

import argparse
import json
import os
from pathlib import Path
import subprocess
import sys


def main():
    parser = argparse.ArgumentParser(add_help=False)
    parser.add_argument('--harness-package')
    parser.add_argument('--role')
    parser.add_argument('--task')
    parser.add_argument('--cleanup-receipt')
    args, _ = parser.parse_known_args()
    try:
        root = Path.cwd().resolve()
        runtime = root / 'bin/runtime'
        if args.cleanup_receipt:
            # Post-worker cleanup runs at the safe checkout, not in the retired
            # runtime. The owning continuation requalifies original exits/effects.
            supplied = Path(args.cleanup_receipt)
            receipt = supplied.resolve(strict=True)
            store = root / '.runs/tasks' / str(args.task) / 'runs'
            if (args.role != 'land' or not supplied.is_absolute() or supplied != receipt or store not in receipt.parents or
                    not receipt.is_file() or receipt.stat().st_uid != os.getuid() or receipt.stat().st_mode & 0o777 != 0o600):
                raise ValueError('invalid cleanup-only receipt')
            plan = json.loads(receipt.read_text())['cleanup']['plan']
            if plan['task'] != args.task or plan['safe_checkout'] != str(root) or not plan.get('original_land_attempt') or not plan.get('exited_workers'):
                raise ValueError('wrong original cleanup-only subject')
        elif runtime.is_file():
            binding = root / 'var/resource-lifecycle.json'
            if binding.exists():
                if json.loads(binding.read_text()).get('task') != args.task:
                    raise ValueError('lifecycle task mismatch')
                # Before dispatch, not after spawning a competing independent stage.
                checked = subprocess.run([str(runtime), 'validate', '--stage', 'build-intake-' + str(args.role)],
                                         cwd=root, timeout=120, check=False)
                if checked.returncode:
                    return checked.returncode
            elif args.role not in ('work', 'engineer'):
                print('Work lifecycle binding missing; hold advancement before dispatch', file=sys.stderr)
                return 1
        package = (Path(args.harness_package) if args.harness_package else
                   Path(__file__).resolve().parents[4] / 'harness/pi').resolve(strict=True)
        monitor = (package / 'src/observation/run-stage.py').resolve(strict=True)
        if monitor.parent != package / 'src/observation':
            raise ValueError('monitor outside package')
        # The package owns the fixed live process identity. No target skill, route or
        # witness supplies a provider path, and exec preserves ordinary ancestry.
        forwarded = list(sys.argv[1:])
        if args.cleanup_receipt:
            index = next(index for index, value in enumerate(forwarded) if value == '--cleanup-receipt' or value.startswith('--cleanup-receipt='))
            del forwarded[index:index + (2 if forwarded[index] == '--cleanup-receipt' else 1)]
        os.execv(sys.executable, [sys.executable, '-u', '-B', str(monitor), *forwarded])
    except (OSError, ValueError, KeyError, TypeError, subprocess.SubprocessError):
        print('Stage monitor unavailable in selected trusted Harness package; update that owned package explicitly before dispatch', file=sys.stderr)
        return 1


if __name__ == '__main__':
    sys.exit(main())
