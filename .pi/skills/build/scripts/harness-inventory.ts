import {
  DefaultPackageManager, SettingsManager, ProjectTrustStore, getAgentDir, hasTrustRequiringProjectResources,
} from '@earendil-works/pi-coding-agent';

/** Inspect native resource paths without granting trust or executing project resources */
export default async function inspectHarnessInventory() {
  const cwd = process.cwd(), agentDir = getAgentDir();
  // Inspect even not-yet-trusted project configuration conservatively. This resolver only
  // discovers paths; ordinary native startup still owns trust and extension execution.
  const projectTrusted = new ProjectTrustStore(agentDir).get(cwd) ?? !hasTrustRequiringProjectResources(cwd);
  const settingsManager = SettingsManager.create(cwd, agentDir, { projectTrusted: true });
  if (settingsManager.drainErrors().length) throw Error('Native settings inventory unavailable');
  const manager = new DefaultPackageManager({ cwd, agentDir, settingsManager });
  const resources = await manager.resolve(async () => 'error');
  if (resources.installFailures.length) throw Error('Native resource inventory unavailable');
  console.log('FIGHT_HARNESS_INVENTORY=' + JSON.stringify({
    projectTrusted, packages: manager.listConfiguredPackages(), extensions: resources.extensions,
  }));
}
