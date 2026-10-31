#!/usr/bin/env python3
"""Finite public-command qualification with real disposable Git/Docker fixtures"""
import argparse
import hashlib
import json
import os
from pathlib import Path
import shutil
import shlex
import signal
import subprocess
import sys
import tempfile
import time

ROOT = Path(__file__).resolve().parent.parent
CLI = ROOT / 'bin/runtime'
TASK = 'TASK-00548'
ENV = None


def run(argv, cwd=ROOT, expected=0, env=None):
    result = subprocess.run([str(v) for v in argv], cwd=cwd, env=ENV if env is None else env, stdin=subprocess.DEVNULL, capture_output=True, timeout=120)
    if result.returncode != expected:
        raise RuntimeError(f'{argv[:4]}: exit {result.returncode}, expected {expected}; ' + result.stderr.decode()[-1500:])
    return result.stdout.decode()


def call(checkout, action, *argv, expected=0, env=None):
    return run([CLI, '--checkout', checkout, action, *argv], expected=expected, env=env)


def git(root, *argv):
    return run(['git', *argv], root).strip()


def observed():
    return {kind: set(run(['docker', *args]).splitlines()) for kind, args in {
        'containers': ['ps', '-aq', '--no-trunc'], 'volumes': ['volume', 'ls', '-q'],
        'networks': ['network', 'ls', '-q', '--no-trunc'], 'images': ['image', 'ls', '-q', '--no-trunc']}.items()}


def bytes_state(root):
    return {str(p.relative_to(root)): (p.lstat().st_ino, p.lstat().st_mode, p.lstat().st_mtime_ns,
            hashlib.sha256(p.read_bytes()).hexdigest() if p.is_file() else None)
            for p in [root, *root.rglob('*')] if '.git' not in p.relative_to(root).parts}


