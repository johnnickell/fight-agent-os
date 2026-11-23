"""Render the shared frontend ownership view; see README.md for the source revision."""
from pathlib import Path

from diagrams import Cluster, Diagram, Edge, Node

with Diagram(
    "Shared React frontend | implementation\n"
    "Solid arrow: static dependency | dotted arrow: runtime request\n"
    "Selected ownership only. Source revision and traces in README.md.",
    filename=str(Path(__file__).with_suffix("")),
    outformat="png",
    show=False,
    direction="LR",
    graph_attr={"fontname": "DejaVu Sans", "pad": "0.3", "dpi": "120"},
    node_attr={
        "fontname": "DejaVu Sans", "shape": "box", "style": "rounded,filled",
        "fillcolor": "white", "fixedsize": "false",
    },
    edge_attr={"fontname": "DejaVu Sans", "fontsize": "10"},
):
    with Cluster("Agent OS | client/"):
        host = Node("ShellApplication\nRoutes, brand and frame", nodeid="host")
        swagger = Node("SwaggerService\nPrivate document path and decoder", nodeid="swagger")
    with Cluster("Library | packages/react/"):
        controls = Node("controls.ts + styles.css\nShell, controls and style defaults", nodeid="controls")
        account = Node("account.ts\nAccount screens and session entry", nodeid="account")
        session = Node("AccountSession\nSessionCoordinator + AuthorityCache", nodeid="session")
        api = Node("ApiClient\nFixed same-origin transport", nodeid="api")
    with Cluster("Agent OS server"):
        server = Node("/api/v1\nAdmission and effects", nodeid="server")
    host >> Edge(label="S1: composes") >> controls
    host >> Edge(label="S2: mounts") >> account
    account >> Edge(label="S3: exports") >> session
    session >> Edge(label="S4: constructs") >> api
    swagger >> Edge(label="R1: protected JSON read", style="dotted") >> api
    api >> Edge(label="R2: browser fetch", style="dotted") >> server
