"""Exercise the owned host policy without invoking Compose or the operator wrapper."""
from contextlib import redirect_stdout
from io import StringIO
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch

from resource_lifecycle import approved_roots as approvals, runtime
from resource_lifecycle.approved_roots import canonical, mount_facts, qualify
from resource_lifecycle.resources import Hold


class ApprovedRootDecisionTest(unittest.TestCase):
    def test_mount_requires_unique_matching_source_destination_and_mode(self):
        exact = {'Type': 'bind', 'Source': '/fixture/project', 'Destination': '/run/example', 'RW': False}
        self.assertTrue(approvals.exact_bind([exact], '/fixture/project', '/run/example', False))
        for mounts in ([], [exact, exact], *([
                {**exact, key: value}] for key, value in (
                    ('Type', 'volume'), ('Source', '/fixture/other'), ('Destination', '/run/other'), ('RW', True)))):
            with self.subTest(mounts=mounts):
                self.assertFalse(approvals.exact_bind(mounts, '/fixture/project', '/run/example', False))

    def test_mount_and_access_are_independent(self):
        observed = {'mounted': True, 'readable': True, 'writable': True, 'reason': None}
        for exact, probe, reason, expected in (
            (True, observed, None, (True, True, True, None)),
            (True, {}, 'configuration_unavailable', (True, False, False, 'configuration_unavailable')),
            (True, {'mounted': False}, 'mount_table_unavailable', (True, False, False, 'mount_table_unavailable')),
            (False, observed, None, (False, False, False, 'exact_mount_not_observed')),
        ):
            with self.subTest(exact=exact, probe=probe):
                self.assertEqual(mount_facts(exact, probe, reason, True), expected)
        self.assertEqual(mount_facts(True, observed, None, False), (True, True, False, None))


