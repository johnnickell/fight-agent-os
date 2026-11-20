"""Render the trusted Console command flow described in README.md."""
from pathlib import Path

from diagrams import Cluster, Diagram, Edge, Node

with Diagram(
    "Trusted Console invitations and assignment | implementation\n"
    "Numbered arrows show runtime calls; the dotted arrow marks inspection.",
    filename=str(Path(__file__).with_suffix("")),
    outformat="png", show=False, direction="TB",
    graph_attr={"fontname": "DejaVu Sans", "fontsize": "16", "pad": "0.3", "splines": "spline"},
    node_attr={"shape": "box", "fixedsize": "false", "height": "0.5", "fontname": "DejaVu Sans", "fontsize": "11"},
    edge_attr={"fontname": "DejaVu Sans", "fontsize": "10"},
):
    with Cluster("Console adapter"):
        entry = Node("InviteUserCommand / AddUserRoleCommand\nBootstrapSuperAdminCommand", nodeid="entry")
    with Cluster("Application coordination"):
        use_case = Node("InviteOrdinaryUserHandler / AddUserRoleHandler\nBootstrapSuperAdminHandler", nodeid="handler")
    with Cluster("Access Control package"):
        package = Node("ReconcileManagedPolicy (bootstrap only)\nInvitePendingUser (creation only)\nAssignRoleToUser", nodeid="package")
    with Cluster("Agent OS PostgreSQL adapters"):
        audit = Node("PostgresAuditEvidenceRepository\nSeparate required evidence commit", nodeid="audit")
        read = Node("PostgresMembershipProgress / PostgresBootstrapProgress\nRead-only committed-state inspection", nodeid="read")

    entry >> Edge(label="1. Typed command via configured CommandBus") >> use_case
    use_case >> Edge(label="2. Nested commands via CommandBus\nIndependent package commits") >> package
    use_case >> Edge(label="3. Handler-owned evidence transaction") >> audit
    use_case >> Edge(label="After failure, or before\nbootstrap step 3", style="dotted") >> read
