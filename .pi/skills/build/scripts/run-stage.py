#!/usr/bin/env python3
"""Select the trusted Harness package once for both execution and passive observation"""

import argparse
import json
import os
from pathlib import Path
import subprocess
import sys


def native_pi(*arguments):
    """Inspect native Pi startup without provider requests or interactive input"""
    env = dict(os.environ, NO_COLOR='1')
    env.pop('FORCE_COLOR', None)
    return subprocess.run(['pi', *arguments], stdin=subprocess.DEVNULL,
                          capture_output=True, text=True, timeout=15, env=env)


def configured_harness():
    """Select a single Harness from the native Pi package inventory"""
    result = native_pi('list')
    if result.returncode or result.stderr.strip():
        raise ValueError('Pi package inventory unavailable; inspect pi list from this checkout')
    output = result.stdout.strip()
    if output == 'No packages installed.':
        return None
    packages = set()
    source = None
    saw_heading = False
    for line in result.stdout.splitlines():
        if not line.strip():
            continue
        if line in ('User packages:', 'Project packages:'):
            if source is not None:
                raise ValueError('Configured package has no installed path; inspect pi list')
            saw_heading = True
        elif line.startswith('    ') and source is not None:
            path = Path(line[4:])
            if not path.is_absolute():
                raise ValueError('Pi package inventory lacks an absolute installed path')
            path = path.resolve(strict=True)
            manifest = path / 'package.json'
            if path.is_dir() and manifest.is_file():
                metadata = json.loads(manifest.read_text())
                if not isinstance(metadata, dict):
                    raise ValueError('Invalid configured package manifest')
                if metadata.get('name') == '@fight/agent-os-pi':
                    if source.endswith(' (filtered)'):
                        raise ValueError('Configured Harness is filtered; qualify its resource selection before dispatch')
                    packages.add(path)
            source = None
        elif saw_heading and line.startswith('  ') and not line.startswith('    ') and source is None:
            source = line[2:]
        else:
            raise ValueError('Unsupported Pi package inventory; inspect pi list before dispatch')
    if source is not None or not saw_heading:
        raise ValueError('Incomplete Pi package inventory; inspect pi list before dispatch')
    if len(packages) > 1:
        raise ValueError('Multiple configured Harness packages; reconcile pi list before dispatch')
    return next(iter(packages), None)


def select_package(explicit):
    """Keep explicit and automatically discovered package identities consistent"""
    configured = configured_harness()
    selected = (Path(explicit) if explicit else configured or
                Path(__file__).resolve().parents[4] / 'harness/pi').resolve(strict=True)
    if configured is not None and selected != configured:
        raise ValueError('Explicit Harness conflicts with the configured package; inspect pi list')
    manifest = json.loads((selected / 'package.json').read_text())
    if not isinstance(manifest, dict) or manifest.get('name') != '@fight/agent-os-pi':
        raise ValueError('Selected package is not the Fight Harness')
    return selected


def main():
    parser = argparse.ArgumentParser(add_help=False, allow_abbrev=False)
    parser.add_argument('--harness-package')
    parser.add_argument('--role')
    parser.add_argument('--task')
    args, _ = parser.parse_known_args()
    try:
        # Docker lifecycle is independent of trusted native session/role admission.
        # A historical TASK receipt or PID cannot prevent a fresh independent role.
        package = select_package(args.harness_package)
        monitor = (package / 'src/observation/run-stage.py').resolve(strict=True)
        if monitor.parent != package / 'src/observation':
            raise ValueError('monitor outside package')
        # Package inventory omits direct and auto-discovered extensions. Exercise native
        # registration with the same package injection used by enrolled screening.
        # Pi can exit zero despite registration conflicts, so diagnostics also hold.
        registration = native_pi('--offline', '-e', str(package), '--help')
        if registration.returncode or registration.stderr.strip():
            raise ValueError('Native Harness registration unavailable or conflicting; inspect native Pi extension settings')
        # The package owns the fixed live process identity. No target skill, route or
        # witness supplies a provider path, and exec preserves ordinary ancestry.
        forwarded = list(sys.argv[1:])
        os.execv(sys.executable, [sys.executable, '-u', '-B', str(monitor), *forwarded])
    except (OSError, ValueError, KeyError, TypeError, subprocess.SubprocessError) as error:
        print(f'Stage monitor unavailable: {error}. No child started; inspect the configured Harness and its monitor support', file=sys.stderr)
        return 1


if __name__ == '__main__':
    sys.exit(main())
