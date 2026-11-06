"""Qualify monitor shutdown/error paths with finite owned children, never application tests

Run from the selected checkout: python3 -B harness/pi/tests/native/stage-shutdown.py
No provider, installation or native custody is asserted. Actual native qualification is separate.
"""
import argparse
import contextlib
import errno
import importlib.util
import io
from pathlib import Path
import signal
import subprocess
import sys
import tempfile
from types import SimpleNamespace
import unittest
from unittest.mock import patch

source = Path(__file__).resolve().parents[2] / 'src/observation'
sys.path.insert(0, str(source))
sys.dont_write_bytecode = True
spec = importlib.util.spec_from_file_location('stage_monitor', source / 'run-stage.py')
monitor = importlib.util.module_from_spec(spec)
spec.loader.exec_module(monitor)


class StageShutdown(unittest.TestCase):
    def exercise(self, signal_mode, unresponsive=False):
        children, publications = [], []
        popen, killpg = subprocess.Popen, monitor.os.killpg

        class Observer:
            def __init__(self, *_args):
                self.closed = False
            def publish(self, text, lifecycle, code):
                publications.append((text, lifecycle, code))
            def poll(self):
                pass
            def close(self):
                self.closed = True
                publications.append(('closed', None, None))

        def launch(*_args, **kwargs):
            script = 'import time; print(\'{"type":"agent_start"}\', flush=True); time.sleep(%s)' % (30 if unresponsive else .4)
            child = popen([sys.executable, '-c', script], **kwargs)
            children.append(child)
            return child

        signals = []
        def send(pid, signum):
            signals.append((pid, signum))
            child = children[0]
            if signal_mode == 'denied' or child.returncode is not None:
                raise PermissionError(errno.EPERM, 'PRIVATE diagnostic text must not be rendered')
            return killpg(pid, signum)

        handlers = {s: signal.getsignal(s) for s in (signal.SIGINT, signal.SIGTERM)}
        with tempfile.TemporaryDirectory(prefix='stage-shutdown-', dir=Path.cwd() / '.runs') as directory:
            attempt = Path(directory)
            (attempt / 'request.md').write_text('Finite owning shutdown qualification only\n')
            args = argparse.Namespace(attempt=directory, task='TASK-00585', role='land', provider='fixture', model='controlled',
                thinking='off', timeout=.05, heartbeat=1, harness_package=None, observe_socket=None)
            output = io.StringIO()
            try:
                with patch.object(monitor.subprocess, 'Popen', side_effect=launch), \
                     patch.object(monitor.os, 'killpg', side_effect=send), \
                     patch.object(monitor, 'AutomaticObservation', Observer), contextlib.redirect_stdout(output):
                    if unresponsive:
                        # Keep the real child alive through the finite monitor deadline, and
                        # qualify a refused reap without waiting another ten seconds per test.
                        original_launch = launch
                        def launch_unreapable(*a, **kw):
                            child = original_launch(*a, **kw)
                            child.wait = lambda timeout=None: (_ for _ in ()).throw(subprocess.TimeoutExpired('owned child', timeout))
                            return child
                        with patch.object(monitor.subprocess, 'Popen', side_effect=launch_unreapable):
                            code = monitor.run(args)
                    else:
                        code = monitor.run(args)
                markers = {name: (attempt / name).read_text() for name in ('exit-code', 'pi-exit-code', 'final.txt')}
                diagnostic = (attempt / 'stderr.log').read_text()
            finally:
                for child in children:
                    if child.poll() is None:
                        child.kill()  # Exact finite child handle, not a discovered/reused group
                    subprocess.Popen.wait(child, timeout=5)
                    child.stdout.close()
                    child.stderr.close()
                for signum, handler in handlers.items():
                    signal.signal(signum, handler)
        self.assertEqual(code, 124)
        self.assertEqual(markers['exit-code'], '124\n')
        self.assertEqual(publications[-1], ('closed', None, None))
        self.assertTrue(any(lifecycle == 'failed' and exit_code == 124 for _, lifecycle, exit_code in publications))
        self.assertNotIn('PRIVATE', output.getvalue() + diagnostic)
        self.assertNotIn('Cannot start', output.getvalue())
        return markers, diagnostic, output.getvalue(), signals

    def test_timeout_never_signals_a_reaped_leader_and_still_publishes_terminal(self):
        markers, _, output, signals = self.exercise('late-denial')
        self.assertNotEqual(markers['pi-exit-code'], 'unknown\n')
        self.assertIn('leader-exited', output)
        self.assertLessEqual(len(signals), 2)

    def test_live_signal_denial_is_safe_diagnostic_not_lost_timeout(self):
        _, diagnostic, output, _ = self.exercise('denied')
        self.assertIn('PermissionError', diagnostic)
        self.assertIn('"errno": 1', diagnostic)
        self.assertIn('shutdown unverified', output)

    def test_changed_group_is_never_signalled(self):
        child = SimpleNamespace(pid=12345, returncode=None)
        output, errors = io.StringIO(), io.BytesIO()
        progress = monitor.Progress('land', output)
        with patch.object(monitor.os, 'getpgid', return_value=54321), \
             patch.object(monitor.os, 'killpg') as send, contextlib.redirect_stdout(io.StringIO()):
            monitor.stop_group(child, signal.SIGTERM, progress, errors)
        send.assert_not_called()
        self.assertIn('group-changed', errors.getvalue().decode())

    def test_unreaped_child_is_bounded_and_unknown_not_terminal_evidence_loss(self):
        markers, diagnostic, output, _ = self.exercise('denied', unresponsive=True)
        self.assertEqual(markers['pi-exit-code'], 'unknown\n')
        self.assertIn('TimeoutExpired', diagnostic)
        self.assertIn('shutdown unverified', output)


if __name__ == '__main__':
    (Path.cwd() / '.runs').mkdir(exist_ok=True)
    unittest.main()
