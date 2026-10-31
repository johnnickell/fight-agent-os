#!/usr/bin/env python3
"""Retire exact owned resources with native Git/Docker; never prune or force."""
from contextlib import contextmanager
import fcntl
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import stat
import subprocess
import sys

sys.dont_write_bytecode = True
KINDS = ('containers', 'volumes', 'networks', 'images')


class Hold(Exception):
    pass


class NativeFailure(Hold):
    pass


def require(ok, reason):
    if not ok:
        raise Hold(reason)


def digest(value):
    return hashlib.sha256(value).hexdigest()


def fingerprint(value):
    return digest(json.dumps(value, sort_keys=True, separators=(',', ':')).encode())


def command(argv, cwd, codes=(0,), timeout=60, effect=False):
    result = subprocess.run(argv, cwd=cwd, capture_output=True, timeout=timeout)
    if result.returncode not in codes:
        error = NativeFailure if effect else Hold
        raise error('owning command failed (exit ' + str(result.returncode) + '): ' + ' '.join(argv[:3]))
    return result


def text(argv, cwd, **kwargs):
    return command(argv, cwd, **kwargs).stdout.decode().strip()


def query(argv, cwd):
    return json.loads(text(argv, cwd))


def absolute(value):
    path = Path(value)
    require(path.is_absolute() and path == path.resolve(), 'canonical absolute path required')
    return path


def inside(path, root):
    return path == root or root in path.parents


def atomic(path, value):
    temp = path.with_name(path.name + '.tmp')
    try:
        with temp.open('x') as stream:
            os.chmod(temp, 0o600)
            json.dump(value, stream, indent=2)
            stream.write('\n')
            stream.flush()
            os.fsync(stream.fileno())
        os.replace(temp, path)
    finally:
        temp.unlink(missing_ok=True)


@contextmanager
def locked(path):
    lock = Path(str(path) + '.lock')
    with lock.open('a') as stream:
        fcntl.flock(stream, fcntl.LOCK_EX | fcntl.LOCK_NB)
        yield


def load(path):
    require(path.is_file() and not path.is_symlink(), 'resource record unavailable')
    document = json.loads(path.read_bytes())
    require(document.get('version') == 2, 'old evidence remains read-only; qualify exact resources, not invented history')
    return document


def engine(root):
    require(not os.environ.get('DOCKER_HOST'), 'use the recorded Docker context, not DOCKER_HOST')
    context = query(['docker', 'context', 'inspect'], root)
    require(len(context) == 1 and context[0]['Endpoints']['docker']['Host'].startswith('unix://'), 'local Docker required')
    return {'id': text(['docker', 'info', '--format', '{{.ID}}'], root),
            'endpoint': context[0]['Endpoints']['docker']['Host']}


def inventory(root, kind):
    if kind == 'containers':
        names = text(['docker', 'ps', '-aq', '--no-trunc'], root).splitlines()
        return query(['docker', 'inspect', *names], root) if names else []
    singular = {'volumes': 'volume', 'networks': 'network', 'images': 'image'}[kind]
    names = sorted(set(text(['docker', singular, 'ls', '-q', '--no-trunc'] if kind != 'volumes' else
                            ['docker', 'volume', 'ls', '-q'], root).splitlines()))
    return query(['docker', singular, 'inspect', *names], root) if names else []


def identifier(kind, item):
    return item['Name' if kind == 'volumes' else 'Id']


def identity(kind, item):
    keys = {
        'containers': ('Id', 'Created', 'Image', 'Name', 'Config', 'HostConfig', 'Mounts'),
        'networks': ('Id', 'Name', 'Created', 'Scope', 'Driver', 'EnableIPv4', 'EnableIPv6', 'IPAM',
                     'Internal', 'Attachable', 'Ingress', 'ConfigFrom', 'ConfigOnly', 'Options', 'Labels'),
        'volumes': ('Name', 'Driver', 'Mountpoint', 'CreatedAt', 'Labels', 'Options', 'Scope'),
        'images': ('Id', 'Created')
    }[kind]
    selected = {key: item.get(key) for key in keys}
    if kind == 'containers':
        # Docker/cgroup v2 normalizes false to null on first start; both mean OOM kill enabled.
        selected['HostConfig'] = {**selected['HostConfig'], 'OomKillDisable': bool(selected['HostConfig'].get('OomKillDisable'))}
    return fingerprint(selected)


