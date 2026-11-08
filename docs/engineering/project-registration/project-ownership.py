"""Render the existing Project creation ownership slice; README.md owns source/arrow traces."""
from pathlib import Path

from diagrams import Cluster, Diagram, Edge, Node

REVISION = "2cab5746d4ba9567dee675d45860e1cf927ef2ff"
OUTPUT = Path(__file__).with_suffix("")

with Diagram(
    "Project creation | existing implementation\n"
    f"Source: {REVISION}\n"
    "Solid = static reference | dashed open = implements | dotted = runtime interaction\n"
    "Selected creation ownership only; reads, admission details and failure limits: README.md",
    filename=str(OUTPUT), outformat="png", show=False, direction="LR",
    graph_attr={
        "fontname": "DejaVu Sans", "fontsize": "16", "bgcolor": "white",
        "pad": "0.3", "nodesep": "0.5", "ranksep": "0.9", "dpi": "110",
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
        action = Node("RegisterProjectAction\nAdmission + ProjectId before dispatch", nodeid="action")
        persistence = Node("PostgresProjectRepository\nProject + source claims", nodeid="persistence")
        audit_writer = Node("PostgresProjectRegistrationAudit\nRequired audit on same connection", nodeid="audit_writer")

    with Cluster("Application | src/Application/Workspace/Project/", graph_attr={"bgcolor": "#eff8ee"}):
        handler = Node("RegisterProjectHandler\nState + required audit in transaction\nCommit BEFORE event dispatch", nodeid="handler")
        audit = Node("ProjectRegistrationAudit\n<<interface>>", nodeid="audit")

    with Cluster("Domain | src/Domain/Workspace/Project/", graph_attr={"bgcolor": "#fff7e6"}):
        command = Node("Command/RegisterProject\nProjectId + scope + actor + values", nodeid="command")
        repository = Node("ProjectRepository\n<<interface>>\nCollects Project roots", nodeid="repository")
        event = Node("Event/ProjectRegistered\nWorkspaceId + ProjectId", nodeid="event")

    with Cluster("Fight Common | supported public contracts", graph_attr={"bgcolor": "#f4eef9"}):
        transaction = Node("TransactionalUnitOfWork\n<<interface>>\nS5: commitTransactional()\nEncloses add + required audit", nodeid="transaction")
        dispatcher = Node("EventDispatcher\n<<interface>>\nIn-process, not durable delivery", nodeid="dispatcher")

    action >> Edge(label="S1: constructs intent") >> command
    handler >> Edge(label="S2: reads intent") >> command
    handler >> Edge(label="S3: add(Project)") >> repository
    handler >> Edge(label="S4: record(Project)") >> audit
    handler >> Edge() >> transaction
    persistence >> Edge(label="S6: implements", style="dashed", arrowhead="empty") >> repository
    audit_writer >> Edge(label="S7: implements", style="dashed", arrowhead="empty") >> audit
    handler >> Edge(label="S8: constructs committed fact") >> event
    action >> Edge(label="R1: execute via configured\nCommandBus (indirect)", style="dotted", penwidth="2") >> handler
    handler >> Edge(label="R2: trigger(event)\nONLY after commit", style="dotted", penwidth="2") >> dispatcher