def qualify(evidence):
    global ENV
    evidence.mkdir(parents=True, exist_ok=False)
    fixtures = Path(tempfile.mkdtemp(prefix='fixtures-', dir=evidence))
    before = observed()
    # Only Worktrunk receives a private HOME/config. Docker keeps the original
    # local engine/context; no personal Worktrunk hooks, approvals or Git state.
    tools = fixtures / 'tools'; tools.mkdir()
    home = fixtures / 'home'; home.mkdir()
    config = home / 'config.toml'; config.write_text('')
    wt = shutil.which('wt')
    assert wt, 'Worktrunk is required for this owning Git-removal qualification'
    isolated = {'PATH': os.environ['PATH'], 'HOME': str(home), 'XDG_CONFIG_HOME': str(home / '.config'),
                'XDG_CACHE_HOME': str(home / '.cache'), 'XDG_DATA_HOME': str(home / '.local/share'),
                'GIT_CONFIG_NOSYSTEM': '1', 'GIT_CONFIG_GLOBAL': '/dev/null'}
    shim = tools / 'wt'
    shim.write_text('#!' + sys.executable + '\nimport os,sys\nos.execve(' + repr(wt) + ',[' + repr(wt) +
                   ',"--config",' + repr(str(config)) + ',*sys.argv[1:]],' + repr(isolated) + ')\n')
    shim.chmod(0o700)
    ENV = {**os.environ, 'PATH': str(tools) + os.pathsep + os.environ['PATH'],
           'GIT_CONFIG_NOSYSTEM': '1', 'GIT_CONFIG_GLOBAL': '/dev/null'}
    no_wt = fixtures / 'no-wt'; no_wt.mkdir()
    for name in ('python3', 'docker'):
        (no_wt / name).symlink_to(shutil.which(name))
    no_wt_env = {**ENV, 'PATH': str(no_wt) + ':/usr/bin:/bin:/usr/sbin:/sbin'}
    assert not shutil.which('wt', path=no_wt_env['PATH'])
    results = []
    completed = False
    try:
        for cycle in (1, 2):
            safe, checkout = fixtures / str(cycle) / 'safe', fixtures / str(cycle) / 'task'
            safe.mkdir(parents=True)
            git(safe, 'init', '-b', 'develop')
            (safe / '.gitignore').write_text('.runs/\nvar/\n')
            shutil.copyfile(ROOT / 'compose.yaml', safe / 'compose.yaml')
            git(safe, 'add', '.gitignore', 'compose.yaml')
            git(safe, '-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.test', 'commit', '-m', 'Finite owning fixture')
            call(safe, 'docker', 'version', '--format', '{{.Client.Version}}')  # non-TASK passthrough
            call(safe, 'paths', 'var/developer-wrapper')
            assert (safe / 'var/developer-wrapper').is_dir()
            (safe / 'var/developer-wrapper').rmdir(); (safe / 'var').rmdir()
            git(safe, 'worktree', 'add', '-b', f'feature/task-00548-public{cycle}', str(checkout))
            if cycle == 1:
                git(safe, 'branch', '--set-upstream-to=develop', f'feature/task-00548-public{cycle}')
            # A pre-existing private parent/content is never implicitly disposable.
            (checkout / 'var').mkdir()
            sentinel = checkout / 'var/keep-private'
            sentinel.write_text('fixture-owned private sentinel; lifecycle must preserve\n')
            sentinel_bytes = sentinel.read_bytes()
            outcome = safe / 'outcome.md'
            outcome.write_text('Original bounded fixture cancellation and source/ref teardown authorized; not a real publication\n')
            preparation = json.loads(call(checkout, 'prepare', '--task', TASK))
            record = Path(preparation['record'])
            project = preparation['project']
            original = record.read_bytes()
            assert json.loads(call(checkout, 'prepare', '--task', TASK)) == preparation
            assert record.read_bytes() == original, 'repeat prepare changed binding'
            workers = []
            foreign = project + '-unknown'
            foreign_snapshot = None
            foreign_container = None
            try:
                # Inspection must preserve bytes, mtime, modes, directory set and lock inode.
                snapshot = bytes_state(safe), bytes_state(checkout)
                call(safe, 'inspect', '--task', TASK)
                assert snapshot == (bytes_state(safe), bytes_state(checkout)), 'inspect mutated state'
                call(checkout, 'docker', 'compose', 'up', '--detach', '--wait', 'postgres_test')
                first = run(['docker', 'ps', '-aq', '--no-trunc', '--filter', 'label=com.docker.compose.project=' + project]).splitlines()
                call(checkout, 'docker', 'compose', 'up', '--detach', '--no-recreate', 'postgres_test')
                assert first == run(['docker', 'ps', '-aq', '--no-trunc', '--filter', 'label=com.docker.compose.project=' + project]).splitlines()
                call(checkout, 'docker', 'run', '--rm', '--pull=never', '--network=none', 'postgres:17-bookworm', 'sh', '-c', 'exit 7', expected=7)
                assert first == run(['docker', 'ps', '-aq', '--no-trunc', '--filter', 'label=com.docker.compose.project=' + project]).splitlines()
                run(['docker', 'volume', 'create', foreign])
                foreign_snapshot = run(['docker', 'volume', 'inspect', foreign])
                call(checkout, 'docker', 'run', '--rm', '--pull=never', '--network=none', '--mount',
                     'type=volume,source=' + foreign + ',target=/unknown', 'postgres:17-bookworm', 'true')
                network = run(['docker', 'network', 'ls', '-q', '--no-trunc', '--filter', 'label=com.docker.compose.project=' + project]).strip()
                foreign_container = run(['docker', 'create', '--name', project + '-external', '--network', network,
                    '--mount', 'type=volume,source=' + foreign + ',target=/unknown', '--tmpfs', '/var/lib/postgresql/data',
                    'postgres:17-bookworm', 'true']).strip()
                external_snapshot = run(['docker', 'inspect', foreign_container])
                (evidence / f'outer-creator-{cycle}.json').write_text(external_snapshot)
                # Worker registration is an explicit mutation; controlled live PIDs
                # exercise consumers, not native Pi/session admission or independent QA.
                attempt = safe / '.runs/build' / TASK / 'fixture-worker'
                (attempt / 'dispatch.lock').mkdir(parents=True)
                workers = [subprocess.Popen(['sleep', '120']) for _ in range(2)]
                (attempt / 'process.json').write_text(json.dumps(dict(zip(('monitor_pid', 'pi_pid'), [p.pid for p in workers]))))
                call(safe, 'register-worker', '--task', TASK, '--stage', 'land', '--attempt', str(attempt))
                held = call(safe, 'retire', '--task', TASK, expected=1)
                assert 'worker live' in held
                assert first == run(['docker', 'ps', '-aq', '--no-trunc', '--filter', 'label=com.docker.compose.project=' + project]).splitlines()
                exits = []
                for child in workers:
                    child.terminate(); exits.append(child.wait(timeout=10))
                for name, code in zip(('exit-code', 'pi-exit-code'), exits):
                    (attempt / name).write_text(str(code) + '\n')
                workers = []
                # External allocation succeeds, then the public wrapper dies before
                # completion recording. Only this fixture's exact wrapper is killed.
                if cycle == 1:
                    tools = safe / '.runs/tools'; tools.mkdir()
                    marker = tools / 'allocated'
                    real_docker = shutil.which('docker')
                    shim = tools / 'docker'
                    shim.write_text('#!/usr/bin/env python3\nimport os,subprocess,sys,time\n' +
                        'code=subprocess.call([' + repr(real_docker) + ',*sys.argv[1:]])\n' +
                        'if sys.argv[1:2]==["create"] and code==0:\n' +
                        ' open(' + repr(str(marker)) + ',"w").close();time.sleep(3)\n' +
                        'sys.exit(code)\n')
                    shim.chmod(0o700)
                    env = {**ENV, 'PATH': str(tools) + os.pathsep + ENV['PATH']}
                    command = [str(CLI), '--checkout', str(checkout), 'docker', 'run', '--rm', '--pull=never', '--network=none', 'postgres:17-bookworm', 'true']
                    child = subprocess.Popen(command, env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
                    try:
                        deadline = time.monotonic() + 30
                        while not marker.exists() and time.monotonic() < deadline:
                            assert child.poll() is None, 'fixture wrapper ended before interruption'
                            time.sleep(.05)
                        assert marker.exists()
                        child.send_signal(signal.SIGKILL); child.wait(timeout=10)
                        assert json.loads(record.read_bytes())['pending'], 'lost allocation identity'
                        time.sleep(4)
                        # ps may retain an orphan zombie briefly; do not guess it dead.
                        call(checkout, 'reconcile', '--task', TASK)
                    finally:
                        if child.poll() is None:
                            child.terminate(); child.wait(timeout=10)
                # A private-source hold cannot suppress independent container effects.
                held = call(safe, 'retire', '--task', TASK, expected=1, env=no_wt_env)
                assert foreign in held and 'unknown/shared/durable' in held
                assert not run(['docker', 'ps', '-aq', '--filter', 'label=com.docker.compose.project=' + project]).strip()
                assert sentinel.read_bytes() == sentinel_bytes
                assert run(['docker', 'volume', 'inspect', foreign]) == foreign_snapshot
                assert run(['docker', 'inspect', foreign_container]) == external_snapshot
                assert network in held and 'consumer' in held, 'foreign network consumer not held'
                run(['docker', 'rm', foreign_container])  # original outer creator, no force
                foreign_container = None
                run(['docker', 'volume', 'rm', foreign])  # original OUTER fixture owner only
                foreign_snapshot = None
                call(checkout, 'paths', 'var/new-output/deep', '.runs/new-logs')
                (checkout / 'var/new-output/deep/log.txt').write_text('finite fixture output\n')
                oid = git(checkout, 'rev-parse', 'HEAD')
                call(safe, 'finish', '--task', TASK, '--outcome', str(outcome), expected=1)
                assert (checkout / 'var/resource-lifecycle.json').exists(), 'outcome path alone authorized disposal'
                before_completion = record.read_bytes()
                call(safe, 'complete', '--task', TASK, '--outcome', str(outcome), '--published-oid', oid,
                     '--remote-ref', f'refs/heads/feature/task-00548-public{cycle}', '--pr', 'https://github.com/foreign/foreign/pull/1', expected=1)
                assert record.read_bytes() == before_completion, 'missing publication proof changed disposition'
                call(safe, 'complete', '--task', TASK, '--outcome', str(outcome), '--cancel-oid', oid,
                     '--authority', 'Original finite fixture source/ref cancellation teardown')
                if cycle == 2:
                    (checkout / '.gitignore').write_text('.runs/\nvar/\n# unique original fixture work\n')
                    git(checkout, 'add', '.gitignore')
                    git(checkout, '-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.test', 'commit', '-m', 'Unique fixture work')
                    held = call(safe, 'finish', '--task', TASK, '--outcome', str(outcome), expected=1)
                    assert 'unqualified work' in held and checkout.exists()
                    call(safe, 'complete', '--task', TASK, '--outcome', str(outcome), '--cancel-oid', git(checkout, 'rev-parse', 'HEAD'),
                         '--authority', 'Original bounded fixture owner explicitly cancels this exact new fixture commit')
                held = call(safe, 'finish', '--task', TASK, '--outcome', str(outcome), expected=1)
                assert 'ignored' in held and sentinel.read_bytes() == sentinel_bytes
                assert sentinel.parent.exists() and not (sentinel.parent / 'resource-lifecycle.json').exists()
                assert not (checkout / 'var/new-output').exists() and not (checkout / '.runs').exists()
                sentinel.unlink()  # original fixture's private-data owner releases its sentinel
                held = call(safe, 'finish', '--task', TASK, '--outcome', str(outcome), expected=1, env=no_wt_env)
                assert 'Worktrunk CLI (wt) unavailable' in held and checkout.exists()
                # Configured hooks are held, never skipped, approved or launched.
                hook_config = safe / '.config/wt.toml'; hook_config.parent.mkdir()
                for phase in ('pre-remove', 'post-remove'):
                    hook_config.write_text(phase + ' = "echo must-not-run > hook-marker"\n')
                    held = call(safe, 'finish', '--task', TASK, '--outcome', str(outcome), expected=1)
                    assert phase + ' hooks configured' in held and checkout.exists()
                    assert not (safe / 'hook-marker').exists() and not (checkout / 'hook-marker').exists()
                hook_config.unlink(); hook_config.parent.rmdir()
                if cycle == 2:
                    held = json.loads(call(safe, 'finish', '--task', TASK, '--outcome', str(outcome), expected=1))
                    effect = next(v for v in held['results'] if v['kind'] == 'git')
                    assert effect['branch_outcome'] == 'retained_unmerged' and effect['worktree_absent'] and not effect['ref_absent']
                    assert not checkout.exists() and record.exists()
                    # Original finite fixture owner integrates its exact commit;
                    # Worktrunk, not custom Git deletion, owns branch-only resume.
                    git(safe, 'merge', '--ff-only', f'feature/task-00548-public{cycle}')
                if cycle == 1:
                    # Worktrunk can delete a ref but retain its task-specific local
                    # configuration. Preserve the obligation across absent-ref retries.
                    for _ in range(2):
                        held = json.loads(call(safe, 'finish', '--task', TASK, '--outcome', str(outcome), expected=1))
                        effect = next(v for v in held['results'] if v['kind'] == 'git')
                        assert effect['branch_config_remaining'] and 'branch-local configuration' in effect['reason']
                        assert not checkout.exists() and record.exists()
                    # Only the ORIGINAL OUTER fixture owner resolves its own exact
                    # fixture metadata. Production lifecycle never edits Git config.
                    git(safe, 'config', '--local', '--remove-section', f'branch.feature/task-00548-public{cycle}')
                # Exercise the actual post-worker caller from its safe checkout.
                (safe / 'bin').mkdir()
                wrapper = safe / 'bin/runtime'
                wrapper.write_text('#!/bin/sh\nexec ' + shlex.quote(str(CLI)) + ' --checkout ' + shlex.quote(str(safe)) + ' "$@"\n')
                wrapper.chmod(0o700)
                run(['python3', ROOT / '.pi/skills/build/scripts/cleanup-continuation.py', '--attempt', attempt,
                     '--receipt', record, '--apply', '--finish'], cwd=safe)
                assert not checkout.exists() and not record.exists() and not Path(str(record) + '.lock').exists()
                assert not git(safe, 'for-each-ref', '--format=%(refname)', f'refs/heads/feature/task-00548-public{cycle}')
                # Task monitor fixture evidence is outer-owned, not silently adopted.
                shutil.rmtree(safe / '.runs/build')
                shutil.rmtree(safe / '.runs/tools', ignore_errors=True)
                results.append({'cycle': cycle, 'repeat_prepare_and_repair_reused': True, 'workload_exit': 7,
                    'read_only_inspect': True, 'active_workers_held': True, 'unknown_volume_unchanged': True,
                    'foreign_stopped_network_and_volume_consumer_unchanged': True,
                    'interrupted_after_native_allocation': cycle == 1, 'locator_before_git_and_private_parent_preserved': True,
                    'docker_retirement_without_wt': True, 'missing_wt_holds_only_git': True,
                    'pre_and_post_remove_hooks_held_without_execution': True,
                    'worktrunk_retained_unmerged_and_branch_only_resume': cycle == 2,
                    'retained_branch_config_holds_record_across_absent_ref_retries': cycle == 1,
                    'owned_runtime_source_ref_output_record_lock_remaining': 0})
            finally:
                for child in workers:
                    child.terminate(); child.wait(timeout=10)
                if record.exists():
                    call(safe, 'retire', '--task', TASK, expected=1 if foreign_snapshot else 0)
                if foreign_container:
                    run(['docker', 'rm', foreign_container])
                if foreign_snapshot:
                    assert run(['docker', 'volume', 'inspect', foreign]) == foreign_snapshot
                    run(['docker', 'volume', 'rm', foreign])
        # Git-only prepare/inspect work with unavailable Docker. A filesystem
        # deletion still needs bind visibility, so that specific effect must hold.
        safe = fixtures / 'git-only'; safe.mkdir()
        git(safe, 'init', '-b', 'feature/task-00548-source-only')
        (safe / '.gitignore').write_text('.runs/\nvar/\n')
        git(safe, 'add', '.gitignore')
        git(safe, '-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.test', 'commit', '-m', 'Git-only fixture')
        tools = fixtures / 'no-docker'; tools.mkdir()
        shim = tools / 'docker'; shim.write_text('#!/bin/sh\nexit 69\n'); shim.chmod(0o700)
        env = {**ENV, 'PATH': str(tools) + os.pathsep + ENV['PATH']}
        binding = json.loads(call(safe, 'prepare', '--task', TASK, '--source-only', env=env))
        snapshot = bytes_state(safe)
        call(safe, 'inspect', '--task', TASK, env=env)
        assert snapshot == bytes_state(safe)
        outcome = safe / 'outcome.md'; outcome.write_text('Original bounded Git-only fixture cancellation outcome\n')
        oid = git(safe, 'rev-parse', 'HEAD')
        call(safe, 'complete', '--task', TASK, '--outcome', str(outcome), '--cancel-oid', oid,
             '--authority', 'Original finite main-checkout fixture teardown')
        held = call(safe, 'finish', '--task', TASK, '--outcome', str(outcome), env=env, expected=1)
        assert 'owning command failed' in held and 'temporary' in held
        held = call(safe, 'finish', '--task', TASK, '--outcome', str(outcome), expected=1)
        assert 'main checkout is never disposable' in held
        assert safe.exists() and git(safe, 'rev-parse', 'HEAD') == oid, 'main checkout/ref removed'
        assert Path(binding['record']).exists() and not (safe / 'var').exists(), 'main Git hold lost its record'
        # The original outer fixture owner retires this protected main repo only
        # during bounded whole-fixture teardown, never through task Git cleanup.
        results.append({'source_only_prepare_inspect_without_docker': True, 'bind_inventory_hold_specific': True,
                        'main_checkout_and_ref_preserved': True})
        assert before == observed(), 'Docker identity sets changed; investigate residue/concurrent activity'
        states = {}
        for directory in fixtures.glob('*/safe/.git/wt'):
            states[str(directory.relative_to(fixtures))] = sorted(str(p.relative_to(directory)) for p in directory.rglob('*') if p.is_file())
        (evidence / 'fixture-worktrunk-state.json').write_text(json.dumps(states, indent=2) + '\n')
        completed = True
        (evidence / 'result.json').write_text(json.dumps({'passed': True, 'cycles': results,
            'limits': 'Real public-command owning qualification, controlled cancellation/worker identities; not independent Review/QA or live PR proof'}, indent=2) + '\n')
    finally:
        remaining = {k: sorted(v - before[k]) for k, v in observed().items()}
        if completed and not any(remaining.values()):
            shutil.rmtree(fixtures)
            (evidence / 'teardown.json').write_text(json.dumps({'fixture_absent': not fixtures.exists(), 'docker_identity_sets_restored': True}) + '\n')
        else:
            (evidence / 'incomplete.json').write_text(json.dumps({'remaining': remaining, 'original_fixture': str(fixtures),
                'action': 'resolve original finite fixture before another run; failed evidence retained'}, indent=2) + '\n')
    return results


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--evidence', required=True, type=Path)
    args = parser.parse_args()
    os.umask(0o077)
    print(json.dumps(qualify(args.evidence.resolve())))
