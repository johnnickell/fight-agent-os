#!/usr/bin/env python3
"""Qualify the owning creation/readiness/plan/continuation path with bounded real effects."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import time

sys.dont_write_bytecode = True
from cleanup import file_record


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--evidence', required=True)
    parser.add_argument('--resume', action='store_true', help='Reuse original fixture/receipt; never repeat allocation')
    args = parser.parse_args()
    os.umask(0o077)
    evidence = Path(args.evidence).resolve()
    evidence.mkdir(mode=0o700, parents=True, exist_ok=args.resume)
    source = Path(__file__).resolve().parents[4]
    commands = json.loads((evidence/'commands.json').read_text()) if args.resume else []
    original_commands = list(commands)
    deadline = time.monotonic() + 600
    real_git = shutil.which('git')

    def save(path, value):
        if path.exists():
            assert args.resume and json.loads(path.read_text()) == value, 'original fixture proof changed; no overwrite'
            return
        with path.open('x') as output:
            json.dump(value, output, indent=2)

    def run(argv, cwd, expected=0, env=None):
        assert len(commands) < 80 and time.monotonic() < deadline, 'finite qualification budget exhausted; preserve original receipt'
        key = [str(v) for v in argv]
        original_effect = (str(argv[0]) == real_git and str(argv[1]) in ('init','config','remote','add','commit','worktree')) or key[:2] == ['./bin/runtime','init'] or key[:5] == ['./bin/runtime','docker','compose','run','--rm']
        if args.resume and original_effect:
            matches = [v for v in original_commands if v['argv'] == key and v['cwd'] == str(cwd) and v['exit'] == expected]
            assert len(matches) == 1, 'missing original effect result; refuse blind replay'
            return (evidence/matches[0]['stdout']).read_text().strip()
        invocation = [str(v) for v in argv]
        if args.resume and invocation[0] == './bin/runtime':
            invocation = [sys.executable, str(source/'.pi/skills/land/scripts/lifecycle.py'), '--root', str(cwd), *invocation[1:]]
        result = subprocess.run(invocation, cwd=cwd, env=dict(os.environ, **(env or {})),
                                capture_output=True, timeout=min(120, deadline-time.monotonic()))
        index = len(commands)
        (evidence / f'{index:03}.stdout').write_bytes(result.stdout)
        (evidence / f'{index:03}.stderr').write_bytes(result.stderr)
        commands.append({'argv': invocation, 'requested_owning_argv': [str(v) for v in argv], 'cwd': str(cwd), 'exit': result.returncode,
                         'stdout': f'{index:03}.stdout', 'stderr': f'{index:03}.stderr'})
        (evidence/'commands.json').write_text(json.dumps(commands, indent=2))
        assert result.returncode == expected, (index, result.returncode, expected, 'inspect exact retained streams; no fallback')
        return result.stdout.decode().strip()

    files = ['bin/runtime', 'bin/down', '.pi/skills/land/scripts/lifecycle.py', '.pi/skills/land/scripts/cleanup.py',
             '.pi/skills/build/scripts/cleanup-continuation.py', 'scripts/compose_storage_inputs.py']
    hashes = {name: hashlib.sha256((source/name).read_bytes()).hexdigest() for name in files}
    original_intent = json.loads((evidence/'original-intent.json').read_text()) if args.resume else None
    image = run(['docker', 'image', 'inspect', 'postgres:17-bookworm', '--format', '{{.Id}}'], source)
    if not args.resume: save(evidence/'original-intent.json', {'task': 'TASK-00546', 'authority': 'bounded disposable owning-tool qualification under original Work authority',
         'source': hashes, 'qualifier_sha256': hashlib.sha256(Path(__file__).read_bytes()).hexdigest(), 'budget': {'containers': 1, 'networks': 1, 'named_volumes': 1, 'anonymous_volumes': 1,
         'images_created_or_pulled': 0, 'workers': 2, 'seconds': 600, 'commands': 80},
         'boundary': 'real Docker/Git/filesystem and finite host children; controlled hosting/role fixtures, NOT independent role journeys or delivery',
         'probe_release': 'original generated link/IPC stay present and are classified before creator loss; socket worker exits naturally; no deletion/protection workaround'})
    safe = evidence/'safe'; safe.mkdir(exist_ok=args.resume)
    for name in files:
        target = safe/name; target.parent.mkdir(parents=True, exist_ok=True)
        if target.exists():
            assert args.resume and hashlib.sha256(target.read_bytes()).hexdigest() == original_intent['source'][name], 'original fixture helper changed; hold'
        else:
            shutil.copy2(source/name, target)
    if not args.resume: (safe/'.gitignore').write_text('var/\n.runs/\n.env\n')
    run([real_git, 'init', '--initial-branch=develop'], safe)
    run([real_git, 'config', 'user.name', 'TASK-00546 bounded fixture'], safe)
    run([real_git, 'config', 'user.email', 'task00546@example.invalid'], safe)
    run([real_git, 'remote', 'add', 'origin', 'git@github.com:task00546/readiness-fixture.git'], safe)
    run([real_git, 'add', '.'], safe)
    run([real_git, 'commit', '-m', 'Controlled TASK-00546 fixture source'], safe)
    branch = 'feature/task-00546-readiness-fixture'
    root = evidence/'checkout'
    run([real_git, 'worktree', 'add', '-b', branch, root, 'develop'], safe)
    store = safe/'.runs/tasks/TASK-00546/runs'; store.mkdir(parents=True, exist_ok=args.resume)
    receipt = store/'work-resources.json'
    external = store/'protected-report.txt'
    if not args.resume: external.write_text('required protected report\n')
    external_record=file_record(external)
    private = root/'.env'
    if not args.resume: private.write_text('fixture-private-settings-not-a-credential\n')
    private_record = file_record(private)
    project = 'task00546-ready-' + hashlib.sha256(str(evidence).encode()).hexdigest()[:10]
    config = store/'compose.json'
    save(config, {'services': {'web': {'image': image, 'pull_policy': 'never', 'entrypoint': [], 'command': ['true'],
           'read_only': True, 'mem_limit': '32m', 'cpus': .25, 'pids_limit': 8, 'volumes': ['named:/fixture-named']}}, 'volumes': {'named': {}}})
    spec = store/'spec.json'
    save(spec, {'project': project, 'config_files': [str(config)], 'env_files': [],
        'policy': {'services': {'web': {'data': 'disposable', 'authority': 'original positively disposable bounded fixture'}},
            'runtime_authority': 'original positively disposable local verification runtime; source/ref/private settings retained',
            'oneoff_authority': 'original bounded temporary fixture',
            'budget': {'operations': 8, 'seconds': 30, 'containers': 1, 'networks': 1, 'volumes': 2, 'images': 1, 'caches': 0},
            'recoverable_storage': {'image_ids': [], 'cache_ids': []}},
        'private_paths': [{'path': str(root/'var'), 'disposition': 'preserve', 'reason': 'original runtime and private evidence'},
                          {'path': str(private), 'disposition': 'preserve', 'reason': 'required private settings'}],
        'protected_files': [str(private), str(external)]})
    run(['./bin/runtime', 'init', '--task', 'TASK-00546', '--receipt', receipt, '--spec', spec], root)
    run(['./bin/runtime', 'docker', 'compose', 'run', '--rm', 'web', 'true'], root)
    run(['./bin/runtime', 'validate', '--stage', 'controlled-work-handoff'], root)
    # Unknown special/private artifacts must be detected upstream, not silently lost.
    unknown = root/'var/unknown-link'
    if not args.resume:
        unknown.symlink_to(external)
        run(['./bin/runtime', 'readiness', '--stage', 'controlled-unknown-artifact'], root, expected=1)
        run(['./bin/runtime', 'classify-temporary', '--path', unknown, '--creator-pid', str(os.getpid()),
             '--authority', 'original generated disposable qualification link; target report remains protected',
             '--reason', 'no-follow link itself is temporary probe state, never its report target'], root)
    attempt = safe/('.runs/build/TASK-00546/original-controlled-worker-2' if args.resume else '.runs/build/TASK-00546/original-controlled-worker'); attempt.mkdir(parents=True)
    if args.resume:
        save(evidence/'fixture-recovery-intent.json', {'same_receipt': str(receipt), 'new_docker_allocations': 0,
             'old_worker_attempt': str(safe/'.runs/build/TASK-00546/original-controlled-worker'),
             'old_outcome': 'original failed checker returned before waiting: prior worker exits remain unknown, never forged',
             'bounded_new_host_workers': 2, 'total_host_worker_budget': 4, 'new_docker_resources': 0,
             'source_bridge': {'original': original_intent['source'], 'current_owning_helper': hashes,
                              'current_qualifier_sha256': hashlib.sha256(Path(__file__).read_bytes()).hexdigest()},
             'correction': 'current owning lifecycle --root reuses exact original receipt/resources; classified artifact generations must still match; no recreation' })
    (attempt/'dispatch.lock').mkdir()
    socket_path=root/'var/observer.sock'
    worker_script = store/('resume-worker.py' if args.resume else 'socket-worker.py')
    worker_script.write_text('import time\ntime.sleep(30)\n' if args.resume else 'import socket,sys,time\ns=socket.socket(socket.AF_UNIX);s.bind("var/observer.sock");s.listen();time.sleep(30);s.close()\n')
    monitor = subprocess.Popen([sys.executable, '-c', 'import time;time.sleep(30)'], cwd=safe)
    worker = subprocess.Popen([sys.executable, str(worker_script), str(socket_path)], cwd=root)
    save(attempt/'process.json', {'monitor_pid': monitor.pid, 'pi_pid': worker.pid})
    for _ in range(80):
        if socket_path.exists(): break
        time.sleep(.05)
    assert socket_path.exists() and worker.poll() is None
    if not args.resume:
        run(['./bin/runtime', 'readiness', '--stage', 'controlled-unknown-ipc'], root, expected=1)
        run(['./bin/runtime', 'classify-temporary', '--path', socket_path, '--creator-pid', str(worker.pid),
             '--authority', 'original finite generated disposable observer IPC', '--reason', 'temporary observer socket, no private data; original creator naturally exits'], root)
    run(['./bin/runtime', 'validate', '--stage', 'controlled-handoff', '--handoff'], root)
    doc=json.loads(receipt.read_text()); assert doc['lifecycle']['readiness'][-1]['ready']
    proof=json.loads(Path(doc['lifecycle']['readiness'][-1]['proof']['path']).read_text())
    copies={v['old']:v for v in proof['preservation']['files']}
    assert copies[str(private)]['record']==private_record and file_record(Path(copies[str(private)]['new']))==copies[str(private)]['destination_record']
    oid=run([real_git,'rev-parse','HEAD'],root)
    url='https://github.com/task00546/readiness-fixture/pull/1'
    delivery=store/'delivery.json'
    save(delivery, {'purpose':'disposable-runtime','oid':oid,'publication':{'url':url,'repository':'task00546/readiness-fixture','number':1,'base':'develop',
        'base_ref':'refs/heads/develop','delivery_verified':True,'merge_oid':None,'merged_at':None},
        'exited_workers':[{'pid':monitor.pid},{'pid':worker.pid}],'original_land_attempt':str(attempt),
        'protected_files':[], 'worktree':{'disposition':'retain','reason':'intentional owning-tool source/proof retention'},
        'branch':{'disposition':'retain','reason':'intentional owning-tool source/proof retention'}})
    run(['./bin/runtime','plan','--delivery',delivery],root)
    control=store/'controlled-hosting';control.mkdir()
    pr={'url':url,'state':'OPEN','mergedAt':None,'mergeCommit':None,'baseRefName':'develop','headRefName':branch,
        'headRefOid':oid,'headRepository':{'name':'readiness-fixture'},'headRepositoryOwner':{'login':'task00546'}}
    (control/'gh').write_text('#!'+sys.executable+'\nimport json\nprint('+repr(json.dumps(pr))+')\n')
    (control/'git').write_text('#!'+sys.executable+'\nimport os,sys\nif sys.argv[1:4]==["ls-remote","--heads","origin"]: print('+repr(oid+'\trefs/heads/'+branch)+')\nelse: os.execv('+repr(real_git)+',['+repr(real_git)+',*sys.argv[1:]])\n')
    for name in ('git','gh'): (control/name).chmod(0o700)
    env={'PATH':str(control)+os.pathsep+os.environ['PATH']}
    helper=source/'.pi/skills/land/scripts/cleanup.py'
    run([sys.executable,helper,'--receipt',receipt],safe,expected=1,env=env)
    continuation=source/'.pi/skills/build/scripts/cleanup-continuation.py'
    # This is deliberately controlled original-worker evidence, not a real Land verdict.
    assert monitor.poll() is None and worker.poll() is None, 'original live-worker probe expired; no fabricated live-refusal claim'
    run([sys.executable,continuation,'--attempt',attempt,'--receipt',receipt,'--apply'],safe,expected=1,env=env)
    monitor.wait(timeout=45);worker.wait(timeout=45)
    (attempt/'exit-code').write_text(str(monitor.returncode)+'\n');(attempt/'pi-exit-code').write_text(str(worker.returncode)+'\n')
    run([sys.executable,continuation,'--attempt',attempt,'--receipt',receipt,'--apply'],safe,env=env)
    run([sys.executable,continuation,'--attempt',attempt,'--receipt',receipt,'--apply'],safe,env=env)
    run(['./bin/down'],root,env=env)  # Actual owning runtime-retirement caller, SAME generated receipt/plan.
    doc=json.loads(receipt.read_text());results=doc['cleanup']['attempts'][-1]['results']
    assert doc['cleanup']['attempts'][-1]['complete'] and all(v['status'] in ('absent','retained') for v in results)
    assert file_record(external)==external_record and file_record(private)==private_record
    resources=doc['lifecycle']['resources']
    containers=set(run(['docker','ps','-aq','--no-trunc'],safe).splitlines())
    volumes=set(run(['docker','volume','ls','-q'],safe).splitlines())
    networks=set(run(['docker','network','ls','-q','--no-trunc'],safe).splitlines())
    assert not containers.intersection(v['identity'] for v in resources['containers'])
    assert not volumes.intersection(v['identity'] for v in resources['volumes'])
    assert not networks.intersection(v['identity'] for v in resources['networks'])
    assert hashes=={name:hashlib.sha256((source/name).read_bytes()).hexdigest() for name in files}
    save(evidence/'result.json',{'pass':True,'receipt':str(receipt),'source':hashes,
         'qualifier_sha256': hashlib.sha256(Path(__file__).read_bytes()).hexdigest(),'protected_unchanged':True,
         'independent_absence':True,'external_private_copy_verified':True,
         'coverage':['original-intent','actual-oneoff-creator-and-named-anonymous-mounts','stage-handoff','unknown-private-link-hold',
                     'unknown-ipc-hold','original-live-creator-ipc-classification','actual-upstream-preservation','mechanical-plan',
                     'preview','actual-bin-down-runtime-retirement','live-original-worker-continuation-refusal','original-worker-natural-exits','same-receipt-continuation-apply','same-receipt-absent-resume'],
         'boundary':'real owning methods/wrappers/Docker/Git/filesystem; controlled hosting and worker fixtures are NOT independent roles/publication',
         'resources':'one container, two volumes and one network absent; reused image and fixture source/ref/proofs retained; physical reclaim unknown'})
    print(json.dumps({'pass':True,'evidence':str(evidence)}))


if __name__=='__main__':
    main()
