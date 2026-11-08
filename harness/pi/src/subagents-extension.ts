import { fileURLToPath } from 'node:url';
import type { ExtensionAPI } from '@earendil-works/pi-coding-agent';
import activateSubagents from '../vendor/pi-subagents/src/index.js';

export default function (pi: ExtensionAPI): void {
  const defaultsPath = fileURLToPath(new URL('../profiles/subagents.json', import.meta.url));
  activateSubagents(pi, defaultsPath);
}
