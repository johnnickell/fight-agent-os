"""Render the confirmed user administration flow; revision and traces in USER_DIRECTORY.md."""
from pathlib import Path

from diagrams import Cluster, Diagram, Edge, Node


def flow(label):
    return Edge(label=label, style="dotted")


with Diagram(
    "Confirmed browser user administration | existing implementation\n"
    "Dotted arrows: runtime interactions | Source revision in USER_DIRECTORY.md",
    filename=str(Path(__file__).with_suffix("")),
    outformat="png",
    show=False,
    direction="TB",
    graph_attr={"fontname": "DejaVu Sans", "pad": "0.3", "dpi": "110"},
    node_attr={
        "fontname": "DejaVu Sans", "shape": "box", "style": "rounded,filled",
        "fillcolor": "white", "fixedsize": "false",
    },
    edge_attr={"fontname": "DejaVu Sans", "fontsize": "10"},
):
    with Cluster("Agent OS host"):
        route = Node("ApplicationView + GuardedRoute\nProtected user detail", nodeid="route")
    with Cluster("Shared React | mounted account session"):
        detail = Node("UserDetail + UserAdministration\nFresh target; explicit confirmation", nodeid="detail")
        service = Node("UserAdministrationService\nOne unresolved change per target", nodeid="service")
        owners = Node("AccountSession recovery owners\nDirectory, session and principal state", nodeid="owners")
    with Cluster("Server | existing HTTP entry"):
        action = Node("DisableUserAction (example)\nServer admission before dispatch", nodeid="action")

    route >> flow("A. Match target and check READ_USERS") >> detail
    detail >> flow("B. Submit confirmed change") >> service
    service >> flow("C. Send through ApiClient") >> action
    action >> flow("D. Settle with acknowledgement or failure") >> service
    service >> flow("E. Retire affected views; no replay") >> owners
    owners >> flow("F. Restore or recheck authority; refetch if allowed") >> detail
