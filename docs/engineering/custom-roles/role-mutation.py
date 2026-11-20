"""Render the Role mutation ownership view; see README.md for source traces."""
from pathlib import Path

from diagrams import Cluster, Diagram, Edge, Node


def step(label):
    return Edge(label=label, style="dotted", color="#185b9d", penwidth="2")


with Diagram(
    "Custom Role mutations | existing implementation\n"
    "Dotted arrows show runtime calls, not dependency direction. Source: README.md",
    filename=str(Path(__file__).with_suffix("")),
    outformat="png",
    show=False,
    direction="TB",
    graph_attr={"fontname": "DejaVu Sans", "fontsize": "17", "pad": "0.35", "ranksep": "0.65"},
    node_attr={"shape": "box", "style": "rounded,filled", "fillcolor": "white",
               "fontname": "DejaVu Sans", "fontsize": "12", "fixedsize": "false"},
    edge_attr={"fontname": "DejaVu Sans", "fontsize": "10"},
):
    with Cluster("Agent OS | protected HTTP and admission", graph_attr={"bgcolor": "#eef4fa"}):
        actions = Node("CreateRoleAction / RenameRoleAction / RemoveRoleAction", nodeid="actions")
        admission = Node("InstallationPermissionAdmission", nodeid="admission")
    with Cluster("Fight Access Control | mutation and audit coordination", graph_attr={"bgcolor": "#eff8ee"}):
        handlers = Node("CreateCustomRoleHandler / RenameCustomRoleHandler\nRemoveCustomRoleHandler", nodeid="handlers")
        events = Node("CustomRoleCreated / CustomRoleRenamed\nCustomRoleRemoved", nodeid="events")
    with Cluster("Common DBAL + Agent OS persistence | one PostgreSQL connection",
                 graph_attr={"bgcolor": "#fff7e6"}):
        transaction = Node("DbalTransactionalUnitOfWork", nodeid="transaction")
        role = Node("PostgresRoleRepository", nodeid="role")
        audit = Node("PostgresAuditEvidenceRepository", nodeid="audit")
    actions >> step("1. Check protected installation scope") >> admission
    actions >> step("2. Dispatch through configured CommandBus") >> handlers
    handlers >> step("3. Run handler callback in transaction") >> transaction
    handlers >> step("4a. Write Role in callback") >> role
    handlers >> step("4b. Append audit in callback") >> audit
    handlers >> step("5. Dispatch success after commit") >> events
