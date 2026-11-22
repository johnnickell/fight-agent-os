"""Render the credential worker ownership view described in README.md."""
from pathlib import Path

from diagrams import Cluster, Diagram, Edge, Node

with Diagram(
    "Credential recovery | implementation\n"
    "Dotted arrow = runtime call",
    filename=str(Path(__file__).with_suffix("")),
    outformat="png", show=False, direction="TB",
    graph_attr={"fontname": "DejaVu Sans", "fontsize": "16", "pad": "0.3", "splines": "polyline"},
    node_attr={"shape": "box", "fixedsize": "false", "height": "0.5", "fontname": "DejaVu Sans", "fontsize": "11"},
    edge_attr={"fontname": "DejaVu Sans", "fontsize": "10"},
):
    with Cluster("Agent OS worker adapters"):
        entry = Node("CredentialDeliveryCommand\nAdmission and JSON/exit status", nodeid="entry")
        runners = Node("CredentialExpiryRunner and\nCredentialRecoveryRunner\nBounded dispatch and progress", nodeid="runners")
    with Cluster("Access Control package"):
        queries = Node("FindCredentialDeliveryStatus\nFindExpiredCredentialDeliveries\nFindDueCredentialDeliveries", nodeid="queries")
        commands = Node("Delivery and expiry handlers\nOwn transitions and transactions", nodeid="commands")
    provider = Node("Agent OS provider adapter\nCredentialMailProvider\nInvitation and reset routing", nodeid="provider")

    entry >> Edge(label="expire or run", style="dotted") >> runners
    entry >> Edge(label="status via QueryBus\n(indirect)", style="dotted") >> queries
    runners >> Edge(label="discover via QueryBus\n(indirect)", style="dotted") >> queries
    runners >> Edge(label="dispatch via command bus\n(indirect)", style="dotted") >> commands
    commands >> Edge(label="deliver only after claim commit", style="dotted") >> provider