class ApprovedRootPolicyTest(unittest.TestCase):
    def setUp(self):
        temporary = tempfile.TemporaryDirectory()
        self.addCleanup(temporary.cleanup)
        self.base = Path(temporary.name).resolve()
        self.root = self.base / 'project'
        self.root.mkdir()
        self.other = self.base / 'other'
        self.other.mkdir()
        # Direct qualification covers real filesystems; these tests own path policy only.
        fs = patch('resource_lifecycle.approved_roots.filesystem')
        fs.start()
        self.addCleanup(fs.stop)

    def entry(self, path, access='ro'):
        return {'path': str(path), 'access': access, 'identity': canonical(str(path), access, ())}

    def test_distinct_directories_are_qualified(self):
        qualify({'project': self.entry(self.root), 'other': self.entry(self.other, 'rw')}, ())

    def test_relative_missing_ambiguous_and_home_paths_fail(self):
        for raw in ('', '/', 'relative', str(self.base / 'missing'), str(self.base) + '//project',
                    str(self.root) + '/..', str(self.root) + '/.', str(Path.home().resolve())):
            with self.subTest(raw=raw), self.assertRaises(Hold):
                canonical(raw, 'ro', ())

    def test_inaccessible_and_protected_roots_fail(self):
        with patch('resource_lifecycle.approved_roots.os.access', return_value=False):
            with self.assertRaisesRegex(Hold, 'readable, searchable'):
                self.entry(self.root)
        with self.assertRaisesRegex(Hold, 'private installation'):
            canonical(str(self.root), 'ro', (self.root / 'reserved',))

    def test_symlink_and_retargeted_approval_fail(self):
        alias = self.base / 'alias'
        alias.symlink_to(self.other, target_is_directory=True)
        with self.assertRaisesRegex(Hold, 'Symlink'):
            self.entry(alias)
        previous = self.entry(self.root)
        self.root.rename(self.base / 'previous')
        self.root.mkdir()
        with self.assertRaisesRegex(Hold, 'identity changed'):
            qualify({'project': previous}, ())

    def test_startup_admission_rejects_changed_settings_and_unverified_identity(self):
        config = self.base / 'roots.json'
        roots = {'project': self.entry(self.root)}
        approvals.write_private(config, approvals.model(config, roots))
        with patch.object(approvals, 'local_docker'):
            with approvals.admit_reconfiguration(config, self.base, roots, ()) as admitted:
                self.assertEqual(admitted, approvals.model(config, roots, config.parent / 'observed'))
            approvals.write_private(config, approvals.model(config, {'other': self.entry(self.other)}))
            with self.assertRaisesRegex(Hold, 'configuration changed'):
                with approvals.admit_reconfiguration(config, self.base, roots, ()):
                    self.fail('Changed model was admitted')
            approvals.write_private(config, approvals.model(config, roots))
            with patch.object(approvals, 'canonical', side_effect=Hold('Cannot qualify directory identity')):
                with self.assertRaisesRegex(Hold, 'Cannot qualify directory identity'):
                    with approvals.admit_reconfiguration(config, self.base, roots, ()):
                        self.fail('Unverified root was admitted')

    def test_unconfirmed_mount_does_not_publish_application_snapshot(self):
        config = self.base / 'roots.json'
        directory = approvals.observation_directory(config)
        directory.mkdir()
        roots = {'project': self.entry(self.root)}
        admitted = approvals.model(config, roots, directory)
        intended = [
            {'Type': 'bind', 'Source': str(directory), 'Destination': approvals.MANIFEST, 'RW': False},
            {'Type': 'bind', 'Source': str(self.root), 'Destination': approvals.target('project', roots['project']),
             'RW': False},
        ]
        snapshot = directory / config.name
        with patch.object(approvals, 'running_mounts', return_value=('fpm', intended[:1])):
            with self.assertRaisesRegex(Hold, 'did not report the exact'):
                approvals.publish_observation(config, self.base, 'fixture', roots, admitted)
            self.assertFalse(snapshot.exists())
        with patch.object(approvals, 'running_mounts', return_value=('fpm', intended)):
            approvals.publish_observation(config, self.base, 'fixture', roots, admitted)
        self.assertEqual(admitted, snapshot.read_text())

    def test_status_keeps_saved_approvals_when_docker_report_is_unavailable(self):
        config = self.base / 'roots.json'
        roots = {'project': self.entry(self.root, 'rw')}
        for report, reason, mounted, stale in (
            (Hold('internal Docker detail'), 'docker_mount_report_unavailable', None, None),
            (ValueError('internal Docker detail'), 'docker_mount_report_unavailable', None, None),
            ((None, []), 'exact_mount_not_observed', False, 0),
        ):
            with self.subTest(report=report), patch.object(approvals, 'running_mounts') as read_mounts:
                if isinstance(report, Exception):
                    read_mounts.side_effect = report
                else:
                    read_mounts.return_value = report
                output = StringIO()
                with redirect_stdout(output):
                    approvals.status(config, self.base, 'fixture', roots, ())
                self.assertNotIn('internal Docker detail', output.getvalue())
                status = json.loads(output.getvalue())
                self.assertEqual(stale, status['stale_mounts'])
                self.assertEqual(reason if stale is None else None, status.get('reason'))
                self.assertEqual([{'locator': 'root:project', 'canonical_path': str(self.root),
                                   'configured': True, 'write_granted': True, 'mounted': mounted,
                                   'readable': mounted, 'writable': mounted, 'reason': reason}], status['roots'])

    def test_status_reports_snapshot_binds_that_startup_rejects(self):
        config = self.base / 'roots.json'
        directory = approvals.observation_directory(config)
        directory.mkdir()
        roots = {'project': self.entry(self.root)}
        snapshot = {'Type': 'bind', 'Source': str(directory), 'Destination': approvals.MANIFEST, 'RW': False}
        root = {'Type': 'bind', 'Source': str(self.root), 'Destination': approvals.target('project', roots['project']),
                'RW': False}
        for saved, mounts, stale in (
            ({}, [snapshot], 1),
            (roots, [root, {**snapshot, 'Source': str(self.other)}], 1),
            (roots, [root, {**snapshot, 'RW': True}], 1),
            (roots, [root, snapshot, snapshot], 1),
            (roots, [root], 0),
        ):
            with self.subTest(mounts=mounts), \
                    patch.object(approvals, 'running_mounts', return_value=('fpm', mounts)), \
                    patch.object(approvals, 'query') as probe:
                with self.assertRaisesRegex(Hold, 'did not report the exact'):
                    approvals.publish_observation(config, self.base, 'fixture', saved, 'unused')
                output = StringIO()
                with redirect_stdout(output):
                    approvals.status(config, self.base, 'fixture', saved, ())
                result = json.loads(output.getvalue())
                self.assertEqual(stale, result['stale_mounts'])
                if saved:
                    self.assertTrue(result['roots'][0]['mounted'])
                    self.assertFalse(result['roots'][0]['readable'])
                    self.assertEqual('application_observation_unavailable', result['roots'][0]['reason'])
                probe.assert_not_called()

    def test_status_keeps_mount_facts_when_application_observation_fails(self):
        config = self.base / 'roots.json'
        roots = {'project': self.entry(self.root, 'rw')}
        mounts = [
            {'Type': 'bind', 'Source': str(self.base / 'observed'), 'Destination': approvals.MANIFEST, 'RW': False},
            {'Type': 'bind', 'Source': str(self.root), 'Destination': approvals.target('project', roots['project']),
             'RW': True},
        ]
        for report in (Hold('private probe detail'), OSError('private probe detail'),
                       subprocess.TimeoutExpired(['private probe detail'], 1), ValueError('private probe detail'),
                       None, {'roots': None}, {'roots': [None]},
                       {'roots': [{'locator': 'root:project', 'mounted': True, 'readable': 'yes'}]}):
            with self.subTest(report=report), \
                    patch.object(approvals, 'running_mounts', return_value=('fpm', mounts)), \
                    patch.object(approvals, 'query') as probe:
                if isinstance(report, Exception):
                    probe.side_effect = report
                else:
                    probe.return_value = report
                output = StringIO()
                with redirect_stdout(output):
                    approvals.status(config, self.base, 'fixture', roots, ())
                self.assertNotIn('private probe detail', output.getvalue())
                result = json.loads(output.getvalue())
                self.assertEqual(0, result['stale_mounts'])
                self.assertEqual([{'locator': 'root:project', 'canonical_path': str(self.root),
                                   'configured': True, 'write_granted': True, 'mounted': True,
                                   'readable': False, 'writable': False,
                                   'reason': 'application_observation_unavailable'}], result['roots'])

    def test_unconfirmed_empty_model_attempts_bounded_stop_and_preserves_failure(self):
        config = self.base / 'roots.json'
        approvals.observation_directory(config).mkdir()
        mounts = [{'Destination': approvals.MANIFEST}]
        args = ['compose-fixture']
        for stop_error, stop_reason in (
            (None, None),
            (Hold('stop command refused'), 'stop command refused'),
            (subprocess.TimeoutExpired(args, 30), 'timed out after 30 seconds'),
        ):
            with self.subTest(stop_error=stop_error), \
                    patch.object(approvals, 'running_mounts', return_value=('fpm', mounts)), \
                    patch.object(runtime, 'run', side_effect=[None, stop_error]) as command:
                message = 'Docker did not report the exact approved-root mounts'
                if stop_reason:
                    message += '.*' + stop_reason
                with self.assertRaisesRegex(Hold, message):
                    runtime.start_with_approved_roots(args, self.base, 'fixture', config, {}, (), {})
                self.assertEqual(2, command.call_count)
                stop = command.call_args
                self.assertEqual([*args, '--profile', 'https', 'stop', 'https_php'], stop.args[0])
                self.assertEqual(30, stop.kwargs['timeout'])

    def test_duplicate_and_nested_roots_fail_in_either_order(self):
        nested = self.root / 'nested'
        nested.mkdir()
        original = self.entry(self.root)
        for first, second in ((original, original), (original, self.entry(nested)), (self.entry(nested), original)):
            with self.subTest(first=first, second=second), self.assertRaisesRegex(Hold, 'overlapping'):
                qualify({'first': first, 'second': second}, ())

    @unittest.skipUnless(os.name == 'posix', 'POSIX directory permission checks')
    def test_write_must_be_explicit_and_permitted(self):
        def readable_only(_path, mode):
            return mode != os.W_OK

        with patch('resource_lifecycle.approved_roots.os.access', side_effect=readable_only):
            self.entry(self.root)
            with self.assertRaisesRegex(Hold, 'requested write access'):
                self.entry(self.root, 'rw')


if __name__ == '__main__':
    unittest.main()
