import type { ExtensionAPI, ExtensionContext } from "@earendil-works/pi-coding-agent";
import type { AgentManager } from "../agent-manager.js";
import type { FleetWorkflow } from "../ui/fleet-list.js";
import { createWorkflowHost } from "./host.js";
import { appendJournal } from "./journal.js";
import { runWorkflow } from "./runtime.js";
import { completeWorkflowTask, failWorkflowTask, updateWorkflowProgressBatch, type WorkflowTask } from "./task.js";

/** Keep the live records for one extension activation. */
export function createWorkflowRuns(pi: ExtensionAPI, manager: AgentManager, refresh: () => void) {
  // Readers keep the live records, not snapshots, so cards and dialogs can
  // follow a background run without another tool call.
  const tasks = new Map<string, WorkflowTask>();

  function track(task: WorkflowTask): void {
    tasks.set(task.id, task);
    // Publish the run before its first child starts. Owned children do not
    // refresh these surfaces through the top-level agent lifecycle callbacks.
    refresh();
  }

  function fleetSummary(): FleetWorkflow[] {
    // Use cached counters: fleet reads the roster several times per tick.
    return [...tasks.values()].map(task => ({
      id: task.id,
      name: task.meta?.name ?? task.workflowName ?? task.id,
      status: task.status,
      doneCount: task.doneCount,
      totalCount: task.agentCount,
      startedAt: task.startTime,
      ...(task.endTime !== undefined ? { completedAt: task.endTime } : {}),
      tokens: task.totalTokens,
    }));
  }

  /** Settle the live record even when the run cannot start. */
  async function run(ctx: ExtensionContext, task: WorkflowTask): Promise<void> {
    try {
      const { journalPath } = task;
      const signal = task.abortController.signal;
      const result = await runWorkflow({
        script: task.script,
        args: task.args,
        signal,
        host: createWorkflowHost({
          pi,
          ctx,
          manager,
          signal,
          rootSessionId: ctx.sessionManager.getSessionId(),
          workflowId: task.id,
        }),
        onProgress: entries => updateWorkflowProgressBatch(task, entries),
        // Dialog controls are dropped again when the task settles.
        onControl: control => { task.control = control; },
        journal: {
          ...(task.replay !== undefined ? { entries: task.replay } : {}),
          ...(journalPath !== undefined ? { append: entry => appendJournal(journalPath, entry) } : {}),
        },
      });
      completeWorkflowTask(task, result);
    } catch (err) {
      failWorkflowTask(task, err instanceof Error ? err.message : String(err));
    }
  }

  function abortAll(): void {
    for (const task of tasks.values()) task.abortController.abort();
    tasks.clear();
  }

  return { tasks: tasks as ReadonlyMap<string, WorkflowTask>, track, fleetSummary, run, abortAll };
}
