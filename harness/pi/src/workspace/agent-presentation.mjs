import { basename } from 'node:path';
import { clean } from './presentation.mjs';

/** Present only the existing admitted, bounded activity window */
export function agentStatus(children, item) {
  if (!item.verified) return { mark: '○', tone: 'warning', label: 'CUSTODY LOST' };
  if (item.lifecycle === 'completed') return { mark: '✓', tone: 'success', label: 'completed' };
  if (item.lifecycle === 'failed') return { mark: '×', tone: 'error', label: 'failed' };
  if (children.active(item)) return { mark: '●', tone: 'accent', label: item.lifecycle };
  return { mark: '○', tone: 'warning', label: 'disconnected · last known ' + item.lifecycle };
}
export function agentRow(children, key, item) {
  const status = agentStatus(children, item);
  return `${key === children.selected ? '›' : ' '} ${status.mark} ${item.identity.role} · ${item.identity.assignment ?? `${item.identity.task} · ${basename(item.identity.attempt)}`}${key === children.pin ? ' [PIN]' : ''} · ${status.label}`;
}
export function agentDetail(children, key = children.selected) {
  const item = children.sources.get(key);
  if (!item) return ['Unavailable — selected attempt lost or evicted; no replacement output shown'];
  const status = agentStatus(children, item), identity = item.identity;
  const paused = children.paused && key === children.selected;
  return [`${status.mark} ${status.label}${key === children.pin ? ' · PINNED' : ''}${paused ? ' · PAUSED' : ''} · ${identity.role} · ${identity.assignment ?? identity.task}`,
    `Task ${identity.task}`,
    ...(identity.assignment ? [`Assignment ${identity.assignment}`] : []),
    `${identity.provider}/${identity.model} · ${identity.thinking}`,
    `Attempt ${identity.attempt}`, `Worktree ${identity.worktree}`,
    `Child ${item.id} · generation ${item.generation}`,
    item.source === 'pi-subagents' ? `SDK ${item.upstreamStatus} · not stage acceptance` : `Last known ${item.lifecycle} · exit ${item.exitCode ?? 'unknown'} · not acceptance`,
    `Bounded activity · omitted ${item.omitted} · pipe gaps ${item.gaps ?? 'unknown'}${paused ? ` · PAUSED, ${Math.max(0, item.sequence - children.pausedSequence)} subsequent records drained` : ''}`,
    ...(!item.verified ? ['CUSTODY LOST — retained last-known output; no log authority'] : []),
    ...(paused ? children.frozen ?? [] : item.history)];
}
export function agentList(children) {
  return children.orderedSources.flatMap(([key, item]) => [agentRow(children, key, item),
    `  └ ${clean(item.history.at(-1)?.replace(/\s+/g, ' ') ?? 'Waiting for agent output')}`]);
}
