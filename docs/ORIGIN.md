# Scaffold Origin

- Starter: https://github.com/johnnickell/project-slim at `c44192f3ad3920b3b0383289f71815cb8b028093`
- Planning tooling/templates and FIGHT identity: https://github.com/johnnickell/fight-common at `2b1f92a6b0d62cb2af0665b43aa1db487a6b19e7`
- Source MIT notices are retained in [LICENSE](../LICENSE) and [Fight Common's notice](licenses/fight-common-MIT.txt)

The starter's Git history, historical planning records, local dependencies, secrets, ignored run data, and
editor configuration were not copied. Source code, test fixtures, dependency locks, and framework-support
qualification machinery are inherited. Their receipts describe the upstream profile, not completed Agent OS features.

The initial adaptation changes project/package identity, the build image name, and the default development port
to `18087` (`FIGHT_AGENT_OS_PORT` can override it). The starter runtime behavior is otherwise unchanged.

Agent OS logo variants retain the FIGHT family mark and outlined wordmark, adding an AGENT OS descriptor.

Composer's own content-hash calculation refreshed both lockfiles after the package rename. Dependency
versions and source references remain identical to the starter. The lowest-lock digest and support receipt
were refreshed to match; the inherited full gate then passed. See the foundation brief for baseline warnings.