def process(pid, root):
    return text(['ps', '-p', str(pid), '-o', 'lstart='], root, codes=(0, 1))


def workers_ended(document, root, requesting_owner=False):
    ancestors = set()
    if requesting_owner:
        pid = os.getpid()
        while pid > 1 and pid not in ancestors:
            ancestors.add(pid)
            value = text(['ps', '-p', str(pid), '-o', 'ppid='], root, codes=(0, 1))
            if not value:
                break
            pid = int(value)
    for worker in document.get('workers', []):
        if worker['pid'] in ancestors and worker.get('birth') == process(worker['pid'], root):
            continue
        # PID reuse is held too; never signal a process on the strength of a record.
        require(not process(worker['pid'], root), 'worker live or PID reused: ' + str(worker['pid']))


def host_consumers(candidate, root, owned_lock=None):
    """Check actual users, not source-copy/link/ACL eligibility for container disposal."""
    observed = command(['lsof', '-nP', '-Fpcuaftn'], root, codes=(0, 1))
    require(not observed.stderr.strip(), 'host consumer visibility incomplete')
    processes, current, entry = [], None, None
    for line in observed.stdout.decode().splitlines():
        if line.startswith('p'):
            current = {'pid': int(line[1:]), 'files': []}
            processes.append(current)
        elif current is not None and line.startswith('u'):
            current['uid'] = line[1:]
        elif current is not None and line.startswith('f'):
            entry = {'fd': line[1:]}
            current['files'].append(entry)
        elif entry is not None and line[:1] in ('a', 't', 'n'):
            entry[line[0]] = line[1:]
    for owner in processes:
        matching = [v for v in owner['files'] if v.get('n', '').startswith('/') and
                    inside(Path(v['n'].removesuffix(' (deleted)')), candidate) and not
                    (owned_lock and owner['pid'] == os.getpid() and v.get('t') == 'REG' and
                     v['n'].removesuffix(' (deleted)') == owned_lock)]
        if not matching:
            continue
        require(all(v.get('a') == 'r' and v.get('t') in ('DIR', 'REG', 'LINK') and
                    re.fullmatch(r'\*?\d+', v['fd']) for v in matching), 'active cwd/writer/unknown descriptor')
        require(sys.platform == 'darwin' and owner.get('uid') == str(os.getuid()), 'unknown reader')
        birth = process(owner['pid'], root)
        exe = text(['ps', '-p', str(owner['pid']), '-o', 'comm='], root)
        vm = '/System/Library/Frameworks/Virtualization.framework/Versions/A/XPCServices/com.apple.Virtualization.VirtualMachine.xpc/Contents/MacOS/com.apple.Virtualization.VirtualMachine'
        backend = '/Applications/Docker.app/Contents/MacOS/com.docker.backend'
        require(exe in (vm, backend), 'unknown read-only consumer')
        requirement = 'anchor apple' if exe == vm else 'anchor apple generic and certificate leaf[subject.OU] = "9BNSXJN65R"'
        command(['codesign', '--verify', '--strict', '-R', '=' + requirement, exe], root)
        files = {v.get('n'): v for v in owner['files']}
        if exe == vm:
            disk = str(Path.home() / 'Library/Containers/com.docker.docker/Data/vms/0/data/Docker.raw')
            kernel = '/Applications/Docker.app/Contents/Resources/linuxkit/kernel'
            require(files.get(disk, {}).get('a') == 'u' and files.get(kernel, {}).get('a') == 'r', 'VM association unavailable')
        else:
            for name in (Path.home() / '.docker/run/docker.sock', Path.home() / 'Library/Containers/com.docker.docker/Data/backend.sock'):
                require(files.get(str(name), {}).get('t') == 'unix', 'Docker backend association unavailable')
        require(engine(root)['endpoint'] == 'unix://' + str(Path.home() / '.docker/run/docker.sock'), 'Docker sharing context mismatch')
        require(birth and process(owner['pid'], root) == birth, 'file-sharing process lifetime changed')


