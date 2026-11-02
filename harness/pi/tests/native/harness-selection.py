"""Qualify stage package selection with native Pi inventory and disposable operator state

Run from the provider checkout: python3 harness/pi/tests/native/harness-selection.py
No provider requests, enrollment, operator settings changes or agent sessions.
"""
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile

provider = Path.cwd().resolve()
launcher = provider / '.pi/skills/build/scripts/run-stage.py'
assert shutil.which('pi'), 'Installed Pi required'
scratch = provider / '.runs/harness-selection'
scratch.mkdir(parents=True, exist_ok=True)
with tempfile.TemporaryDirectory(dir=scratch) as directory:
    root = Path(directory)
    home, agent, target = (root / name for name in ('home', 'agent', 'target'))
    for path in (home, agent, target):
        path.mkdir()
    env = dict(os.environ, HOME=str(home), PI_CODING_AGENT_DIR=str(agent), PI_OFFLINE='1',
               NO_COLOR='1', PYTHONDONTWRITEBYTECODE='1')
    env.pop('FORCE_COLOR', None)
    packages = []
    for name in ('first harness', 'second harness'):
        package = root / name
        (package / 'src/observation').mkdir(parents=True)
        (package / 'package.json').write_text(json.dumps({'name': '@fight/agent-os-pi', 'pi': {'extensions': []}}))
        (package / 'src/observation/run-stage.py').write_text('print(' + repr(str(package)) + ')\n')
        packages.append(package)
    alias = root / 'same-package-alias'
    alias.symlink_to(packages[0], target_is_directory=True)
    cases = []

    def check(name, configured, expected=None, error=None, explicit=None, extensions=None):
        (agent / 'settings.json').write_text(json.dumps({'packages': configured, 'extensions': extensions or []}))
        command = [sys.executable, str(launcher), '--help']
        if explicit is not None:
            command.extend(['--harness-package', str(explicit)])
        result = subprocess.run(command, cwd=target, env=env, capture_output=True, text=True, timeout=25)
        if error:
            assert result.returncode == 1 and error in result.stderr, (name, result.stdout, result.stderr)
            assert not result.stdout, 'A rejected selection must not run the monitor'
        else:
            if result.returncode != 0 or expected not in result.stdout:
                inventory = subprocess.run(['pi', 'list'], cwd=target, env=env, capture_output=True, text=True)
                raise AssertionError((name, result.stdout, result.stderr, inventory.returncode, inventory.stdout, inventory.stderr))
        cases.append(name)

    first, second = packages
    check('configured package with spaces', [str(first)], expected=str(first))
    check('explicit matching package', [str(first)], expected=str(first), explicit=first)
    check('equivalent configured paths', [str(first), str(alias)], expected=str(first))
    check('explicit equivalent path', [str(first)], expected=str(first), explicit=alias)
    check('multiple configured copies', [str(first), str(second)], error='Multiple configured Harness')
    check('explicit mismatch', [str(first)], error='Explicit Harness conflicts', explicit=second)
    check('filtered Harness', [{'source': str(first), 'extensions': []}], error='Configured Harness is filtered')
    check('no configured Harness', [], expected='Run one Pi role')
    check('explicit package without configuration', [], expected=str(second), explicit=second)
    unrelated = root / 'unrelated'
    unrelated.mkdir()
    (unrelated / 'package.json').write_text(json.dumps({'name': 'unrelated', 'pi': {'extensions': []}}))
    check('unrelated package', [str(unrelated)], expected='Run one Pi role')
    check('wrong explicit package', [], explicit=unrelated, error='Selected package is not the Fight Harness')
    (first / 'src/observation/run-stage.py').unlink()
    check('configured monitor missing does not fall back', [str(first)], error='No child started')
    # Native resources can load a complete Harness without any packages inventory entry.
    installed = root / 'direct harness'
    shutil.copytree(provider / 'harness/pi', installed, ignore=shutil.ignore_patterns('node_modules', 'tests'))
    (installed / 'src/observation/run-stage.py').write_text('print(' + repr(str(installed)) + ')\n')
    resources = json.loads((installed / 'package.json').read_text())['pi']['extensions']
    check('complete direct Harness is selected automatically', [],
          extensions=[str(installed / resource) for resource in resources], expected=str(installed))
    (agent / 'extensions').mkdir()
    discovered = agent / 'extensions/harness'
    discovered.symlink_to(installed, target_is_directory=True)
    check('complete auto-discovered Harness is selected automatically', [], expected=str(installed))
    check('disabled auto-discovered Harness is not re-enabled', [],
          extensions=['-' + str(discovered / resource) for resource in resources],
          error='Configured Harness resources are incomplete or disabled')
    discovered.unlink()
    check('command-only observer from another Harness', [str(second)],
          extensions=[str(installed / 'src/native-observation-extension.ts')],
          error='Multiple configured Harness')
    check('incomplete direct Harness', [],
          extensions=[str(installed / 'src/native-observation-extension.ts')],
          error='Configured Harness resources are incomplete or disabled')
    untrusted = root / 'untrusted harness'
    (untrusted / 'src/observation').mkdir(parents=True)
    marker = root / 'untrusted-executed'
    (untrusted / 'package.json').write_text(json.dumps({
        'name': '@fight/agent-os-pi', 'pi': {'extensions': ['./index.ts']}}))
    (untrusted / 'index.ts').write_text("import { writeFileSync } from 'node:fs';\n" +
                                      'export default function () { writeFileSync(' + json.dumps(str(marker)) + ', "executed"); }\n')
    (untrusted / 'src/observation/run-stage.py').write_text('print("UNTRUSTED_MONITOR_DISPATCHED")\n')
    (target / '.pi').mkdir()
    project_settings = target / '.pi/settings.json'
    project_settings.write_text(json.dumps({'packages': [os.path.relpath(untrusted, project_settings.parent)]}))
    check('untrusted project Harness cannot become an explicit extension', [],
          error='Project Harness configuration is not natively trusted')
    assert not marker.exists(), 'Conservative inventory must not execute untrusted project code'
    project_settings.unlink()
    (target / '.pi').rmdir()
    # Unsupported inventory must never be interpreted as an empty configuration.
    binaries = root / 'bin'
    binaries.mkdir()
    fake = binaries / 'pi'
    fake.write_text('#!/bin/sh\nprintf "changed inventory format\\n"\n')
    fake.chmod(0o700)
    env['PATH'] = str(binaries) + os.pathsep + env['PATH']
    check('unsupported inventory', [], error='Unsupported Pi resource inventory')
    fake.write_text('#!/bin/sh\nexit 1\n')
    check('failed inventory', [], error='Pi resource inventory unavailable')
    print(json.dumps({'result': 'passed', 'cases': cases, 'count': len(cases), 'providerRequests': 0}))
