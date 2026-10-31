"""Explicit main and registered-worktree Compose operations."""
import json
import os
from pathlib import Path
import shutil
import subprocess

from .preparation import compose, environment, ingress, output_path, prepare, resolve
from .resources import Hold, native_ids, query, registrations, require, run, text


def storage_admit(root):
    reserve = int(os.environ.get('FIGHT_STORAGE_RESERVE_BYTES', 20 * 1024**3))
    allowance = int(os.environ.get('FIGHT_STORAGE_ALLOCATION_BYTES', 4 * 1024**3))
    require(reserve >= 0 and allowance >= 0, 'Negative storage allowance')
    require(shutil.disk_usage(root).free >= reserve + allowance,
            'Insufficient host space; use explicit owned cleanup, never global prune')
    text(['docker', 'info', '--format', '{{.ID}}'], root)


def selected(root, selector=None):
    safe, _, _, _ = resolve(root)
    # Native wrappers invoked INSIDE a linked checkout select that checkout;
    # canonical/main wrappers never infer task mode from branch or stale files.
    if selector is None and root != safe:
        selector = str(root)
    return (*resolve(root, selector), selector is not None)


def command(root, argv, selector=None):
    safe, checkout, project, entry, isolated = selected(root, selector)
    if argv[0] == 'compose':
        if isolated and (not (safe / 'var/worktrees' / project / 'override.yaml').exists() or
                         not (safe / 'var/worktrees' / project / 'output').exists()):
            prepare(safe, checkout, project, entry)
        return subprocess.run([*compose(safe, checkout, project, isolated), *argv[1:]], cwd=checkout,
                              env=environment(isolated)).returncode
    # Ad hoc tool containers use native --rm and task labels, not a shadow ledger.
    if isolated and argv[0] == 'run':
        options = list(argv[1:])
        for index, value in enumerate(options):
            if index and options[index - 1] == '--mount':
                fields = value.split(',')
                options[index] = ','.join('source=' + str(output_path(safe, checkout, project, field.split('=', 1)[1]))
                    if field.startswith(('source=', 'src=')) else field for field in fields)
        argv = ['run', '--label', 'com.docker.compose.project=' + project, *options]
    return subprocess.run(['docker', *argv], cwd=checkout, env=environment(isolated)).returncode


def up(root, selector=None, build_only=False):
    safe, checkout, project, entry = resolve(root, selector)
    isolated = selector is not None
    storage_admit(checkout)
    if isolated:
        prepare(safe, checkout, project, entry)
    args = compose(safe, checkout, project, isolated)
    if build_only:
        return subprocess.run([*args, '--profile', 'https', 'build'], cwd=checkout,
                              env=environment(isolated)).returncode
    require((checkout / 'vendor/autoload.php').is_file(), 'Install locked dependencies explicitly before startup')
    # This is a real external prerequisite, never a lifecycle receipt/PID gate.
    ingress(safe, checkout, project, isolated)
    args = compose(safe, checkout, project, isolated)
    for value in ('var', '.runs/cache/client/dependency-cache', '.runs/cache/client/vite-temp'):
        (checkout / value).mkdir(parents=True, exist_ok=True)
    # All declared application profiles, not the Composer maintenance job. No
    # migrations, dependency installation, shared ingress start or trust mutation.
    run([*args, '--profile', 'https', '--profile', 'mail', 'up', '--detach', '--build',
         '--wait', '--wait-timeout', '120'], checkout, env=environment(isolated))
    run([*args, '--profile', 'https', 'exec', '-T', 'https', 'nginx', '-t'], checkout, env=environment(isolated))
    run([*args, '--profile', 'https', 'exec', '-T', 'https', 'nginx', '-s', 'reload'], checkout, env=environment(isolated))
    host = (project if isolated else 'fight-agent-os') + '.localhost'
    url = 'https://' + host
    # Verified actual browser entry plus direct-TLS bootstrap; no -k, forwarded
    # authority or config-only readiness. Do not retain nonce/cookie contents.
    for path in ('/', '/api/v1/auth/csrf'):
        run(['curl', '--fail', '--silent', '--show-error', '--retry', '5', '--retry-all-errors',
             '--connect-timeout', '3', '--max-time', '10', '--retry-max-time', '60',
             '--resolve', host + ':443:127.0.0.1', '--output', '/dev/null', url + path], checkout)
    print(url)
    return 0


