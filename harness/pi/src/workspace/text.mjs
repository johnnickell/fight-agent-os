// Shared Unicode boundaries for bounded local input and display retention (UTF-16 units).
const segments = new Intl.Segmenter(undefined, { granularity: 'grapheme' });
export const graphemes = text => [...segments.segment(text)].map(part => part.segment);
export function prefix(text, limit) {
  let result = '', rejected = '';
  for (const { segment } of segments.segment(text)) {
    if (!segment.isWellFormed() || result.length + segment.length > limit) { rejected = segment.length <= limit ? segment : ''; break; }
    result += segment;
  }
  return { text: result, omitted: result.length !== text.length, rejected };
}
export function boundedInsert(value, cursor, text, rejected = '') {
  let discarded = false;
  if (rejected) {
    const joined = graphemes(rejected + text)[0];
    if (joined.length > rejected.length) {
      text = text.slice(joined.length - rejected.length);
      rejected = prefix(joined, 32768).text; discarded = true;
    } else rejected = '';
  }
  const parts = graphemes(value), before = parts.slice(0, cursor).join(''), after = parts.slice(cursor).join('');
  const bounded = prefix(before + text, 32768 - after.length);
  if (bounded.omitted) { discarded = true; rejected = bounded.rejected; }
  return { value: bounded.text + after, cursor: graphemes(bounded.text).length, rejected, discarded };
}
export function tail(text, limit) {
  // Start segmentation at the original boundary, not at a possibly split surrogate/ZWJ sequence.
  const start = Math.max(0, text.length - limit);
  for (const part of segments.segment(text)) if (part.index >= start) return text.slice(part.index).toWellFormed();
  return '';
}
