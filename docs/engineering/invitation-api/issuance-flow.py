from pathlib import Path

from diagrams import Cluster, Diagram, Edge, Node


with Diagram(
    "HTTP invitation issuance — existing implementation",
    filename=str(Path(__file__).with_suffix("")),
    show=False,
    direction="TB",
    graph_attr={"fontname": "DejaVu Sans", "pad": "0.25"},
    node_attr={"fontname": "DejaVu Sans", "shape": "box", "fixedsize": "false", "height": "0.65"},
    edge_attr={"fontname": "DejaVu Sans"},
):
    with Cluster("Agent OS HTTP adapter"):
        action = Node("IssueInvitationAction\nAdmit request; generate User ID", nodeid="action")
    with Cluster("Agent OS Application"):
        handler = Node("IssueInvitationHandler\nOwns separate audit commit", nodeid="handler")
    with Cluster("Agent OS Domain"):
        evidence = Node("MembershipEvidenceRepository\nConfirmation audit contract", nodeid="audit")
    with Cluster("Access Control package"):
        origin = Node("InvitePendingUser\nOrigin commit; delivery may begin", nodeid="origin")
        assignment = Node("AssignRoleToUser\nSeparate assignment commit", nodeid="assignment")

    action >> Edge(label="1  IssueInvitation via CommandBus", style="dotted") >> handler
    handler >> Edge(label="2  Dispatch via CommandBus", style="dotted") >> origin
    handler >> Edge(label="3  Dispatch via CommandBus", style="dotted") >> assignment
    handler >> Edge(label="4  Commit after membership check", style="dotted") >> evidence
    Node("Dotted arrows: numbered runtime calls\nBus hops are indirect; no outer transaction", nodeid="legend")
