"""Exercise the owned host policy without invoking Compose or the operator wrapper."""
import os
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

from resource_lifecycle.approved_roots import admit_bind, canonical, mount_facts, qualify
from resource_lifecycle.resources import Hold


class ApprovedRootDecisionTest(unittest.TestCase):
    def test_unknown_bind_identity_refuses_nonempty_model(self):
        admit_bind({})
        with self.assertRaisesRegex(Hold, 'bind time'):
            admit_bind({'example': {'identity': [1, 2, 3]}})

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
