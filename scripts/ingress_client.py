"""Discover the user's explicitly installed local ingress supplier without private paths."""
import json
import os
from pathlib import Path
import subprocess


def config_path():
    base = Path(os.environ.get('XDG_CONFIG_HOME', str(Path.home() / '.config')))
    if not base.is_absolute():
        raise ValueError('XDG_CONFIG_HOME must be absolute')
    path = base / 'fight/local-ingress.json'
    if path != path.resolve():
        raise ValueError('Aliased local ingress discovery refused')
    return path


def supplier():
    value = os.environ.get('FIGHT_INGRESS_SUPPLIER')
    if value is None:
        path = config_path()
        if not path.exists():
            return None
        value = json.loads(path.read_text())['supplier']
    root = Path(value)
    if not root.is_absolute() or root != root.resolve() or not (root / 'bin/local-domain').is_file():
        raise ValueError('Configured local ingress supplier unavailable; repair its local discovery pointer')
    return root


def ensure(root, checkout, task=None):
    provider = supplier()
    if provider is None:
        return None, None
    args = [provider / 'bin/local-domain', 'ensure', '--project-root', root]
    if checkout != root:
        args += ['--worktree', checkout]
    if task:
        args += ['--task', task]
    result = subprocess.run([str(v) for v in args], capture_output=True, text=True)
    if result.returncode:
        raise ValueError(result.stderr.strip() or 'Local host registration failed')
    return provider, json.loads(result.stdout)


def preview(provider, root, checkout):
    args = [provider / 'bin/ingress', 'preview', '--consumer-root', root]
    if checkout != root:
        args += ['--worktree', checkout]
    subprocess.run([str(v) for v in args], check=True)
