"""Render who owns the existing synchronous password mutations; see README.md."""
from pathlib import Path

from diagrams import Cluster, Diagram, Edge, Node

REVISION = "e5d3f772407986297a4023233a622902417a45e5"

with Diagram(
    "Password mutations | existing implementation\n"
    f"Source: {REVISION}\n"
    "Solid arrow: static dependency from caller to owner",
    filename=str(Path(__file__).with_suffix("")),
    outformat="png",
    show=False,
    direction="LR",
    graph_attr={
        "fontname": "DejaVu Sans", "fontsize": "16", "dpi": "120", "pad": "0.3",
        "splines": "spline", "ranksep": "1.0", "nodesep": "0.5",
    },
    node_attr={
        "fontname": "DejaVu Sans", "fontsize": "12", "shape": "box",
        "style": "rounded,filled", "fillcolor": "white", "color": "#526474",
        "width": "2.9", "height": "0.65", "fixedsize": "false",
    },
    edge_attr={"fontname": "DejaVu Sans", "fontsize": "10", "color": "#526474"},
):
    with Cluster("Agent OS | HTTP adapter", graph_attr={"bgcolor": "#eef4fa"}):
        actions = Node(
            "ActivateInvitationAction\nChangePasswordAction\nCompletePasswordResetAction",
            nodeid="actions",
        )
    with Cluster("Agent OS | Application", graph_attr={"bgcolor": "#eff8ee"}):
        policy = Node("PasswordSetting\nOwned password acceptance", nodeid="policy")
    with Cluster("Fight Access Control | Application", graph_attr={"bgcolor": "#fff7e6"}):
        auth = Node("UserAuthentication\nActivation and ordinary session", nodeid="auth")
        passwords = Node("PasswordManagement\nPassword and authority replacement", nodeid="passwords")

    actions >> Edge(label="S1: requireAccepted()  (all three)") >> policy
    actions >> Edge(label="S2: activate()  (invitation only)") >> auth
    actions >> Edge(label="S3: changePassword() / resetPassword()") >> passwords
