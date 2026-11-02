"""Guard native Worktrunk removal independently of disposable Docker teardown."""
from pathlib import Path
import shutil
import subprocess

from .preparation import resolve
from .resources import query, registrations, require, run, text


DOWN_HOOK = '{{ primary_worktree_path }}/bin/down --worktree {{ worktree_path }}'


def removal_hooks(safe, checkout):
    """Inspect effective system/user/project hooks without disclosing personal commands"""
    for directory in dict.fromkeys((safe, checkout)):
        for phase in ('pre-remove', 'post-remove'):
            hooks = query(['wt', 'hook', 'show', phase, '--format=json'], directory)
            require(isinstance(hooks, list), 'Worktrunk hook inventory unavailable')
            require(all(isinstance(hook, dict) and phase == 'pre-remove' and
                        hook.get('type') == phase and hook.get('source') == 'project' and
                        hook.get('name') == 'runtime' and hook.get('template') == DOWN_HOOK
                        for hook in hooks),
                    'Unqualified Worktrunk ' + phase + ' hook in ' +
                    ('canonical' if directory == safe else 'target') +
                    ' context; source retained, Docker teardown completed')


def remove(root, selector, expected_oid, delivery=None):
    """Run Docker first, then one non-force native removal for delivery or owned fixtures

    Land supplies its exact publication check. Without it, only an explicitly owned
    qualification fixture with no commits outside canonical HEAD is eligible.
    Caller authority and live-source qualification remain required; this is not a sandbox.
    """
    safe, checkout, project, entry = resolve(root, selector, removed=True)
    require(root == safe, 'Run source removal from canonical safe checkout')
    # Always use the public command, also used by the repository pre-remove hook.
    # Source, publication and hook holds cannot suppress independent Docker teardown.
    run([safe / 'bin/down', '--worktree', selector], safe)
    if project is None:
        require(Path(selector).is_absolute() and not Path(selector).exists(),
                'No registered worktree/locator; verify the exact absent source path')
        return 0
    require(checkout != safe and checkout != Path.cwd().resolve(),
            'Main/current checkout is never disposable; Docker teardown completed')
    branch = entry['branch']
    ref = 'refs/heads/' + branch if branch else None
    present = str(checkout) in [value['path'] for value in registrations(safe)]
    require(present == checkout.exists(), 'Source path/registration mismatch; inspect partial effects')

    def complete():
        remaining = text(['git', 'for-each-ref', '--format=%(refname)', ref], safe) if ref else ''
        require(not checkout.exists() and not remaining and
                str(checkout) not in [value['path'] for value in registrations(safe)],
                'Worktrunk retained source/ref/registration; inspect actual outcome, never force')
        # Also resume here after native removal succeeded but final down was interrupted.
        # There is no surviving source/ref to qualify or publish again.
        run([safe / 'bin/down', '--worktree', selector], safe)
        state = safe / 'var/worktrees' / project
        require(not state.is_symlink(), 'Runtime state alias refused')
        if state.exists():
            shutil.rmtree(state)
        return 0

    if not present and (not ref or not text(['git', 'for-each-ref', '--format=%(refname)', ref], safe)):
        return complete()

    def source_state():
        if present:
            current = next((value for value in registrations(safe) if value['path'] == str(checkout)), None)
            require(current == entry, 'Source registration changed before removal')
            require(text(['git', 'rev-parse', 'HEAD'], checkout) == expected_oid,
                    'Source OID changed; unique work retained')
            require(not text(['git', 'status', '--porcelain', '--untracked-files=all'], checkout),
                    'Dirty/unique source retained')
            require(not text(['git', 'ls-files', '--others', '--ignored', '--exclude-standard'], checkout),
                    'Ignored/private output remains; dispose only known temporary output after consumers end')
        if ref:
            require(text(['git', 'rev-parse', ref], safe) == expected_oid,
                    'Exact source ref/OID changed; unique work retained')
        if not delivery:
            require(present, 'Absent fixture source with surviving ref requires explicit delivery qualification')
            run(['git', 'merge-base', '--is-ancestor', expected_oid, 'HEAD'], safe)

    source_state()
    if delivery:
        delivery(safe, checkout, ref)
    # Qualify installed capabilities rather than an equality-version gate. --yes
    # on remove is per-invocation consent, NOT `config approvals add --yes`.
    help_text = text(['wt', 'remove', '--help'], safe)
    require(all(option in help_text for option in ('--yes', '--foreground', '--format')),
            'Installed Worktrunk lacks qualified noninteractive removal support')
    if present:
        from .source import check
        check(checkout, safe)
    source_state()
    # Re-read complete effective pre/post hooks in BOTH contexts immediately before
    # the native effect, using the same inherited config/environment as removal.
    removal_hooks(safe, checkout if present else safe)
    target = str(checkout) if present else branch
    if target:
        result = subprocess.run(['wt', 'remove', '--foreground', '--format=json', '--yes', target],
                                cwd=safe, stdin=subprocess.DEVNULL, text=True, timeout=120)
        require(result.returncode == 0, 'Worktrunk removal failed; inspect partial effects without force')
    return complete()
