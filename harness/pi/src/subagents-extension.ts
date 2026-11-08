import { fileURLToPath } from 'node:url';
import type { ExtensionAPI } from '@earendil-works/pi-coding-agent';
import installSubagents from '../vendor/pi-subagents/src/index.js';

/** Select Fight's packaged defaults for the single generic execution engine. */
export default function installFightSubagents(pi: ExtensionAPI) {
  installSubagents(pi, fileURLToPath(new URL('../profiles/subagents.json', import.meta.url)));
}
