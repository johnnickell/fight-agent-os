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
    env = dict(os.environ, NO_COLOR='1', PI_OFFLINE='1')
    env.pop('FORCE_COLOR', None)
    return subprocess.run(['pi', *arguments], stdin=subprocess.DEVNULL,
                          capture_output=True, text=True, timeout=15, env=env)


def harness_owner(path):
    """Identify the owning Harness through canonical native resource paths"""
    for parent in (path, *path.parents):
        manifest = parent / 'package.json'
        if manifest.is_file():
            metadata = json.loads(manifest.read_text())
            if not isinstance(metadata, dict):
                raise ValueError('Invalid configured package manifest')
            if metadata.get('name') == '@fight/agent-os-pi':
                return parent
    return None


def configured_harness():
    """Inspect native packages and enabled resources, including direct discovery"""
    helper = Path(__file__).resolve().with_name('harness-inventory.ts')
    result = native_pi('--offline', '-e', str(helper), '--help')
    if result.returncode or result.stderr.strip():
        raise ValueError('Pi resource inventory unavailable; inspect native Pi settings')
    prefix = 'FIGHT_HARNESS_INVENTORY='
    records = [line[len(prefix):] for line in result.stdout.splitlines() if line.startswith(prefix)]
    if len(records) != 1:
        raise ValueError('Unsupported Pi resource inventory; expected one native inventory record')
    inventory = json.loads(records[0])
    if (not isinstance(inventory, dict) or not isinstance(inventory.get('packages'), list)
            or not isinstance(inventory.get('extensions'), list)
            or not isinstance(inventory.get('projectTrusted'), bool)):
        raise ValueError('Unsupported Pi resource inventory')
    packages, resources = set(), set()
    for entry in inventory['packages']:
        path = Path(entry['installedPath'])
        if not path.is_absolute():
            raise ValueError('Pi package inventory lacks an absolute installed path')
        path = path.resolve(strict=True)
        if harness_owner(path) == path:
            if entry['scope'] == 'project' and not inventory['projectTrusted']:
                raise ValueError('Project Harness configuration is not natively trusted; inspect native project trust')
            if entry['filtered']:
                raise ValueError('Configured Harness is filtered; qualify its resource selection before dispatch')
            packages.add(path)
    for entry in inventory['extensions']:
        if not isinstance(entry['enabled'], bool):
            raise ValueError('Unsupported native resource state')
        if entry['path'].startswith('builtin:'):
            continue
        path = Path(entry['path'])
        if not path.is_absolute():
            raise ValueError('Pi resource inventory lacks an absolute installed path')
        path = path.resolve(strict=True)
        owner = harness_owner(path)
        if owner:
            if entry['metadata']['scope'] == 'project' and not inventory['projectTrusted']:
                raise ValueError('Project Harness configuration is not natively trusted; inspect native project trust')
            packages.add(owner)
            if entry['enabled']:
                resources.add(path)
    if len(packages) > 1:
        raise ValueError('Multiple configured Harness packages or resource owners; reconcile native Pi settings')
    selected = next(iter(packages), None)
    if selected:
        manifest = json.loads((selected / 'package.json').read_text())
        pi = manifest.get('pi')
        extensions = pi.get('extensions') if isinstance(pi, dict) else None
        if not isinstance(extensions, list) or not all(isinstance(path, str) for path in extensions):
            raise ValueError('Configured Harness extension manifest unavailable')
        expected = {(selected / path).resolve(strict=True) for path in extensions}
        if resources != expected:
            raise ValueError('Configured Harness resources are incomplete or disabled; inspect native Pi settings')
    return selected


def select_package(explicit):
    """Keep explicit and automatically discovered package identities consistent"""
    configured = configured_harness()
    selected = (Path(explicit) if explicit else configured or
                Path(__file__).resolve().parents[4] / 'harness/pi').resolve(strict=True)
    if configured is not None and selected != configured:
        raise ValueError('Explicit Harness conflicts with the configured package; inspect native Pi settings')
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
        # Exercise native registration with the same package injection used by enrolled screening.
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
