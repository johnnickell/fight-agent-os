#!/usr/bin/env python3
"""Direct native qualification of disposable Compose projects (not product tests).

Requires the existing main web image; never starts/stops main or shared ingress.
Full HTTPS/auth acceptance still requires independently delivered TASK-00553.
"""
import argparse
from contextlib import nullcontext
import json
import shutil
from pathlib import Path
import subprocess
import sys
import tempfile

sys.dont_write_bytecode = True
from resource_lifecycle.preparation import identity
from resource_lifecycle.worktrunk import remove
from resource_lifecycle.resources import Hold

ROOT = Path(__file__).resolve().parent.parent
CLI = ROOT / 'bin/runtime'


def call(argv, cwd=ROOT, capture=True, check=True):
    result = subprocess.run([str(v) for v in argv], cwd=cwd, capture_output=capture, text=True, timeout=180)
    if check and result.returncode:
        raise RuntimeError('Command failed: ' + ' '.join(str(v) for v in argv[:4]) + '\n' + result.stderr)
    return result


def docker(*args):
    return call(['docker', *args]).stdout.strip()


def scoped(checkout, *args):
    return call([CLI, '--worktree', checkout, 'docker', 'compose', *args]).stdout.strip()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--evidence', required=True)
    args = parser.parse_args()
    evidence = Path(args.evidence).resolve()
    require_scratch = ROOT / '.runs'
    if require_scratch not in evidence.parents:
        raise ValueError('Evidence must remain in ignored .runs')
    evidence.mkdir(parents=True, exist_ok=False)
    before = docker('ps', '-aq', '--no-trunc').splitlines()
    main_image = docker('image', 'inspect', '--format', '{{.Id}}', 'fight-agent-os-web')
    rows, fixtures = [], []
    # Already integrated fixture tips let native Worktrunk delete their refs
    # without force; canonical runtime code still owns every operation below.
    fixture_oid = call(['git', 'rev-parse', 'develop']).stdout.strip()
    base = ROOT / '.runs/worktrees'
    base.mkdir(parents=True, exist_ok=True)
    try:
        with nullcontext(tempfile.mkdtemp(prefix='task00543-compose-', dir=base)) as directory:
            for name in ('a', 'b'):
                checkout = Path(directory) / name
                branch = 'feature/task-00543-qualification-' + Path(directory).name.rsplit('-', 1)[1] + '-' + name
                call(['git', 'worktree', 'add', '-b', branch, checkout, fixture_oid])
                project = identity(ROOT, checkout)
                fixtures.append((checkout, project))
                # Real wrapper config resolution, no legacy prepare/receipt/registration.
                scoped(checkout, 'config', '--quiet')
                docker('tag', main_image, project + '-web')
                model = json.loads(scoped(checkout, '--profile', '*', 'config', '--format', 'json'))
                assert all(not v.get('ports') for v in model['services'].values())
                assert model['services']['web']['volumes'][0]['source'] == str(checkout)
                assert model['volumes']['postgres_data']['name'] == project + '_postgres_data'
                scoped(checkout, 'up', '--detach', '--no-build', '--wait', '--wait-timeout', '60', 'web', 'mailcatcher')
                sql = "CREATE TABLE fixture (value text); INSERT INTO fixture VALUES ('" + name + "');"
                scoped(checkout, 'exec', '-T', 'postgres', 'psql', '-U', 'agent_os', '-d', 'agent_os', '-c', sql)
                scoped(checkout, 'exec', '-T', 'web', 'sh', '-c', 'printf ' + name + ' > /app/var/fixture')
                rows.append({'project': project, 'checkout': str(checkout), 'started': True})
            ambiguous = call([ROOT / 'bin/down', '--worktree', 'task-00543'], check=False)
            assert ambiguous.returncode == 1 and 'Ambiguous' in ambiguous.stderr
            # Same TASK is never enough to choose one of these actual checkouts.
            for checkout, project in fixtures:
                value = scoped(checkout, 'exec', '-T', 'postgres', 'psql', '-U', 'agent_os', '-d', 'agent_os', '-Atc', 'SELECT value FROM fixture')
                marker = scoped(checkout, 'exec', '-T', 'web', 'cat', '/app/var/fixture')
                assert value == marker == checkout.name
            a, b = fixtures
            orphan = docker('run', '--detach', '--name', a[1] + '-orphan',
                            '--label', 'com.docker.compose.project=' + a[1],
                            '--label', 'com.docker.compose.service=orphan',
                            '--mount', 'type=volume,target=/fixture', main_image, 'sleep', '300')
            anonymous = json.loads(docker('inspect', orphan))[0]['Mounts'][0]['Name']
            call([ROOT / 'bin/down', '--worktree', a[0]])
            assert not docker('volume', 'ls', '-q', '--filter', 'name=^' + anonymous + '$')
            assert not docker('ps', '-aq', '--filter', 'label=com.docker.compose.project=' + a[1])
            assert not docker('volume', 'ls', '-q', '--filter', 'label=com.docker.compose.project=' + a[1])
            assert not docker('network', 'ls', '-q', '--filter', 'label=com.docker.compose.project=' + a[1])
            assert scoped(b[0], 'exec', '-T', 'web', 'cat', '/app/var/fixture') == 'b'
            call([ROOT / 'bin/down', '--worktree', a[0]])
            # A second complete lifecycle reuses the stable checkout identity and
            # starts with fresh disposable DB/storage, not a branch/receipt gate.
            scoped(a[0], 'config', '--quiet')
            docker('tag', main_image, a[1] + '-web')
            scoped(a[0], 'up', '--detach', '--no-build', '--wait', '--wait-timeout', '60', 'web')
            assert scoped(a[0], 'exec', '-T', 'postgres', 'psql', '-U', 'agent_os', '-d', 'agent_os', '-Atc',
                          "SELECT count(*) FROM pg_tables WHERE tablename='fixture'") == '0'
            call([ROOT / 'bin/down', '--worktree', a[0]])
            rows.append({'two_projects_concurrent': True, 'distinct_data_and_source_mounts': True,
                         'two_successive_lifecycles': True, 'repeated_down': True,
                         'same_task_ambiguity_refused': True, 'anonymous_orphan_volume_removed': True})
    finally:
        cleanup_failures = []
        for checkout, project in reversed(fixtures):
            try:
                # Same guarded Docker-first callable as Land. Fixture ancestry is
                # checked without inventing PR/publication proof for local probes.
                remove(ROOT, str(checkout), fixture_oid)
            except (Hold, OSError, subprocess.SubprocessError) as error:
                cleanup_failures.append(str(error))
            repeated = call([ROOT / 'bin/down', '--worktree', checkout], check=False)
            if repeated.returncode:
                cleanup_failures.append(repeated.stderr)
            if not cleanup_failures:
                # Needed results remain in this run's small report, not runtime
                # caches, secrets/config locators or permanent fixture archives.
                for target in (ROOT / 'var/worktrees' / project, ROOT / '.runs/compose' / project):
                    if target.exists():
                        assert not target.is_symlink()
                        shutil.rmtree(target)
        if 'directory' in locals() and not any(Path(directory).iterdir()):
            Path(directory).rmdir()
        after = docker('ps', '-aq', '--no-trunc').splitlines()
        assert set(before) == set(after), 'Foreign/main container identity changed'
        rows.append({'main_and_foreign_container_ids_preserved': len(before), 'cleanup_failures': cleanup_failures,
                     'https_auth': 'UNVERIFIED — TASK-00553 is not delivered'})
        (evidence / 'result.json').write_text(json.dumps(rows, indent=2) + '\n')
        if cleanup_failures:
            raise RuntimeError('Fixture cleanup incomplete; inspect ' + str(evidence))
    print(json.dumps(rows, indent=2))


if __name__ == '__main__':
    main()
