"""Render the existing CSRF bootstrap ownership slice; see README.md for traces."""
from pathlib import Path

from diagrams import Cluster, Diagram, Edge, Node

REVISION = "b986243f613bf1542b1d275b9e34ed8c010aa20b"
OUTPUT = Path(__file__).with_suffix("")

with Diagram(
    "CSRF bootstrap | existing implementation\n"
    f"Source: {REVISION}\n"
    "Solid: static dependency | dashed: implements | dotted blue: runtime via bus\n"
    "Selected dependencies only; nonce, clock and admission omitted. Traces: README.md",
    filename=str(OUTPUT),
    outformat="png",
    show=False,
    direction="LR",
    graph_attr={
        "fontname": "DejaVu Sans", "fontsize": "16", "bgcolor": "white",
        "pad": "0.3", "nodesep": "0.45", "ranksep": "0.85",
        "splines": "spline", "dpi": "120",
    },
    node_attr={
        "shape": "box", "style": "rounded,filled", "fillcolor": "white",
        "fontname": "DejaVu Sans", "fontsize": "12", "fontcolor": "#17212b",
        "color": "#526474", "width": "2.5", "height": "0.7", "fixedsize": "false",
    },
    edge_attr={"fontname": "DejaVu Sans", "fontsize": "10", "color": "#526474"},
):
    with Cluster("Adapter | src/Adapter/", graph_attr={"bgcolor": "#eef4fa"}):
        action = Node("CsrfBootstrapAction\nHTTP entry", nodeid="action")
        responder = Node("CsrfBootstrapResponder\nHTTP presentation", nodeid="responder")
        hmac = Node("HmacCsrfProofs\nMAC implementation", nodeid="hmac")

    with Cluster("Application | src/Application/", graph_attr={"bgcolor": "#eff8ee"}):
        handler = Node("GetCsrfProofHandler\nQuery coordination", nodeid="handler")
        proofs = Node("CsrfProofs\n<<interface>>", nodeid="proofs")

    with Cluster("Domain | src/Domain/", graph_attr={"bgcolor": "#fff7e6"}):
        query = Node("GetCsrfProof\nQuery message", nodeid="query")
        view = Node("CsrfProofView\nQuery result", nodeid="view")
        proof = Node("CsrfProof\nValue", nodeid="proof")

    action >> Edge(label="S1: delegates") >> responder
    action >> Edge(label="S2: constructs") >> query
    responder >> Edge(label="S3: consumes") >> view
    handler >> Edge(label="S4: reads") >> query
    handler >> Edge(label="S5: constructs") >> view
    view >> Edge(label="S6: contains") >> proof
    handler >> Edge(label="S7: sign()") >> proofs
    hmac >> Edge(label="S8: implements", style="dashed", arrowhead="empty") >> proofs
    action >> Edge(
        label="R1: fetch via QueryBus*", style="dotted", color="#185b9d",
        fontcolor="#185b9d", penwidth="2",
    ) >> handler
