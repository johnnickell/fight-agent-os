"""Render the existing bootstrap ownership slice; README.md owns source and arrow traces."""
from pathlib import Path

from diagrams import Cluster, Diagram, Edge, Node

REVISION = "973744f32649a14d28aebc79bf96cd6489d25190"
OUTPUT = Path(__file__).with_suffix("")

with Diagram(
    "Initial Workspace bootstrap | existing implementation\n"
    f"Source: {REVISION}\n"
    "Solid = static reference | dashed open = implements | dotted = indirect runtime dispatch\n"
    "Selected ownership only. Current reads, reference generation and failure branches: README.md",
    filename=str(OUTPUT), outformat="png", show=False, direction="LR",
    graph_attr={
        "fontname": "DejaVu Sans", "fontsize": "16", "bgcolor": "white",
        "pad": "0.3", "nodesep": "0.45", "ranksep": "0.8", "dpi": "110",
        "splines": "spline",
    },
    node_attr={
        "shape": "box", "style": "rounded,filled", "fillcolor": "white",
        "fontname": "DejaVu Sans", "fontsize": "12", "fontcolor": "#17212b",
        "color": "#526474", "width": "2.4", "height": "0.7", "fixedsize": "false",
    },
    edge_attr={"fontname": "DejaVu Sans", "fontsize": "10", "color": "#526474"},
):
    with Cluster("Adapter | src/Adapter/", graph_attr={"bgcolor": "#eef4fa"}):
        console = Node("BootstrapWorkspaceCommand\nTrusted operator admission + ID", nodeid="console")
        persistence = Node("PostgresWorkspaceRepository\nWorkspace collection", nodeid="persistence")
        audit_writer = Node("PostgresWorkspaceBootstrapAudit\nRequired audit only", nodeid="audit_writer")

    with Cluster("Application | src/Application/Workspace/Workspace/", graph_attr={"bgcolor": "#eff8ee"}):
        handler = Node("BootstrapWorkspaceHandler\nCoordinates state + required audit", nodeid="handler")
        audit = Node("WorkspaceBootstrapAudit\n<<interface>>", nodeid="audit")

    with Cluster("Domain | src/Domain/Workspace/Workspace/", graph_attr={"bgcolor": "#fff7e6"}):
        command = Node("Command/BootstrapWorkspace\nWorkspaceId + operatorId", nodeid="command")
        repository = Node("WorkspaceRepository\n<<interface>>\nCollects Workspace roots", nodeid="repository")

    with Cluster("Fight Common | supported public contracts", graph_attr={"bgcolor": "#f4eef9"}):
        transaction = Node("TransactionalUnitOfWork\n<<interface>>\nShared Doctrine transaction", nodeid="transaction")

    console >> Edge(label="S1: constructs") >> command
    handler >> Edge(label="S2: reads intent") >> command
    handler >> Edge(label="S3: add(root)") >> repository
    handler >> Edge(label="S4: record(root, operator)") >> audit
    handler >> Edge(label="S5: commitTransactional()") >> transaction
    persistence >> Edge(label="S6: implements", style="dashed", arrowhead="empty") >> repository
    audit_writer >> Edge(label="S7: implements", style="dashed", arrowhead="empty") >> audit
    console >> Edge(
        label="R1: execute via configured CommandBus*", style="dotted",
        color="#185b9d", fontcolor="#185b9d", penwidth="2",
    ) >> handler
