#!/usr/bin/env python3
"""One task runtime and resource record; normal wrappers, no command-proof archives."""
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import uuid

sys.dont_write_bytecode = True
from .resources import (Retirement, KINDS, atomic, command, digest, engine, fingerprint,
                        identifier, identity, inventory, load, process, query, require, text, builder_identity)


def compose(doc):
    args = ['docker', 'compose', '--project-directory', doc['checkout'], '--project-name', doc['project']]
    for path in doc['config_files']:
        args += ['--file', path]
    for path in doc['env_files'] or ['/dev/null']:
        args += ['--env-file', path]
    return args


def binding(doc):
    return {name: digest(Path(name).read_bytes()) for name in [*doc['config_files'], *doc['env_files']]}


def storage_admit(root):
    reserve = int(os.environ.get('FIGHT_STORAGE_RESERVE_BYTES', 20 * 1024**3))
    allowance = int(os.environ.get('FIGHT_STORAGE_ALLOCATION_BYTES', 4 * 1024**3))
    require(reserve >= 0 and allowance >= 0, 'negative storage allowance')
    require(shutil.disk_usage(root).free >= reserve + allowance, 'insufficient host space; retire owned resources, never global prune')
    # The daemon must answer too. Logical image sizes are not physical free space.
    command(['docker', 'system', 'df', '--format', '{{json .}}'], root)