def docker_consumers(path, root):
    for container in inventory(root, 'containers'):
        for mount in container['Mounts']:
            if mount['Type'] != 'bind':
                continue
            source = Path(mount['Source']).resolve()
            require(not inside(source, path), 'explicit bind dependency')
            if not inside(path, source) or not container['State']['Running']:
                continue
            exposed = Path(mount['Destination']) / path.relative_to(source)
            probe = 'for p in /proc/[0-9]*; do for f in "$p/cwd" "$p"/fd/*; do [ -L "$f" ] || continue; readlink "$f" || { [ ! -e "$p" ] || echo INSPECTION_UNAVAILABLE; }; done; done'
            observed = command(['docker', 'exec', container['Id'], 'sh', '-c', probe], root)
            paths = observed.stdout.decode().splitlines()
            require(not observed.stderr.strip() and 'INSPECTION_UNAVAILABLE' not in paths, 'parent bind consumer visibility incomplete')
            require(not any(v.startswith('/') and inside(Path(v), exposed) for v in paths), 'actual consumer through parent bind')


def builder_identity(name, root):
    rows = [json.loads(line) for line in text(['docker', 'buildx', 'ls', '--format', '{{json .}}'], root).splitlines()]
    matches = [v for v in rows if v['Name'] == name]
    require(matches and all(v['Driver'] == 'docker-container' for v in matches), 'dedicated builder unavailable')
    values = [{'name': v['Name'], 'driver': v['Driver'], 'nodes': [
        {k: n.get(k) for k in ('Name', 'Endpoint')} for n in v['Nodes']]} for v in matches]
    require(all(v == values[0] for v in values), 'builder identity ambiguous')
    return values[0]


def path_identity(path):
    value = path.lstat()
    birth = getattr(value, 'st_birthtime', None)
    if birth is None:
        birth = text(['stat', '--format=%w', '--', str(path)], path.parent)
        require(birth and birth != '-', 'temporary path lifetime unavailable; hold only this disposal')
    return [value.st_dev, value.st_ino, value.st_mode, value.st_uid, birth]


def disposable_tree(path, root, owned_lock=None):
    """Do not follow links or strip protections; unknown trees never enter this function."""
    host_consumers(path, root, owned_lock)
    paths = [path]
    if path.is_dir() and not path.is_symlink():
        for directory, dirs, files in os.walk(path, followlinks=False):
            paths.extend(Path(directory) / name for name in [*dirs, *files])
    for item in paths:
        info = item.lstat()
        require(info.st_uid == os.getuid() and not getattr(info, 'st_flags', 0), 'foreign owner or filesystem protection')
        if sys.platform == 'darwin':
            acl = text(['ls', '-lde', str(item)], root)
            require(len(acl.splitlines()) == 1, 'ACL protection; no automatic stripping')
        require(not stat.S_ISSOCK(info.st_mode), 'live or unclassified endpoint; its creator must close/unlink it')


