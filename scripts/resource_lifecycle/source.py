"""Source-removal guards, independent of explicit disposable Docker teardown."""
import json
import os
from pathlib import Path, PurePosixPath
import plistlib
import pwd
import stat
import subprocess
import sys
from uuid import UUID
from xml.parsers.expat import ExpatError

from .resources import Hold, query, require, run, text


def parent_mount_unused(container, mount, checkout, source, safe):
    """Qualify parent-only exposure without stopping unrelated application services"""
    target = str(PurePosixPath(mount['Destination']) / checkout.relative_to(source))
    require(container['State']['Running'], 'Parent source mount requires live consumer qualification')
    configured = json.dumps({key: container['Config'].get(key) for key in ('WorkingDir', 'Entrypoint', 'Cmd')})
    require(target not in configured, 'Container configuration consumes candidate source')
    # Use the configured container identity: root without ptrace capabilities
    # can have less /proc visibility than the application user. Check every
    # process and retain source if any inspection remains unavailable.
    # Return status only: no process arguments, descriptors or private data escape.
    script = r'''
for process in /proc/[0-9]*; do
    [ "$process" = "/proc/$$" ] && continue
    [ -d "$process" ] || continue
    [ -r "$process/cmdline" ] && [ -r "$process/maps" ] && [ -r "$process/fd" ] || exit 2
    grep -F -q -- "$1" "$process/cmdline" "$process/maps"
    status=$?
    [ "$status" -eq 0 ] && exit 3
    [ "$status" -eq 1 ] || exit 2
    for descriptor in "$process/cwd" "$process/exe" "$process"/fd/*; do
        [ -L "$descriptor" ] || continue
        value=$(readlink "$descriptor") || { [ ! -L "$descriptor" ] && continue; exit 2; }
        case "$value" in "$1"|"$1"/*) exit 3 ;; esac
    done
done
'''
    result = subprocess.run(['docker', 'exec', container['Id'],
                             'sh', '-c', script, 'source-consumer-check', target],
                            cwd=safe, capture_output=True, text=True, timeout=60)
    require(result.returncode != 3, 'Parent-mounted container consumes candidate source: ' + container['Id'][:12])
    require(result.returncode == 0,
            'Parent-mounted container process visibility unavailable with configured identity: ' + container['Id'][:12])


def readonly_docker_handles(output, safe):
    """Admit only positively identified macOS Docker file-sharing read handles"""
    records, process, descriptor = [], {}, None
    for line in output.splitlines():
        key, value = line[:1], line[1:]
        if key == 'p':
            process, descriptor = {'pid': value}, None
        elif key == 'u':
            process['uid'] = value
        elif key == 'f':
            descriptor = {'process': process, 'fd': value}
            records.append(descriptor)
        elif descriptor is not None and key in ('a', 't'):
            descriptor[key] = value
    require(records, 'Source consumer visibility unavailable')
    qualified = set()
    for record in records:
        process = record['process']
        require(record.get('a') == 'r' and record.get('t') in ('REG', 'DIR') and
                record['fd'].removeprefix('*').isdigit() and process.get('uid') == str(os.getuid()),
                'Source has active/unknown cwd, writer or reader; retain source')
        pid = process['pid']
        if pid not in qualified:
            executable = text(['ps', '-p', pid, '-o', 'comm='], safe)
            require(executable == '/System/Library/Frameworks/Virtualization.framework/Versions/A/'
                                  'XPCServices/com.apple.Virtualization.VirtualMachine.xpc/Contents/MacOS/'
                                  'com.apple.Virtualization.VirtualMachine',
                    'Source reader is not qualified Docker file sharing')
            handles = text(['lsof', '-nP', '-p', pid, '-Fn'], safe).splitlines()
            disk = Path.home() / 'Library/Containers/com.docker.docker/Data/vms/0/data/Docker.raw'
            require(disk.is_file() and 'n' + str(disk) in handles,
                    'Virtualization reader lacks actual Docker runtime backing evidence')
            qualified.add(pid)


def runtime_mount_owner(safe):
    """Resolve the invoking account without membership lookup's synthetic UUID fallback"""
    message = ('Native macOS account lookup unavailable or unverifiable; retain source and rerun '
               'the same guarded bin/runtime finish from the canonical checkout with native macOS '
               'account access. Do not strip ACLs manually or substitute a guessed owner UUID.')
    try:
        account = pwd.getpwuid(os.getuid()).pw_name
        result = subprocess.run(['dscl', '-plist', '/Search', '-read', '/Users/' + account,
                                 'GeneratedUID', 'UniqueID'], cwd=safe, capture_output=True, timeout=30)
    except (KeyError, OSError, subprocess.TimeoutExpired):
        raise Hold(message) from None
    require(result.returncode == 0, message)
    try:
        record = plistlib.loads(result.stdout)
    except (plistlib.InvalidFileException, ExpatError, ValueError, TypeError):
        raise Hold(message) from None
    require(isinstance(record, dict), message)
    owner = record.get('dsAttrTypeStandard:GeneratedUID')
    require(record.get('dsAttrTypeStandard:UniqueID') == [str(os.getuid())] and
            isinstance(owner, list) and len(owner) == 1 and isinstance(owner[0], str), message)
    try:
        return str(UUID(owner[0])).upper()
    except ValueError:
        raise Hold(message) from None


