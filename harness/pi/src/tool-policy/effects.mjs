import { constants, accessSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname } from 'node:path';
import { spawn } from 'node:child_process';
import { PolicyDenied } from './policy.mjs';

/** Recheck the final canonical target with no JavaScript yield before filesystem access */
export function fileOperations(policy, operation) {
  const target = (path) => policy.target(operation, path);
  const read = (path) => readFileSync(target(path));
  return {
    access: async (path) => { accessSync(target(path), constants.R_OK); },
    readFile: async (path) => read(path),
    writeFile: async (path, content) => {
      if (typeof content !== 'string' || Buffer.byteLength(content) > 1048576) throw new PolicyDenied('content-too-large');
      writeFileSync(target(path), content, 'utf8');
    },
    mkdir: async () => {
      // Pi supplies the parent, but admission stays bound to the original full write target.
      const path = target(operation.input.path);
      mkdirSync(dirname(path), { recursive: true });
    },
    detectImageMimeType: async (path) => {
      const bytes = read(path);
      if (bytes.subarray(0, 8).equals(Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]))) return 'image/png';
      if (bytes[0] === 255 && bytes[1] === 216 && bytes[2] === 255) return 'image/jpeg';
      if (/^GIF8[79]a/.test(bytes.subarray(0, 6).toString('ascii'))) return 'image/gif';
      if (bytes.subarray(0, 4).toString() === 'RIFF' && bytes.subarray(8, 12).toString() === 'WEBP') return 'image/webp';
      if (bytes.subarray(0, 2).toString() === 'BM') return 'image/bmp';
      return undefined;
    },
  };
}

/** Execute only the exact digest-bound script/argv, never a shell command string */
export async function runScript(policy, operation, spawnProcess = spawn) {
  const selected = policy.command(operation);
  const signal = operation.controller.signal;
  if (signal.aborted) throw new PolicyDenied('stale-operation');
  // command() rechecks policy, sources, script digest and cwd immediately before spawn.
  const child = spawnProcess(selected.path, selected.args, { cwd: selected.cwd, detached: true,
    env: { PATH: '/usr/bin:/bin', LANG: 'C', LC_ALL: 'C' }, stdio: ['ignore', 'pipe', 'pipe'] });
  let output = Buffer.alloc(0), truncated = false, timedOut = false;
  const collect = (chunk) => {
    const remaining = 65536 - output.length;
    if (chunk.length > remaining) truncated = true;
    output = Buffer.concat([output, chunk.subarray(0, remaining)]);
  };
  child.stdout.on('data', collect);
  child.stderr.on('data', collect);
  const kill = () => { try { if (child.pid) process.kill(-child.pid, 'SIGKILL'); } catch { /* Already gone */ } };
  signal.addEventListener('abort', kill, { once: true });
  if (signal.aborted) kill();
  const timer = setTimeout(() => { timedOut = true; kill(); }, selected.timeout * 1000);
  try {
    // exit, not close: a detached descendant may retain stdout after the direct child exits.
    const exitCode = await new Promise((resolve, reject) => {
      child.once('error', () => reject(new PolicyDenied('script-start-failed')));
      child.once('exit', (code) => resolve(code ?? 1));
    });
    if (signal.aborted) throw new PolicyDenied('stale-operation');
    if (timedOut) throw new PolicyDenied('script-timeout');
    return { output: output.toString('utf8'), truncated, exitCode };
  } finally {
    clearTimeout(timer);
    signal.removeEventListener('abort', kill);
    kill();
    child.stdout.destroy(); child.stderr.destroy();
  }
}
