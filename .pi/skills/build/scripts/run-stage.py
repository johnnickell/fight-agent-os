#!/usr/bin/env python3
"""Select the trusted Harness package once for both execution and passive observation"""

import argparse
import os
from pathlib import Path
import sys


def main():
    parser = argparse.ArgumentParser(add_help=False)
    parser.add_argument('--harness-package')
    args, _ = parser.parse_known_args()
    try:
        package = (Path(args.harness_package) if args.harness_package else
                   Path(__file__).resolve().parents[4] / 'harness/pi').resolve(strict=True)
        monitor = (package / 'src/observation/run-stage.py').resolve(strict=True)
        if monitor.parent != package / 'src/observation':
            raise ValueError('monitor outside package')
        # The package owns the fixed live process identity. No target skill, route or
        # witness supplies a provider path, and exec preserves ordinary ancestry.
        os.execv(sys.executable, [sys.executable, '-u', '-B', str(monitor), *sys.argv[1:]])
    except (OSError, ValueError):
        print('Stage monitor unavailable in selected trusted Harness package; update that owned package explicitly before dispatch', file=sys.stderr)
        return 1


if __name__ == '__main__':
    sys.exit(main())
