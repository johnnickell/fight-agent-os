"""Own exact, explicitly approved project-root binds for the selected installation."""
import fcntl
import hashlib
import json
import os
from pathlib import Path
import plistlib
import pwd
import re
import stat
import sys

from .resources import Hold, query, require, run, text, write_private

SERVICE = 'https_php'
PREFIX = '/run/fight-approved-roots'
MANIFEST = '/run/fight-root-configuration'
KEY = 'x-fight-approved-roots'
LIMIT = 16
NAME = re.compile(r'[a-z][a-z0-9-]{0,31}')
# Avoid Compose interpolation, ambiguous mount delimiters and unsafe terminal output.
PATH = re.compile(r'/[A-Za-z0-9_./ -]{1,1023}')


def overlaps(first, second):
    return first == second or first in second.parents or second in first.parents


def beneath(path, parent):
    return path == parent or parent in path.parents


def filesystem(path):
    """Accept only supported local fixed media, with no host mounts inside the tree."""
    if sys.platform == 'darwin':
        mounts = []
        for line in text(['/sbin/mount'], path).splitlines():
            source, sep, rest = line.partition(' on ')
            mount, options, _ = rest.rpartition(' (')
            require(sep and options and mount.startswith('/'), 'Cannot qualify the host mount table')
            mounts.append((Path(mount), source))
        # macOS also exposes the Data volume through firmlinks such as /Users.
        data = Path('/System/Volumes/Data')
        backing = data / path.relative_to('/')
        visible = [path]
        if backing.exists() and path.samefile(backing):
            visible.append(backing)
        require(not any(candidate in point.parents for candidate in visible for point, _ in mounts),
                'Root contains another filesystem mount')
        point, source = max((entry for entry in mounts if any(beneath(p, entry[0]) for p in visible)),
                            key=lambda entry: len(entry[0].parts))
        require(source.startswith('/dev/disk'), 'Root is not on supported fixed local storage')
        info = plistlib.loads(text(['/usr/sbin/diskutil', 'info', '-plist', source], path).encode())
        require(info.get('Internal') is True and info.get('RemovableMedia') is False and
                info.get('BusProtocol') in ('Apple Fabric', 'PCI', 'PCI-Express', 'SATA', 'NVMe') and
                info.get('FilesystemType') == 'apfs' and info.get('MountPoint') == str(point),
                'Root is not on supported fixed local APFS storage')
    elif sys.platform == 'linux':
        mounts = []
        for line in Path('/proc/self/mountinfo').read_text().splitlines():
            before, after = line.split(' - ', 1)
            fields = before.split()
            decoded = re.sub(r'\\([0-7]{3})', lambda m: chr(int(m[1], 8)), fields[4])
            mounts.append((Path(decoded), fields[3], fields[2], after.split()[0]))
        require(not any(path in point.parents for point, *_ in mounts), 'Root contains another filesystem mount')
        matches = [entry for entry in mounts if beneath(path, entry[0])]
        depth = max(len(entry[0].parts) for entry in matches)
        deepest = [entry for entry in matches if len(entry[0].parts) == depth]
        require(len(deepest) == 1, 'Host filesystem mount is ambiguous')
        point, mount_root, device, kind = deepest[0]
        require(point != path and sum(entry[2] == device for entry in mounts) == 1,
                'Whole filesystem mounts and filesystem aliases are not accepted')
        block = (Path('/sys/dev/block') / device).resolve()
        disk = block.parent if (block / 'partition').exists() else block
        # Virtual/stacked block devices cannot prove their physical backing here.
        pci = any(re.fullmatch(r'pci[0-9a-f]{4}:[0-9a-f]{2}', part) for part in disk.parts)
        direct = 'nvme' in disk.parts or any(re.fullmatch(r'ata[0-9]+', part) for part in disk.parts)
        require(kind in ('ext4', 'xfs') and mount_root == '/' and pci and direct and
                not any(part.startswith('usb') for part in disk.parts) and
                (disk / 'removable').is_file() and (disk / 'removable').read_text().strip() == '0',
                'Root is not on supported fixed local Linux storage')
    else:
        raise Hold('Host platform is not supported for approved roots')