def runtime_mount_acl(checkout, safe, path, acl):
    """Qualify only the owner's delete rule on empty reserved runtime mount targets"""
    require(path in (checkout / '.runs', checkout / 'var', checkout / 'packages/react/dist') and
            not path.is_symlink(),
            'Source ACL protection outside reserved runtime mount targets: ' + str(path))
    info = path.lstat()
    require(stat.S_ISDIR(info.st_mode) and info.st_uid == os.getuid() and not getattr(info, 'st_flags', 0),
            'Runtime mount target protection/foreign owner: ' + str(path))
    directories = [path]
    for directory in directories:
        # iterdir propagates access errors: an unreadable directory is not empty.
        for child in directory.iterdir():
            child_info = child.lstat()
            require(stat.S_ISDIR(child_info.st_mode) and child_info.st_uid == os.getuid() and
                    not getattr(child_info, 'st_flags', 0),
                    'Runtime mount target still contains data, aliases or protections: ' + str(path))
            require(len(text(['ls', '-lden', str(child)], safe).splitlines()) == 1,
                    'Runtime mount descendant ACL protection: ' + str(child))
            directories.append(child)
    run(['git', 'check-ignore', '--quiet', '--', str(path)], checkout)
    owner = runtime_mount_owner(safe)
    require(acl == ['0: ' + owner + ' deny delete'],
            'Source ACL protection is not the sole owner delete rule: ' + str(path))
    return info.st_dev, info.st_ino


def release_runtime_mounts(checkout, safe, targets):
    """Recheck qualified mount targets before releasing their exact delete rules"""
    # No permission effect until every target still matches the source inspection.
    for path, identity, acl in targets:
        current = [line.strip() for line in text(['ls', '-lden', str(path)], safe).splitlines()[1:]]
        require(current == acl and runtime_mount_acl(checkout, safe, path, current) == identity,
                'Runtime mount target changed before retirement: ' + str(path))
    for path, identity, acl in targets:
        current = [line.strip() for line in text(['ls', '-lden', str(path)], safe).splitlines()[1:]]
        require(current == acl and runtime_mount_acl(checkout, safe, path, current) == identity,
                'Runtime mount target changed before retirement: ' + str(path))
        run(['chmod', '-a#', '0', str(path)], safe)
        require(len(text(['ls', '-lden', str(path)], safe).splitlines()) == 1,
                'Runtime mount target ACL retirement incomplete: ' + str(path))
        print('Released owner delete rule on retired runtime mount target: ' + str(path))


def check(checkout, safe, *, retire_runtime_mounts=False):
    # Exact/child mounts always hold, including stopped containers. Parent-only
    # exposure needs positive live qualification, not shared shutdown.
    ids = text(['docker', 'ps', '-aq'], safe).splitlines()
    for container in query(['docker', 'inspect', *ids], safe) if ids else []:
        for mount in container['Mounts']:
            if mount['Type'] == 'bind':
                source = Path(mount['Source']).resolve()
                require(source != checkout and checkout not in source.parents,
                        'Source bind dependency remains; qualify the actual consumer before source removal')
                if source in checkout.parents:
                    parent_mount_unused(container, mount, checkout, source, safe)
    result = subprocess.run(['lsof', '-nP', '-Fpuaftn', '+D', str(checkout)], cwd=safe,
                            capture_output=True, text=True, timeout=60)
    require(result.returncode in (0, 1) and not result.stderr.strip(), 'Source consumer visibility unavailable')
    # All actual container mounts/processes were checked above. Cached read-only
    # Docker VM handles are not an active task; unknown readers/writers still hold.
    if result.stdout.strip():
        readonly_docker_handles(result.stdout, safe)
    targets = {}
    for directory, dirs, files in os.walk(checkout, followlinks=False):
        for path in [Path(directory), *(Path(directory) / name for name in [*dirs, *files])]:
            info = path.lstat()
            require(info.st_uid == os.getuid() and not getattr(info, 'st_flags', 0), 'Source filesystem protection/foreign owner')
            require(not stat.S_ISSOCK(info.st_mode), 'Source socket remains')
            if sys.platform == 'darwin':
                acl = [line.strip() for line in text(['ls', '-lden', str(path)], safe).splitlines()[1:]]
                if acl:
                    require(retire_runtime_mounts, 'Source ACL protection; no automatic stripping: ' + str(path))
                    identity = runtime_mount_acl(checkout, safe, path, acl)
                    targets[path] = (identity, acl)
    return [(path, *value) for path, value in sorted(targets.items())]