def down(root, selector=None):
    safe, checkout, project, entry = resolve(root, selector, removed=selector is not None)
    if selector is None:
        # Compose down without -v retains main's normal declared persistent data.
        return subprocess.run([*compose(safe, checkout, project), '--profile', '*', 'down', '--remove-orphans'],
                              cwd=safe, env=environment()).returncode
    if project is None:
        print(json.dumps({'complete': True, 'already_absent': True}))
        return 0
    state = safe / 'var/worktrees' / project
    cleanup = state / 'down.json'
    if not cleanup.exists():
        # No prior startup: native project identity is deterministic. An empty
        # Compose model still removes labeled orphans; label sweep covers volumes.
        config = '{"services": {}}'
        require(not state.is_symlink(), 'Runtime state alias refused')
        state.mkdir(parents=True, exist_ok=True, mode=0o700)
        from .resources import write_private
        write_private(cleanup, config)
    args = ['docker', 'compose', '--project-name', project, '--project-directory', str(safe),
            '--env-file', '/dev/null', '--file', str(cleanup), 'down', '--volumes', '--remove-orphans']
    failures = []
    removed = {'containers': 0, 'volumes': 0, 'networks': 0, 'image_tags': 0, 'builders': 0}

    def effect(argv):
        try:
            run(argv, safe, env=environment())
            return True
        except (Hold, OSError, subprocess.SubprocessError) as error:
            failures.append(str(error))
            return False

    before = {kind: native_ids(safe, kind, project) for kind in ('containers', 'volumes', 'networks')}
    effect(args)
    # Exact reserved project builder only; ordinary builds use the shared native
    # builder and do not claim its downloaded images/cache as task-exclusive.
    builders = text(['docker', 'buildx', 'ls', '--format', '{{.Name}}'], safe).splitlines()
    if project in builders and effect(['docker', 'buildx', 'rm', project]):
        removed['builders'] += 1
    builder_cache = 'buildx_buildkit_' + project + '0_state'
    cache = text(['docker', 'volume', 'ls', '-q', '--filter', 'name=^' + builder_cache + '$'], safe)
    if cache:
        # buildx rm can leave an inactive builder's backing volume. Native volume
        # removal still refuses any surviving foreign mount, with no force/prune.
        if effect(['docker', 'volume', 'rm', builder_cache]):
            removed['volumes'] += 1
    # Compose's metadata is authoritative, not stored PIDs/worker registrations.
    # Native rm refuses volumes/networks still used by foreign consumers.
    for kind, noun in (('containers', None), ('volumes', 'volume'), ('networks', 'network')):
        for key in native_ids(safe, kind, project):
            if kind == 'containers':
                if not effect(['docker', 'stop', '--time', '20', key]):
                    continue
                effect(['docker', 'rm', '--volumes', key])
            else:
                effect(['docker', noun, 'rm', key])
        after = native_ids(safe, kind, project)
        removed[kind] += len(set(before[kind]) - set(after))
        if after:
            failures.append('Remaining project ' + kind + ': ' + ', '.join(after))
    tags = text(['docker', 'image', 'ls', '--format', '{{.Repository}}:{{.Tag}}'], safe).splitlines()
    for tag in sorted(set(v for v in tags if v.startswith(project + '-'))):
        # Remove tags, NEVER a shared immutable image ID or downloaded base tag.
        if effect(['docker', 'image', 'rm', tag]):
            removed['image_tags'] += 1
    remaining_tags = [v for v in text(['docker', 'image', 'ls', '--format', '{{.Repository}}:{{.Tag}}'], safe).splitlines()
                      if v.startswith(project + '-')]
    if remaining_tags:
        failures.append('Remaining task image tags: ' + ', '.join(remaining_tags))
    if not failures and (state / 'output').exists():
        # This reserved directory holds only disposable task runtime storage.
        require(not (state / 'output').is_symlink(), 'Task output alias refused')
        shutil.rmtree(state / 'output')
    # Locator/config survives while source is registered, enabling harmless
    # repeats and post-worktree-removal recovery. No source/output deletion here.
    print(json.dumps({'project': project, 'complete': not failures, 'removed': removed, 'failures': failures,
                      'locator': str(state / 'locator.json')}))
    return 1 if failures else 0


def finish(root, selector, outcome, published_oid, pr):
    """Docker first, then guarded Worktrunk source/ref removal after Land verification"""
    safe, checkout, project, entry = resolve(root, selector)
    require(root == safe, 'Run source removal from canonical safe checkout')
    if down(root, selector):
        return 1
    require(checkout != safe, 'Main/current checkout is never disposable; Docker teardown completed')
    require(Path(outcome).is_file() and checkout not in Path(outcome).resolve().parents,
            'Record final outcome outside disposable source before Git removal')
    ref = 'refs/heads/' + entry['branch']
    oid = text(['git', 'rev-parse', ref], safe)
    require(oid == published_oid, 'Exact published source OID required; unique work retained')
    from .publication import published
    published({'safe': str(safe), 'ref': ref}, oid, ref, pr)
    require(not text(['git', 'status', '--porcelain', '--untracked-files=all'], checkout), 'Dirty/unique source retained')
    require(not text(['git', 'ls-files', '--others', '--ignored', '--exclude-standard'], checkout),
            'Ignored/private output remains; dispose known temporary output explicitly after consumers end')
    from .source import check
    check(checkout, safe)
    # Worktrunk owns hooks/integration/non-force removal. Attended user hooks may
    # invoke the SAME down command; no arbitrary hook approval or bypass here.
    for directory in (safe, checkout):
        for phase in ('pre-remove', 'post-remove'):
            hooks = query(['wt', 'hook', 'show', phase, '--format=json'], directory)
            require(all(v.get('source') == 'project' and v.get('name') == 'runtime' and
                        v.get('template') == '{{ primary_worktree_path }}/bin/down --worktree {{ worktree_path }}'
                        for v in hooks),
                    'Other Worktrunk hooks require attended execution; Docker teardown already completed')
    # Do not infer source safety from Docker teardown. Actual cwd/open-file and
    # filesystem checks remain required under Land before this non-force command.
    require(text(['git', 'rev-parse', ref], safe) == oid, 'Source ref changed before removal')
    result = subprocess.run(['wt', 'remove', '--foreground', '--format=json', str(checkout)], cwd=safe,
                            stdin=subprocess.DEVNULL, text=True, timeout=120)
    require(result.returncode == 0, 'Worktrunk removal failed; inspect partial effects without force')
    remaining = text(['git', 'for-each-ref', '--format=%(refname)', ref], safe)
    require(not checkout.exists() and not remaining and
            str(checkout) not in [v['path'] for v in registrations(safe)],
            'Worktrunk retained source/ref/registration; inspect actual outcome, never force')
    # Only generated locator/config is ours. No archive or dependency/source copy.
    shutil.rmtree(safe / 'var/worktrees' / project)
    return 0