def canonical(raw, access, protected):
    try:
        path = Path(raw)
        require(PATH.fullmatch(raw) and str(path) == raw and '..' not in path.parts and len(path.parts) > 2,
                'Use an explicit absolute bounded root without traversal or ambiguous components')
        require(path.resolve(strict=True) == path, 'Symlink aliases and escaping paths are not accepted')
        for at in (path, *path.parents):
            require(not at.is_symlink(), 'Symlink aliases and escaping paths are not accepted')
        current = pwd.getpwuid(os.getuid()).pw_dir
        homes = {Path(current).resolve(), *(Path(user.pw_dir).resolve() for user in pwd.getpwall()
                 if user.pw_dir.startswith('/') and user.pw_dir not in ('/', '/nonexistent', '/var/empty'))}
        require(not any(beneath(home, path) or (home.exists() and path.samefile(home)) for home in homes)
                and not (len(path.parts) == 3 and path.parts[1].casefold() in ('home', 'users')),
                'Complete home directories and their parents are not accepted')
        for item in protected:
            require(not overlaps(path, item) and not any(
                left.exists() and right.exists() and left.samefile(right)
                for left, right in [(path, node) for node in (item, *item.parents)] +
                [(node, item) for node in path.parents]), 'Root overlaps private installation state')
        require(path.is_dir() and os.access(path, os.R_OK | os.X_OK), 'Root is not a readable, searchable directory')
        if access == 'rw':
            require(os.access(path, os.W_OK), 'Root does not permit the requested write access')
        fd = os.open(path, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW)
        try:
            if sys.platform == 'darwin':
                observed = os.fsdecode(fcntl.fcntl(fd, fcntl.F_GETPATH, bytes(1024)).split(b'\0', 1)[0])
                require(observed == raw, 'Use the canonical spelling of the directory')
            filesystem(path)
            metadata = os.fstat(fd)
            birth = getattr(metadata, 'st_birthtime_ns', None)
            if birth is None and sys.platform == 'darwin':
                birth = int(metadata.st_birthtime * 1_000_000_000)
            if birth is None and sys.platform == 'linux':
                # A recycled inode is not the original approval. GNU stat supplies birth time on supported media.
                stamp = run(['/usr/bin/stat', '--format=%w', '--', path], path, capture=True,
                            env={**os.environ, 'LC_ALL': 'C', 'TZ': 'UTC0'})
                if re.fullmatch(r'[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]{9} \+0000', stamp):
                    birth = int(re.sub(r'\D', '', stamp)[:-4])
            current = path.stat()
            require(os.path.samestat(metadata, current) and path.resolve(strict=True) == path,
                    'Root identity changed during qualification')
            require(birth is not None, 'Cannot qualify a stable directory creation identity')
            return [metadata.st_dev, metadata.st_ino, birth]
        finally:
            os.close(fd)
    except (OSError, RuntimeError, ValueError, KeyError):
        raise Hold('Root or host filesystem is missing, inaccessible or cannot be qualified') from None


def fingerprint(value):
    return hashlib.sha256(json.dumps(value, sort_keys=True).encode()).hexdigest()


def target(name, root):
    return f'{PREFIX}/{name}-{fingerprint(root)}'


def bind(source, destination, readonly):
    return {'type': 'bind', 'source': str(source), 'target': destination, 'read_only': readonly,
            'bind': {'create_host_path': False, 'propagation': 'rprivate'}}


def config_file(safe, project, isolated):
    state = safe / ('var/worktrees/' + project if isolated else 'var/runtime')
    path = state / 'approved-roots/roots.json'
    require(path.resolve() == path and not path.is_symlink(), 'Approved-root configuration alias refused')
    return path


