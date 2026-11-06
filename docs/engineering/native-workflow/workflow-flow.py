"""Render the bounded TASK-00586 native workflow supplier flow without network access"""
from pathlib import Path
from diagrams import Cluster, Diagram, Edge, Node

output = Path(__file__).with_suffix("")
with Diagram("Native workflow after Jev retirement\nExisting implementation · e4959587 (full revision in caption)",
             filename=str(output), show=False, direction="LR",
             graph_attr={"fontname": "DejaVu Sans", "fontsize": "18", "pad": "0.3"},
             node_attr={"fontname": "DejaVu Sans", "fontsize": "12", "shape": "box",
                        "fixedsize": "false", "width": "3.8", "height": "1.0", "labelloc": "c"},
             edge_attr={"fontname": "DejaVu Sans", "fontsize": "10"}):
    process = Node("Provider run-stage.py\nselected process monitor", nodeid="process")
    native = Node("Native Pi\nparent / process child", nodeid="native")
    with Cluster("Fight Harness · no evaluator supplier"):
        workflow = Node("workflow-extension.ts\nrestore / select responsibilities", nodeid="workflow")
        authority = Node("build/authority.mjs\nactual SessionManager binding", nodeid="authority")
        observe = Node("native-observation-extension.ts\npassive custody / child view", nodeid="observe")
    sdk = Node("Bundled SDK engine\nactual fresh Engineer session", nodeid="sdk")
    tools = Node("Pi native file / shell tools\nseparate opt-in shell policy", nodeid="tools")
    process >> Edge(label="1. launch same package\nrole + task + checkout", style="dotted") >> native
    native >> Edge(label="2. session_start / input", style="dotted") >> workflow
    workflow >> Edge(label="3. publish selection", style="dotted") >> authority
    sdk >> Edge(label="trusted host binds actual session\nnot a stage label", style="dotted") >> authority
    native >> Edge(label="native execution\nno workflow file gate", style="dotted") >> tools
    native >> Edge(label="lifecycle / tool notifications\nobservation only", style="dotted") >> observe
    Node("Legend: dotted arrow = runtime interaction\nSelection is not file permission or acceptance\nOmitted: transport, layout, full scheduler", nodeid="legend", width="4.8")
