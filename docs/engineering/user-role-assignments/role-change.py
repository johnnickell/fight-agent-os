"""Render the existing User Role change flow; source traces are in README.md."""
from pathlib import Path

from diagrams import Cluster, Diagram, Edge, Node


def step(label):
    return Edge(label=label, style="dotted", color="#185b9d", penwidth="2")


with Diagram(
    "User Role changes | existing implementation\n"
    "Source: 5e6d38842f58c6c0562b73ca59916ec454f695d0\n"
    "Legend: dotted arrows = runtime interactions, not static dependencies",
    filename=str(Path(__file__).with_suffix("")), outformat="png", show=False,
    direction="TB",
    graph_attr={"fontname": "DejaVu Sans", "fontsize": "17", "pad": "0.35", "ranksep": "1.1"},
    node_attr={"shape": "box", "style": "rounded,filled", "fillcolor": "white",
               "fontname": "DejaVu Sans", "fontsize": "12", "fixedsize": "false", "labelloc": "c"},
    edge_attr={"fontname": "DejaVu Sans", "fontsize": "10"},
):
    with Cluster("Agent OS | HTTP admission"):
        gate = Node("ApiActionSecurity\nMANAGE_USER_ROLES", nodeid="gate")
        actions = Node("AssignUserRoleAction / RemoveUserRoleAction\n"
                       "Principal actor, authoritative User/Role queries\n"
                       "Reject self / inactive before dispatch\n"
                       "Super Admin: extra assignment permission + confirmation\n"
                       "Removal always refused", nodeid="actions")
        bus = Node("CommandBus: CommandPipeline\nRoutingCommandBus / ServiceAwareCommandRouter",
                   nodeid="bus")
    with Cluster("Fight Access Control v0.11.0 | package coordination"):
        handlers = Node("AssignRoleToUserHandler / RemoveRoleFromUserHandler", nodeid="handlers")
        events = Node("RoleAssignedToUser / RoleRemovedFromUser\nEventDispatcher (changed membership only)",
                      nodeid="events")
    with Cluster("Common DBAL + Agent OS | shared PostgreSQL Connection"):
        transaction = Node("DbalTransactionalUnitOfWork\ncommitTransactional(callback)", nodeid="transaction")
        writes = Node("PostgresUserRepository.replaceRoleAssignments\n"
                      "Predecessor / Role identity conflict checks\n"
                      "PostgresAuditEvidenceRepository.add\n"
                      "Both writes inside callback", nodeid="writes")
    gate >> step("1. Gate routed Action") >> actions
    actions >> step("2. execute package command if admitted") >> bus
    bus >> step("3. indirect registered handler dispatch") >> handlers
    handlers >> step("4. coordinate transaction") >> transaction
    transaction >> step("5. callback: membership + required audit") >> writes
    handlers >> step("6. trigger success only after commit returns") >> events