def model(config, roots):
    entries = {name: {**root, 'target': target(name, root)} for name, root in roots.items()}
    document = {KEY: {'version': 1, 'roots': entries}, 'services': {SERVICE: {'volumes': [
        bind(config.parent, MANIFEST, True),
        *(bind(root['path'], root['target'], root['access'] == 'ro') for root in entries.values())]}}}
    return json.dumps(document, indent=2) + '\n'


def load(config):
    if not config.exists():
        return {}
    try:
        require(config.is_file() and config.stat().st_uid == os.getuid() and
                stat.S_IMODE(config.stat().st_mode) == 0o600 and config.stat().st_size <= 65536,
                'Approved-root settings must be a private regular file of bounded size')
        source = config.read_text()
        data = json.loads(source)
        roots = data[KEY]['roots']
        require(isinstance(roots, dict) and len(roots) <= LIMIT, 'Invalid approved-root configuration')
        for name, root in roots.items():
            require(NAME.fullmatch(name) and set(root) == {'access', 'path', 'identity', 'target'} and
                    root['access'] in ('ro', 'rw') and PATH.fullmatch(root['path']) and
                    isinstance(root['identity'], list) and len(root['identity']) == 3 and
                    all(type(part) is int for part in root['identity']), 'Invalid approved-root entry')
        result = {name: {key: value for key, value in root.items() if key != 'target'} for name, root in roots.items()}
        require(source == model(config, result), 'Approved-root configuration does not match its exact mount declarations')
        return result
    except (OSError, TypeError, ValueError, AttributeError, KeyError):
        raise Hold('Invalid approved-root configuration; restore a known version before starting FPM') from None


def qualify(roots, protected):
    require(len(roots) <= LIMIT, f'At most {LIMIT} roots may be approved')
    seen = set()
    ancestors = set()
    for root in roots.values():
        path = Path(root['path'])
        require(canonical(root['path'], root['access'], protected) == root['identity'],
                'Approved root identity changed; preview an explicit change or remove it')
        identity = tuple(root['identity'][:2])
        try:
            lineage = {(metadata.st_dev, metadata.st_ino) for metadata in (parent.stat() for parent in path.parents)}
        except OSError:
            raise Hold('Approved root ancestry changed during qualification') from None
        require(identity not in seen | ancestors and not seen.intersection(lineage),
                'Duplicate or overlapping approved roots are not accepted')
        seen.add(identity)
        ancestors.update(lineage)


def protected_paths(safe, checkout):
    reserved = (safe / 'var', safe / '.env', safe / '.git', safe / '.runs/compose')
    checkout_env = checkout / '.env'
    return (*reserved, checkout_env) if checkout != safe and checkout_env.exists() else reserved


def local_docker(checkout):
    require(not os.environ.get('DOCKER_HOST') and not os.environ.get('DOCKER_CONTEXT'),
            'Use the selected local Unix Docker context without environment overrides')
    require(text(['docker', 'context', 'inspect', '--format', '{{.Endpoints.docker.Host}}'], checkout).startswith('unix:///'),
            'A local Unix Docker context is required for approved roots')


def admit_bind(roots):
    # Docker resolves literal source paths itself. Neither Compose nor its Engine bind
    # API can require the directory identity that the host previously qualified.
    require(not roots, 'Cannot verify approved root identity at bind time; no root binds were applied')


def admit_reconfiguration(safe, project, isolated):
    admit_bind(load(config_file(safe, project, isolated)))


def running_mounts(checkout, project):
    local_docker(checkout)
    ids = text(['docker', 'ps', '-q', '--filter', 'label=com.docker.compose.project=' + project,
                '--filter', 'label=com.docker.compose.service=' + SERVICE,
                '--filter', 'label=com.docker.compose.oneoff=False'], checkout).splitlines()
    require(len(ids) <= 1, 'Cannot identify a unique running application service')
    return (ids[0], query(['docker', 'inspect', '--format', '{{json .Mounts}}', ids[0]], checkout)) if ids else (None, [])


