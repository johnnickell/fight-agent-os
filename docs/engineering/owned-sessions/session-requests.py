"""Render the self-service session request flow traced in README.md."""
from pathlib import Path

from diagrams import Cluster, Diagram, Edge, Node

REVISION = "3ffa136458adc762e3a83424372aa377d1b5c631"


def call(label):
    """Mark a runtime call, not a static dependency."""
    return Edge(label=label, style="dotted", color="#185b9d", fontcolor="#185b9d", penwidth="2")


with Diagram(
    "Self-service sessions | existing implementation\n"
    f"Source: {REVISION}\n"
    "Dotted arrows: runtime calls or bus hops | numbers order each branch",
    filename=str(Path(__file__).with_suffix("")), outformat="png", show=False, direction="LR",
    graph_attr={
        "fontname": "DejaVu Sans", "fontsize": "17", "bgcolor": "white",
        "pad": "0.3", "nodesep": "0.55", "ranksep": "0.7", "dpi": "120",
    },
    node_attr={
        "shape": "box", "style": "rounded,filled", "fillcolor": "white", "fontname": "DejaVu Sans",
        "fontsize": "12", "fontcolor": "#17212b", "color": "#526474", "fixedsize": "false",
        "labelloc": "c", "height": "0.7", "width": "2.0",
    },
    edge_attr={"fontname": "DejaVu Sans", "fontsize": "10"},
):
    with Cluster("Agent OS | HTTP and security adapters", graph_attr={"bgcolor": "#eef4fa"}):
        security = Node("ApiActionSecurity\nFresh principal and exact permission", nodeid="security")
        listing = Node("ListOwnedSessionsAction", nodeid="list-action")
        revoking = Node("RevokeOwnedSessionAction\nAdmits only an owned target", nodeid="revoke-action")

    with Cluster("Agent OS | Application", graph_attr={"bgcolor": "#eff8ee"}):
        projection = Node("ListOwnedSessionsHandler\nReturns SessionPage with optional device hints", nodeid="projection")

    with Cluster("Fight Access Control | package handlers", graph_attr={"bgcolor": "#fff7e6"}):
        active = Node("ListActiveSessionsHandler\nAuthoritative page and current marker", nodeid="active")
        revoke = Node("RevokeSessionHandler\nRevocation and audit commit", nodeid="revoke")

    security >> call("L1: READ_OWN_SESSIONS") >> listing
    security >> call("R1: DELETE_OWN_SESSIONS") >> revoking
    listing >> call("L2: via QueryBus") >> projection
    projection >> call("L3: via QueryBus") >> active
    revoking >> call("R2: via CommandBus") >> revoke
