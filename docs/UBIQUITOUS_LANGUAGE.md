# Ubiquitous language

Use these terms consistently in product planning, Domain contracts and user-facing explanations. A name describes
its concept; it does not by itself grant authority or establish implemented behavior.

## People, Agents and authority

| Term | Meaning |
|---|---|
| User | A human identity owned by Fight Access Control |
| Agent | A non-human identity owned by Fight Access Control, with its own authentication and directly assigned Permissions |
| Role | A named set of Permissions assigned to human Users through the existing Role assignment/removal mechanism |
| Permission | The right required for a particular operation; eligibility to receive it is separate from actual assignment |
| Repository access | The principal's access to one Repository, evaluated independently of the operation Permission |
| Team role | An Agent profile/template responsibility such as Software Engineer or QA Engineer; not a human authorization Role |

An authoritative administrator Role can also establish Repository scope under the accepted access policy. That
scope still does not supply an operation Permission. See the [Repository access contract](../planning/tasks/00498-TASK.md)
and the [eligibility-versus-assignment rule](engineering/MANAGED_POLICY.md#permission-eligibility-is-not-assignment).

## Human Roles

These names describe **human Roles**, not Agent types or Team roles:

| Role | Meaning |
|---|---|
| `ROLE_USER` | The baseline human User Role |
| `ROLE_SUPER_ADMIN` | Installation-wide human administrator; authoritative membership supplies access to all repositories |
| `ROLE_WORKSPACE_ADMIN` | Human administrator of the Workspace; authoritative membership supplies access to the initial single Workspace's repositories |
| `ROLE_REPOSITORY_ADMIN` | A human responsible for administering repositories they can access |
| `ROLE_DEVELOPER` | A human performing development work in repositories they can access |

`ROLE_REPOSITORY_ADMIN` and `ROLE_DEVELOPER` are approved additions to the vocabulary. They are not aliases for
Software Engineer, Team Lead or another Agent template. Their names do not add an all-Repository access path:
Repository access and the Permissions needed for each operation remain separate.

Naming a Role does not install its catalog definition, select its Permission memberships or assign it to a User.
Those changes use the existing Role/catalog owners and explicit assignment decisions; no new memberships or grants
follow from this terminology update. In particular, the word Admin does not imply every management Permission,
and Developer does not imply permission to start Agents, publish Git changes or bypass independent review.

`ROLE_WORKSPACE_ADMIN` uses the existing Role mechanism, not a separate administrative-membership store. Separate
Workspace assignment is deferred until multi-workspace functionality. The current single-Workspace model is not
blocked on that future feature.

## Agent responsibilities

[Agent team and workflow](engineering/TEAM.md) owns the Team-role vocabulary and responsibilities. Agents receive
actual Permissions directly; they do not become human Users or acquire human Roles by sharing a job title.
An Agent may perform an authorized Repository operation when its own Permissions and Repository access allow it.