def mount_facts(exact, observation, probe_reason, write_granted):
    if not exact:
        return False, False, False, 'exact_mount_not_observed'
    if not observation.get('mounted'):
        return True, False, False, probe_reason or observation.get('reason') or 'application_observation_unavailable'
    return True, observation.get('readable', False), write_granted and observation.get('writable', False), observation.get('reason')


def status(checkout, project, roots, protected):
    container, mounts = running_mounts(checkout, project)
    facts = {}
    if container:
        code = ('require "/app/vendor/autoload.php";'
                'echo json_encode((new App\\Adapter\\Installation\\ApprovedRootAccess())->observe());')
        try:
            facts = query(['docker', 'exec', container, 'php', '-r', code], checkout)
        except (Hold, ValueError):
            facts = {'reason': 'application_observation_unavailable'}
    results = []
    pending = {mount['Destination'] for mount in mounts if mount['Destination'].startswith(PREFIX + '/')}
    for name, root in roots.items():
        destination = target(name, root)
        exact = any(m['Destination'] == destination and m['Type'] == 'bind' and m['Source'] == root['path']
                    and m['RW'] == (root['access'] == 'rw') for m in mounts)
        if exact:
            pending.discard(destination)
        observation = next((fact for fact in facts.get('roots', []) if fact['locator'] == 'root:' + name), {})
        write_granted = root['access'] == 'rw'
        mounted, readable, writable, reason = mount_facts(exact, observation, facts.get('reason'), write_granted)
        try:
            current = canonical(root['path'], root['access'], protected) == root['identity']
        except Hold:
            current = False
        results.append({'locator': 'root:' + name, 'canonical_path': root['path'], 'configured': True,
                        'write_granted': write_granted, 'mounted': mounted,
                        'readable': readable, 'writable': writable,
                        'reason': reason if current else 'host_root_unqualified'})
    print(json.dumps({'roots': results, 'stale_mounts': len(pending)}, indent=2))


def operate(safe, checkout, project, isolated, args):
    config = config_file(safe, project, isolated)
    roots = load(config)
    protected = protected_paths(safe, checkout)
    if args.operation == 'status':
        status(checkout, project, roots, protected)
        return
    name = args.name
    require(NAME.fullmatch(name), 'Use a short lowercase root name (letters, digits and hyphens)')
    before = roots.get(name)
    require((before is None) == (args.operation == 'add'), 'Root name already exists or is not configured')
    after = None if args.operation == 'remove' else {
        'path': args.path, 'access': args.access,
        'identity': canonical(args.path, args.access, protected)}
    proposed = {key: value for key, value in roots.items() if key != name}
    if after is not None:
        proposed[name] = after
        qualify(proposed, protected)
    digest = fingerprint([str(config), roots, name, args.operation, after])
    restart = './bin/up --worktree ' + str(checkout) if isolated else './bin/up (preserve your existing ingress mode)'
    print(json.dumps({'operation': args.operation, 'locator': 'root:' + name, 'configuration': str(config),
                      'before': before['path'] if before else None, 'before_access': before['access'] if before else None,
                      'after': after['path'] if after else None, 'access': after['access'] if after else None,
                      'reconfigure': restart,
                      'expected': 'Only settings change; runtime reconfiguration refuses nonempty approvals without bind-time identity',
                      'recovery': 'Preview and apply the inverse change; stop FPM to end existing exposure; no source is removed',
                      'apply_digest': digest}, indent=2))
    if args.apply is None:
        return
    require(args.apply == digest, 'Preview no longer matches; review a new preview before applying')
    config.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
    require(config.parent.resolve() == config.parent, 'Approved-root configuration directory alias refused')
    fd = os.open(config.parent, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW)
    try:
        fcntl.flock(fd, fcntl.LOCK_EX)
        require(load(config) == roots, 'Configuration changed since preview; review a new preview')
        if after is not None:
            qualify(proposed, protected_paths(safe, checkout))
        write_private(config, model(config, proposed))
    finally:
        os.close(fd)
    print('Configuration saved. Runtime exposure is unchanged until explicit reconfiguration.')
