// Admission guards over Build's existing append-only session evidence. This is
// not a workflow scheduler: Build still inspects reports and chooses every stage.
export function stageHistory(entries, request) {
  const prior = entries.filter(entry => entry.type === 'custom' && entry.customType === 'fight-build-sdk-attempt-v1')
    .map(entry => entry.data);
  if (prior.at(-1)?.executionSettled !== true && prior.length) throw new Error('stage-execution-unreconciled');
  const same = prior.filter(entry => entry.task === request.task);
  const previous = same.at(-1);
  if (!Number.isSafeInteger(request.repairLimit) || request.repairLimit < 1 || request.repairLimit > 10 ||
      !Number.isSafeInteger(request.repairsUsed) || request.repairsUsed < 0 || request.repairsUsed > request.repairLimit ||
      typeof request.repair !== 'boolean' || request.repair && request.role !== 'engineer') throw new Error('stage-repair-accounting-invalid');
  if (previous && (previous.repairLimit !== request.repairLimit ||
      request.repairsUsed !== previous.repairsUsed + (request.repair ? 1 : 0))) throw new Error('stage-repair-accounting-changed');
  // A fresh coordinator carries the original ledger allowance, including prior
  // process stages. It never assumes a new session means zero consumed repairs.
  if (!previous && request.repair && request.repairsUsed < 1) throw new Error('stage-repair-accounting-invalid');
  const pin = same.find(entry => entry.role === request.role);
  if (pin && ['provider', 'model', 'thinking'].some(key => pin[key] !== request[key])) throw new Error('stage-model-pin-changed');
  if (same.some(entry => entry.checkout !== request.checkout || entry.base !== request.base)) throw new Error('stage-target-changed');
}