class Runtime:
    def __init__(self, root, path, check_docker=True):
        self.root, self.path = root, path
        self.doc = load(path)
        require(self.doc['checkout'] == str(root), 'wrong selected checkout')
        require(binding(self.doc) == self.doc['inputs'], 'runtime config changed; reconcile deliberately, do not start defaults')
        require(text(['git', 'symbolic-ref', 'HEAD'], root) == self.doc['ref'], 'task branch changed')
        if not check_docker:
            return
        require(engine(root) == self.doc['engine'], 'Docker context/engine changed')
        require(fingerprint(query([*compose(self.doc), '--profile', '*', 'config', '--format', 'json'], root)) == self.doc.get('resolved_sha256'),
                'effective runtime configuration drifted; do not recreate or use defaults')
        if not self.doc['pending']:
            known = {v['id']: v['fingerprint'] for v in self.doc['resources']['containers']}
            for current in inventory(root, 'containers'):
                labels = current['Config'].get('Labels') or {}
                if labels.get('com.docker.compose.project') == self.doc['project'] or labels.get('io.fight.task') == self.doc['project']:
                    require(known.get(current['Id']) == identity('containers', current), 'unrecorded/replaced runtime creator; preserve it')

    def save(self):
        atomic(self.path, self.doc)

    def add(self, kind, current, disposable, reason):
        key = identifier(kind, current)
        existing = next((v for v in self.doc['resources'][kind] if v['id'] == key), None)
        if existing:
            require(existing['fingerprint'] == identity(kind, current), 'recorded resource changed')
            return
        item = {'id': key, 'fingerprint': identity(kind, current), 'disposable': disposable,
                'authority': self.doc['authority'] if disposable else '', 'reason': reason}
        if kind == 'containers':
            item['service'] = (current['Config'].get('Labels') or {}).get('com.docker.compose.service', 'oneoff')
            # Preserve only safe mount metadata, not Config.Env or whole source copies.
            item['mounts'] = current['Mounts']
        if kind == 'images':
            item['tags'] = current.get('RepoTags', [])
        self.doc['resources'][kind].append(item)

    def capture(self):
        pending = self.doc['pending']
        if not pending:
            return
        all_items = {kind: inventory(self.root, kind) for kind in KINDS}
        containers = []
        for current in all_items['containers']:
            labels = current.get('Config', {}).get('Labels') or {}
            if identifier('containers', current) in pending['before']['containers']:
                continue
            if labels.get('com.docker.compose.project') != self.doc['project'] and labels.get('io.fight.task') != self.doc['project']:
                continue
            service = labels.get('com.docker.compose.service', 'oneoff')
            disposable = self.doc['services'].get(service, self.doc['services'].get('oneoff', 'unknown')) == 'disposable'
            self.add('containers', current, disposable, 'native creator observed before removal')
            containers.append((current, disposable))
        for kind in ('volumes', 'networks'):
            for current in all_items[kind]:
                key = identifier(kind, current)
                used = [(c, disposable) for c, disposable in containers if
                        (any(m.get('Name') == key for m in c['Mounts']) if kind == 'volumes' else
                         any(v.get('NetworkID') == key for v in c['NetworkSettings']['Networks'].values()))]
                labels = current.get('Labels') or {}
                owned = key not in pending['before'][kind]
                if not used and labels.get('com.docker.compose.project') != self.doc['project']:
                    continue
                disposable = owned and (all(v for _, v in used) if used else kind == 'networks')
                self.add(kind, current, disposable, 'new creator mount/network' if disposable else 'unknown/shared/durable data')
        for current in all_items['images']:
            tags = current.get('RepoTags') or []
            if identifier('images', current) not in pending['before']['images'] and tags and all(tag.startswith(self.doc['project'] + '-') for tag in tags):
                self.add('images', current, True, 'unique task build output; shared bases are not owned')
        builder = self.doc.get('builder')
        if builder and builder.get('created'):
            name = '/buildx_buildkit_' + builder['name'] + '0'
            match = next((c for c in all_items['containers'] if c['Name'] == name), None)
            if match:
                observed = {'id': match['Id'], 'fingerprint': identity('containers', match)}
                require(not builder.get('container') or builder['container'] == observed, 'builder native creator changed')
                builder['container'] = observed
                for mount in match['Mounts']:
                    if mount['Type'] != 'volume':
                        continue
                    volume = next(v for v in all_items['volumes'] if v['Name'] == mount['Name'])
                    entry = {'id': volume['Name'], 'fingerprint': identity('volumes', volume)}
                    captured = next((v for v in builder.get('volumes', []) if v['id'] == entry['id']), None)
                    require(captured == entry or (not captured and entry['id'] not in pending['before']['volumes']), 'builder cache not originally owned')
                    if not captured:
                        builder.setdefault('volumes', []).append(entry)
        self.save()

    def execute(self, argv, kind='allocation'):
        require(not self.doc['pending'], 'prior command incomplete; inspect original runtime, then reconcile once')
        before = {k: [identifier(k, v) for v in inventory(self.root, k)] for k in KINDS}
        require(len([c for c in inventory(self.root, 'containers') if
                     (c['Config'].get('Labels') or {}).get('com.docker.compose.project') == self.doc['project']]) < 16,
                'task container bound reached')
        self.doc['pending'] = {'kind': kind, 'before': before, 'cli': None}
        self.save()
        finished = False
        child = None
        try:
            child = subprocess.Popen(argv, cwd=self.root)
            self.doc['pending']['cli'] = {'pid': child.pid, 'birth': process(child.pid, self.root)}
            self.save()
            code = child.wait(timeout=1800)
            finished = True
            return code
        finally:
            self.capture()
            if finished:
                self.doc['pending'] = None
                self.save()
            # On cancellation/timeout, leave exact CLI/runtime for finite reconciliation.
            # Never signal unrelated/reused PIDs or claim remote exec ended with its CLI.

    def reconcile(self):
        pending = self.doc['pending']
        require(pending, 'no interrupted command')
        require(pending['cli'] and not process(pending['cli']['pid'], self.root), 'original CLI live/reused or identity unavailable')
        self.capture()
        if pending['kind'] == 'exec':
            require(not any(c['State']['Running'] for c in inventory(self.root, 'containers') if
                            c['Id'] in pending['before']['containers'] and any(c['Id'] == v['id'] for v in self.doc['resources']['containers'])),
                    'remote exec outcome unknown; affected original target must end before reuse')
        self.doc['pending'] = None
        self.doc['last_interruption'] = 'Original command outcome unknown; CLI and affected remote target ended before reuse'
        self.save()

    def builder(self):
        name = self.doc['project']
        if not self.doc.get('builder'):
            listed = text(['docker', 'buildx', 'ls', '--format', '{{.Name}}'], self.root).splitlines()
            require(name not in listed and not any(c['Name'] == '/buildx_buildkit_' + name + '0' for c in inventory(self.root, 'containers')),
                    'builder/container already exists; do not claim foreign cache')
            # Reuse an explicitly installed tool base by immutable ID; bootstrap must not
            # refresh a mutable shared tag on every TASK and accumulate old roots.
            base = query(['docker', 'image', 'inspect', 'moby/buildkit:buildx-stable-1'], self.root)[0]
            self.doc['builder'] = {'name': name, 'created': False, 'base_image': base['Id']}
            self.save()
            command(['docker', 'buildx', 'create', '--name', name, '--driver', 'docker-container',
                     '--driver-opt', 'image=' + base['Id'], '--driver-opt', 'memory=512m', '--driver-opt', 'cpu-period=100000', '--driver-opt', 'cpu-quota=100000'], self.root)
            self.doc['builder']['created'] = True
            self.doc['builder']['identity'] = builder_identity(name, self.root)
            self.save()
        require(self.doc['builder']['created'], 'builder creation interrupted; observe exact native identity before recovery')
        require(builder_identity(name, self.root) == self.doc['builder']['identity'], 'builder identity changed')
        return name

    def oneoff(self, argv, standalone=False):
        name = self.doc['project'] + '-oneoff-' + uuid.uuid4().hex[:12]
        # Native create/run -d preserves the creator for a single mount capture; no archive.
        if standalone:
            options = [v for v in argv if v != '--rm']
            args = ['docker', 'create', '--name', name, '--label', 'io.fight.task=' + self.doc['project'], *options]
        else:
            options = [v for v in argv if v != '--rm']
            require('--detach' not in options and '-d' not in options, 'oneoffs are supervised, not detached work')
            args = [*compose(self.doc), 'run', '--detach', '--no-deps', '--name', name, *options]
        code = self.execute(args)
        if code:
            return code
        creator = query(['docker', 'inspect', name], self.root)[0]
        key = creator['Id']
        if standalone:
            code = self.execute(['docker', 'start', '--attach', *(['--interactive'] if creator['Config'].get('OpenStdin') else []), key], 'oneoff')
        else:
            if sys.stdin.isatty() and creator['Config'].get('OpenStdin'):
                self.execute(['docker', 'attach', key], 'oneoff')
            else:
                self.execute(['docker', 'logs', '--follow', key], 'oneoff')
            code = int(text(['docker', 'wait', key], self.root))
        latest = query(['docker', 'inspect', key], self.root)[0]
        require(not latest['State']['Running'], 'oneoff still running; retain original, never repeat allocation')
        # Native workload exit is known; use the SAME cleanup functions, not a second cleaner.
        cleaner = Retirement(self.path, self.doc, apply=True)
        owned = next(v for v in self.doc['resources']['containers'] if v['id'] == key)
        cleaner.effect('containers', owned, lambda result: cleaner.resource('containers', owned, result, completed_oneoff=True))
        for item in list(self.doc['resources']['volumes']):
            if (item['disposable'] and any(m.get('Name') == item['id'] for m in creator['Mounts']) and
                    not any(m.get('Name') == item['id'] for c in inventory(self.root, 'containers') for m in c['Mounts'])):
                cleaner.effect('volumes', item, lambda result, i=item: cleaner.resource('volumes', i, result))
        if not all(v['status'] in ('removed', 'absent') for v in cleaner.results):
            self.doc['last_workload'] = {'exit': code, 'cleanup_complete': False}
            self.save()
            print(json.dumps({'workload_exit': code, 'cleanup_complete': False, 'results': cleaner.results}), file=sys.stderr)
            return code or 1
        # Do not accumulate retired generations across repeated commands.
        for kind in ('containers', 'volumes'):
            retired = {v['id'] for v in cleaner.results if v['kind'] == kind and v['status'] in ('removed', 'absent')}
            self.doc['resources'][kind] = [v for v in self.doc['resources'][kind] if v['id'] not in retired]
        self.doc.pop('cleanup', None)
        self.save()
        return code

    def build_output(self, tag):
        require(tag.startswith(self.doc['project'] + '-'), 'build output must be task-namespaced')
        for image in inventory(self.root, 'images'):
            if any(v in (tag, tag + ':latest') for v in image.get('RepoTags') or []):
                require(any(v['id'] == image['Id'] and v['disposable'] for v in self.doc['resources']['images']),
                        'build would overwrite a shared/unowned image reference')

    def retire_services(self, services):
        require(services and all(re.fullmatch(r'[a-z][a-z0-9_]*', v) for v in services), 'explicit service names required')
        require(not self.doc['pending'], 'original command unresolved')
        cleaner = Retirement(self.path, self.doc, apply=True)
        for item in list(self.doc['resources']['containers']):
            if item.get('service') in services:
                cleaner.effect('containers', item, lambda result, i=item: cleaner.resource('containers', i, result, requesting_owner=True))
        retired = {v['id'] for v in cleaner.results if v['status'] in ('removed', 'absent')}
        self.doc['resources']['containers'] = [v for v in self.doc['resources']['containers'] if v['id'] not in retired]
        self.save()
        return 0 if all(v['status'] in ('removed', 'absent') for v in cleaner.results) else 1

    def docker(self, argv):
        require(argv, 'missing Docker command')
        if argv[0] == 'compose':
            options = argv[1:]
            # Keep profile options; task config/project/env are bound, not ambient.
            index = 0
            while index < len(options) and options[index] == '--profile':
                index += 2
            require(index < len(options), 'missing Compose command')
            verb, tail = options[index], options[index + 1:]
            profiles = options[:index]
            if verb == 'run':
                require(not profiles, 'profile oneoffs require explicit service in the bound config')
                return self.oneoff(tail)
            if verb in ('build', 'up', 'create'):
                storage_admit(self.root)
                if verb == 'build' or '--build' in tail:
                    require(not profiles or verb != 'build', 'select explicit build services')
                    model = query([*compose(self.doc), *profiles, 'config', '--format', 'json'], self.root)
                    for service, config in model['services'].items():
                        if config.get('build'):
                            tag = config.get('image', self.doc['project'] + '-' + service)
                            self.build_output(tag)
                    built = self.execute([*compose(self.doc), *profiles, 'build', '--builder', self.builder(), *([] if verb != 'build' else tail)])
                    if built or verb == 'build':
                        return built
                    tail = [v for v in tail if v != '--build'] + ['--no-build']
                return self.execute([*compose(self.doc), *profiles, verb, *tail])
            if verb in ('stop', 'rm'):
                # Existing service shutdown wrappers share the same exact native cleanup; no force flag is forwarded.
                return self.retire_services([v for v in tail if v not in ('--stop', '--force')])
            require(verb not in ('down', 'kill'), 'use the one exact-resource cleanup path')
            if verb == 'exec':
                require('-T' in tail and '--detach' not in tail and '-d' not in tail, 'exec must be supervised and noninteractive')
                return self.execute([*compose(self.doc), *options], 'exec')
            require(verb in ('ps', 'config', 'port', 'logs', 'images', 'version'), 'unsupported Compose effect')
            return subprocess.run([*compose(self.doc), *options], cwd=self.root, timeout=1800).returncode
        if argv[0] == 'run':
            return self.oneoff(argv[1:], True)
        if argv[0] == 'build':
            tags = [argv[i + 1] for i, value in enumerate(argv[:-1]) if value in ('--tag', '-t')]
            require(len(tags) == 1, 'one task-owned image output required')
            require(not any(v.startswith(('--tag=', '--output', '--cache-to', '--cache-from', '--push', '--builder', '--load=')) or
                            v == '-o' or (v.startswith('-t') and v != '-t') for v in argv[1:]),
                    'unsupported extra output/cache/builder selection')
            self.build_output(tags[0])
            return self.execute(['docker', 'buildx', 'build', '--load', '--builder', self.builder(), *argv[1:]])
        if argv[0] == 'pull':
            # Explicitly pulled images are shared downloads, not task-owned roots.
            storage_admit(self.root)
            return subprocess.run(['docker', *argv], cwd=self.root, timeout=1800).returncode
        require(argv[0] in ('inspect', 'ps', 'info', 'version', 'image', 'volume', 'network') and
                (len(argv) < 2 or argv[0] not in ('image', 'volume', 'network') or argv[1] in ('ls', 'inspect')),
                'unsupported effect; use native task cleanup')
        return subprocess.run(['docker', *argv], cwd=self.root, timeout=1800).returncode
