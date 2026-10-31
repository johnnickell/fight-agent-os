"""Source-removal guards, independent of explicit disposable Docker teardown."""
import os
from pathlib import Path
import stat
import subprocess
import sys

from .resources import query, require, text


def check(checkout, safe):
    # Bind consumers include stopped containers. A broad parent mount requires
    # attended qualification under Land, never a shared-service shutdown here.
    ids = text(['docker', 'ps', '-aq'], safe).splitlines()
    for container in query(['docker', 'inspect', *ids], safe) if ids else []:
        for mount in container['Mounts']:
            if mount['Type'] == 'bind':
                source = Path(mount['Source']).resolve()
                require(source != checkout and source not in checkout.parents and checkout not in source.parents,
                        'Source bind dependency remains; qualify the actual consumer before source removal')
    result = subprocess.run(['lsof', '-nP', '-Fpaftn', '+D', str(checkout)], cwd=safe,
                            capture_output=True, text=True, timeout=60)
    require(result.returncode in (0, 1) and not result.stderr.strip(), 'Source consumer visibility unavailable')
    # Conservative source-only hold for any actual open descriptor; explicit down
    # already ran. Land can qualify read-only virtualization handles separately.
    require(not result.stdout.strip(), 'Source still has open/cwd consumers; use attended Land qualification')
    for directory, dirs, files in os.walk(checkout, followlinks=False):
        for path in [Path(directory), *(Path(directory) / name for name in [*dirs, *files])]:
            info = path.lstat()
            require(info.st_uid == os.getuid() and not getattr(info, 'st_flags', 0), 'Source filesystem protection/foreign owner')
            require(not stat.S_ISSOCK(info.st_mode), 'Source socket remains')
            if sys.platform == 'darwin':
                require(len(text(['ls', '-lde', str(path)], safe).splitlines()) == 1,
                        'Source ACL protection; no automatic stripping')
