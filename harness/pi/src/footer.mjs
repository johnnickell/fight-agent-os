/** Normalize extension status text without allowing embedded line breaks */
const singleLine = text => text.replace(/[\r\n\t]/g, ' ').replace(/ +/g, ' ').trim();

/** Keep Codex usage immediately above the combined screening and Jev row */
export function footerStatusRows(statuses) {
  const fight = ['fight-file-screen', 'fight-jev'];
  const others = [...statuses].filter(([key]) => key !== 'codex-weekly-usage' && !fight.includes(key))
    .sort(([a], [b]) => a.localeCompare(b)).map(([, text]) => singleLine(text));
  return [others.join(' '), singleLine(statuses.get('codex-weekly-usage') ?? ''),
    fight.map(key => singleLine(statuses.get(key) ?? '')).filter(Boolean).join(' · ')].filter(Boolean);
}

/** Include assistant, nested-tool, standalone and summary usage without double counting */
export function collectFooterUsage(entries) {
  const totals = { input: 0, output: 0, cacheRead: 0, cacheWrite: 0, cost: 0, cacheHitRate: undefined };
  for (const entry of entries) {
    let usage;
    if (entry.type === 'message' && ['assistant', 'toolResult'].includes(entry.message.role)) {
      usage = entry.message.usage;
      if (usage && entry.message.role === 'assistant') {
        const prompt = usage.input + usage.cacheRead + usage.cacheWrite;
        totals.cacheHitRate = prompt > 0 ? usage.cacheRead / prompt * 100 : undefined;
      }
    } else if (['usage', 'compaction', 'branch_summary'].includes(entry.type)) usage = entry.usage;
    if (!usage) continue;
    for (const key of ['input', 'output', 'cacheRead', 'cacheWrite']) totals[key] += usage[key];
    totals.cost += usage.cost.total;
  }
  return totals;
}

/** Match native Pi token units for the compact statistics row */
export function footerTokens(count) {
  if (count < 1000) return String(count);
  if (count < 10000) return `${(count / 1000).toFixed(1)}k`;
  if (count < 1000000) return `${Math.round(count / 1000)}k`;
  if (count < 10000000) return `${(count / 1000000).toFixed(1)}M`;
  return `${Math.round(count / 1000000)}M`;
}
