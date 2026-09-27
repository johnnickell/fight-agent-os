import { mark } from './mark.mjs';

export const modes = Object.freeze(['auto', 'port', 'wordmark', 'compact', 'ascii', 'off']);

export function parseMode(value) {
  return modes.includes(value) ? value : undefined;
}

export function displayMode(preference, hasConversation) {
  return preference === 'auto' ? (hasConversation ? 'compact' : 'port') : preference;
}

export function hasConversation(entries) {
  return entries.some(entry => entry.type === 'message');
}

const wordmark = [
  '█████  █████   ████  █   █  █████',
  '█        █    █      █   █    █  ',
  '████     █    █  ██  █████    █  ',
  '█        █    █   █  █   █    █  ',
  '█      █████   ████  █   █    █  ',
];

/** Return semantic color spans; the Pi adapter owns styling and final cell truncation. */
export function headerRows(mode, width, plain = false) {
  if (!parseMode(mode)) throw new RangeError(`Unknown Fight header mode: ${mode}`);
  if (!Number.isFinite(width) || width < 1 || mode === 'off') return [];

  if (plain || mode === 'ascii' || width < 56) {
    return [[['text', width < 15 ? 'FIGHT' : 'F FIGHT AGENT OS']]];
  }

  if (mode === 'compact') {
    return [[['accent', '▰ '], ['text', 'FIGHT AGENT OS'], ['dim', '  /  terminal workspace']]];
  }

  if (mode === 'wordmark') {
    return [[], ...wordmark.map(line => [['text', `  ${line}`]]),
      [['accent', '  A G E N T   O S']], []];
  }

  const copy = {
    2: [['text', 'F I G H T']],
    3: [['accent', 'AGENT OS']],
    5: [['muted', 'Your engineering workspace.']],
    7: [['dim', '/fight-header · appearance']],
  };
  return [[], ...mark.map((line, index) => [
    ['text', '  '], ...line, ['text', '   '], ...(copy[index] ?? []),
  ]), []];
}

/** Produce width-bounded rows, including terminals narrower than the fallback label. */
export function renderHeader(mode, width, style, plain = false) {
  return headerRows(mode, width, plain).map(line => {
    let remaining = Math.max(0, Math.floor(width));
    return line.map(([role, text]) => {
      // The owned alphabet consists only of single-cell ASCII and block glyphs.
      const part = [...text].slice(0, remaining).join('');
      remaining -= [...part].length;
      return plain ? part : style(role, part);
    }).join('');
  });
}