class Retirement:
    """Retire independently eligible resources from one validated ownership record"""
    def __init__(self, path, document=None, apply=False):
        self.path = absolute(str(path))
        self.document = document if document is not None else load(self.path)
        self.root = absolute(self.document['safe'])
        self.apply = apply
        self.results = []

    def save(self):
        if self.apply:
            self.document['cleanup'] = self.results
            atomic(self.path, self.document)

    def effect(self, kind, item, operation):
        result = {'kind': kind, 'id': item['id']}
        try:
            if kind in (*KINDS, 'builder'):
                require(engine(self.root) == self.document['engine'], 'Docker context/engine changed before effect')
            operation(result)
        except NativeFailure as error:
            result.update(status='failed', reason=str(error))
        except (Hold, OSError, ValueError, subprocess.SubprocessError) as error:
            result.update(status='held', reason=str(error))
        self.results.append(result)
        self.save()

    def resource(self, kind, item, result, completed_oneoff=False, requesting_owner=False):
        current = next((v for v in inventory(self.root, kind) if identifier(kind, v) == item['id']), None)
        if current is None:
            result['status'] = 'absent'
            return
        require(identity(kind, current) == item['fingerprint'], 'resource generation/configuration changed')
        require(item.get('disposable') and item.get('authority'), item.get('reason', 'unknown/shared/durable resource'))
        containers = inventory(self.root, 'containers')
        if kind == 'containers':
            if completed_oneoff:
                require(not current['State']['Running'], 'own oneoff has not ended')
            else:
                workers_ended(self.document, self.root, requesting_owner)
            pending = self.document.get('pending')
            if pending:
                cli = pending.get('cli')
                require(cli and not process(cli['pid'], self.root), 'original command live or identity unknown')
                # An authorized native stop below ends an uncertain remote workload;
                # it does not manufacture the original exec's outcome.
            if current['State']['Running']:
                workers_ended(self.document, self.root, requesting_owner)
                if self.document.get('checkout') and not requesting_owner and any(
                        m['Type'] == 'bind' and inside(Path(self.document['checkout']), Path(m['Source']).resolve())
                        for m in current['Mounts']):
                    host_consumers(Path(self.document['checkout']), self.root)
                require(self.apply, 'preview: eligible running container')
                command(['docker', 'stop', '--time', '20', item['id']], self.root, effect=True)
            # No -v: an unknown volume never blocks independent container disposal.
            args = ['docker', 'rm', item['id']]
        elif kind == 'volumes':
            require(not any(m.get('Name') == item['id'] for c in containers for m in c['Mounts']), 'volume has current/stopped consumer')
            args = ['docker', 'volume', 'rm', item['id']]
        elif kind == 'networks':
            require(not current.get('Containers') and not any(
                    current['Name'] in c['NetworkSettings']['Networks'] or any(item['id'] == n.get('NetworkID')
                    for n in c['NetworkSettings']['Networks'].values()) for c in containers),
                    'network has current/stopped configured consumer')
            args = ['docker', 'network', 'rm', item['id']]
        else:
            require(not any(item['id'] in (c['Image'], c.get('ImageManifestDescriptor', {}).get('digest')) for c in containers), 'image used/shared')
            require(set(current.get('RepoTags') or []) <= set(item.get('tags', [])), 'image acquired foreign references')
            args = ['docker', 'image', 'rm', item['id']]
        if not self.apply:
            result['status'] = 'eligible'
            return
        # Record identities/mounts BEFORE removal, not a command/session proof archive.
        command(args, self.root, effect=True)
        require(not any(identifier(kind, v) == item['id'] for v in inventory(self.root, kind)), 'removal postcondition failed')
        result['status'] = 'removed'

    def temporary(self, item, result):
        path = Path(item['id'])
        if not path.exists() and not path.is_symlink():
            result['status'] = 'absent'
            return
        require(path_identity(path) == item['identity'], 'temporary path generation changed')
        require(item.get('disposable') and item.get('authority'), 'path not disposable')
        require(path != self.root and not inside(self.root, path), 'cannot dispose safe checkout or ancestor')
        if item.get('empty_only'):
            require(path.is_dir() and not any(path.iterdir()), 'pre-existing or unknown child content; retain parent')
        disposable_tree(path, self.root)
        # Bind sources are dependencies even for stopped containers. Unavailable
        # inventory holds this path, not independent Git/ref or container effects.
        if self.document.get('engine'):
            require(engine(self.root) == self.document['engine'], 'original Docker bind inventory unavailable')
        docker_consumers(path, self.root)
        if self.apply:
            if item.get('empty_only'):
                path.rmdir()
            elif path.is_dir() and not path.is_symlink():
                shutil.rmtree(path)
            else:
                path.unlink()
            require(not path.exists() and not path.is_symlink(), 'temporary path still present')
        result['status'] = 'removed' if self.apply else 'eligible'

    def git(self, result):
        doc = self.document
        candidate = absolute(doc['checkout'])
        require(candidate != self.root and not inside(self.root, candidate), 'main checkout is never disposable')
        workers_ended(doc, self.root)
        discarded = doc.get('discard_oid') == doc['oid'] and doc.get('discard_authority')
        require((doc.get('published_oid') == doc['oid'] or discarded) and doc.get('outcome'), 'record delivery or explicit cancellation disposition before Git cleanup')
        if not discarded:
            from .publication import published
            published(doc, doc['oid'], doc.get('remote_ref'), doc.get('pr'))
        ref = text(['git', 'show-ref', '--verify', '--hash', doc['ref']], self.root, codes=(0, 1))
        if not ref and not candidate.exists():
            registrations = text(['git', 'worktree', 'list', '--porcelain'], self.root)
            require('worktree ' + str(candidate) + '\n' not in registrations and 'branch ' + doc['ref'] not in registrations,
                    'missing source still has a worktree registration')
            result['status'] = 'absent'
            return
        require(ref == doc['oid'], 'local ref contains unqualified work')
        if candidate.exists():
            require(text(['git', 'rev-parse', 'HEAD'], candidate) == doc['oid'], 'checkout changed')
            require(text(['git', 'symbolic-ref', 'HEAD'], candidate) == doc['ref'], 'checkout branch identity changed')
            require(not text(['git', 'status', '--porcelain', '--untracked-files=all'], candidate), 'unique/dirty source')
            ignored = text(['git', 'ls-files', '--others', '--ignored', '--exclude-standard'], candidate)
            require(not ignored, 'unknown/private ignored files; move needed data explicitly or dispose owned temporary paths first')
            disposable_tree(candidate, self.root)
            if doc.get('engine'):
                require(engine(self.root) == doc['engine'], 'original Docker bind inventory unavailable')
            docker_consumers(candidate, self.root)
            if self.apply:
                command(['git', 'worktree', 'remove', str(candidate)], self.root, effect=True)
                require(not candidate.exists(), 'worktree still present')
        registrations = text(['git', 'worktree', 'list', '--porcelain'], self.root)
        if self.apply:
            require('branch ' + doc['ref'] not in registrations, 'branch remains checked out')
            command(['git', 'update-ref', '-d', doc['ref'], doc['oid']], self.root, effect=True)
            require(not text(['git', 'for-each-ref', '--format=%(refname)', doc['ref']], self.root), 'local ref still present')
        result['status'] = 'removed' if self.apply else 'eligible'

    def run(self, runtime_only=False, finish=False):
        require(Path.cwd().resolve() == self.root, 'retirement runs from the canonical safe checkout')
        for kind in KINDS:
            for item in self.document['resources'].get(kind, []):
                self.effect(kind, item, lambda result, k=kind, i=item: self.resource(k, i, result))
        builder = self.document.get('builder')
        if builder:
            def retire_builder(result):
                require(builder['created'] and not self.document.get('pending'), 'builder allocation/consumer unresolved')
                listed = text(['docker', 'buildx', 'ls', '--format', '{{.Name}}'], self.root).splitlines()
                containers = inventory(self.root, 'containers')
                creator = builder.get('container')
                require(not any(c['Name'] == '/buildx_buildkit_' + builder['name'] + '0' and
                                (not creator or c['Id'] != creator['id']) for c in containers), 'builder name reused by foreign creator')
                if builder['name'] not in listed:
                    # Native rm --keep-state or interruption can leave components after
                    # config removal. The same exact-resource functions finish them.
                    components = []
                    for kind, originals in (('containers', [creator] if creator else []), ('volumes', builder.get('volumes', []))):
                        for original in originals:
                            component = {'id': original['id']}
                            item = {**original, 'disposable': True, 'authority': self.document['authority']}
                            self.resource(kind, item, component)
                            components.append(component)
                    status = 'eligible' if any(v['status'] == 'eligible' for v in components) else ('removed' if any(v['status'] == 'removed' for v in components) else 'absent')
                    result.update(status=status, components=components)
                    return
                require(builder_identity(builder['name'], self.root) == builder['identity'], 'builder replaced or rebound')
                if creator:
                    current = next((c for c in containers if c['Id'] == creator['id']), None)
                    require(not current or identity('containers', current) == creator['fingerprint'], 'builder creator changed')
                for volume in builder.get('volumes', []):
                    current = next((v for v in inventory(self.root, 'volumes') if v['Name'] == volume['id']), None)
                    require(not current or identity('volumes', current) == volume['fingerprint'], 'builder cache generation changed')
                    require(not any(c['Id'] != creator['id'] and m.get('Name') == volume['id'] for c in containers for m in c['Mounts']), 'builder cache has foreign consumer')
                require(self.apply, 'preview: eligible owned builder/cache')
                command(['docker', 'buildx', 'rm', builder['name']], self.root, effect=True)
                require(builder['name'] not in text(['docker', 'buildx', 'ls', '--format', '{{.Name}}'], self.root).splitlines(), 'builder still present')
                require(not creator or not any(c['Id'] == creator['id'] for c in inventory(self.root, 'containers')), 'builder container still present')
                require(not any(v['Name'] == old['id'] for v in inventory(self.root, 'volumes') for old in builder.get('volumes', [])), 'builder cache still present')
                result['status'] = 'removed'
            self.effect('builder', {'id': builder['name']}, retire_builder)
        if not runtime_only:
            def retire_path(item, result):
                workers_ended(self.document, self.root)
                require(self.document.get('outcome'), 'record final outcome before disposing evidence')
                require(not self.document.get('pending'), 'original command unresolved; reconcile before finish')
                require(not inside(self.path, Path(item['id'])), 'resource record is retired last')
                self.temporary(item, result)
            # Owned locators/output retire BEFORE ignored-file eligibility. Never classify
            # a pre-existing parent just because the lifecycle placed a file inside it.
            for item in sorted(self.document.get('temporary', []), key=lambda v: len(Path(v['id']).parts), reverse=True):
                self.effect('temporary', item, lambda result, i=item: retire_path(i, result))
            if self.document.get('remove_checkout'):
                self.effect('git', {'id': self.document['checkout']}, self.git)
        # Keep only remaining resources, not an ever-growing history of retired generations.
        for kind in KINDS if self.apply else ():
            retired = {v['id'] for v in self.results if v['kind'] == kind and v['status'] in ('removed', 'absent')}
            self.document['resources'][kind] = [v for v in self.document['resources'].get(kind, []) if v['id'] not in retired]
        if self.apply and any(v['kind'] == 'builder' and v['status'] in ('removed', 'absent') for v in self.results):
            self.document.pop('builder', None)
        if self.apply:
            retired_paths = {v['id'] for v in self.results if v['kind'] == 'temporary' and v['status'] in ('removed', 'absent')}
            self.document['temporary'] = [v for v in self.document.get('temporary', []) if v['id'] not in retired_paths]
        self.save()
        complete = all(v['status'] in ('removed', 'absent') for v in self.results)
        if finish and complete and self.apply and not runtime_only:
            workers_ended(self.document, self.root)
            require(self.document.get('outcome') and not self.document.get('pending'), 'completion/command unresolved')
            require(not self.document.get('preparing'), 'preparation incomplete; retain original resource obligations')
            lock = Path(str(self.path) + '.lock')
            expected, parents, retained = {self.path, lock}, [], []
            # Qualify parents BEFORE destroying the only ownership record. The
            # command's own exact lock is not an unrelated writer/consumer.
            for item in reversed(self.document.get('record_parents', [])):
                parent = Path(item['id'])
                if not parent.exists():
                    continue
                require(path_identity(parent) == item['identity'], 'record parent generation changed')
                if any(child not in expected for child in parent.iterdir()):
                    retained.append(str(parent))  # now shared with unrelated/active content
                    continue
                disposable_tree(parent, self.root, owned_lock=str(lock))
                if self.document.get('engine'):
                    require(engine(self.root) == self.document['engine'], 'record parent bind inventory unavailable')
                docker_consumers(parent, self.root)
                parents.append(parent)
                expected.add(parent)
            self.path.unlink()
            lock.unlink(missing_ok=True)
            for parent in parents:
                parent.rmdir()
            print(json.dumps({'complete': complete, 'results': self.results, 'retained_nonempty_parents': retained}))
        else:
            print(json.dumps({'complete': complete, 'results': self.results}))
        return 0 if complete else 1
