"""Render the local enrollment flow; source traces and limits are in README.md."""
from pathlib import Path
from diagrams import Cluster, Diagram, Edge, Node

REVISION = "2039880887829f7e7981df7098b17ddb6953ad34"
with Diagram(
    "Local project enrollment | existing implementation\n"
    f"Source: {REVISION}\n"
    "Dotted arrows: runtime operations; numbers order each selected path\n"
    "Source trace only; DNS/trust enrollment and HTTPS request routing omitted",
    filename=str(Path(__file__).with_suffix("")), show=False, outformat="png", direction="LR",
    graph_attr={"fontname": "DejaVu Sans", "fontsize": "16", "dpi": "110", "pad": "0.3", "ranksep": "0.7"},
    node_attr={"shape": "box", "style": "rounded,filled", "fillcolor": "white", "fontname": "DejaVu Sans", "fontsize": "12", "width": "2.5", "height": "0.8", "fixedsize": "false"},
    edge_attr={"style": "dotted", "fontname": "DejaVu Sans", "fontsize": "10"},
):
    with Cluster("Application checkout"):
        startup = Node("bin/up\nselected primary or worktree", nodeid="startup")
        client = Node("ingress_client.py\noptional local supplier discovery", nodeid="client")
        ordinary = Node("Ordinary startup\nwhen no provider", nodeid="ordinary")
    with Cluster("Canonical local Fight installation"):
        cli = Node("bin/local-domain\nensure / plan + apply", nodeid="cli")
        registry = Node("var/local-ingress/hosts.json\nproject zone + checkout binding", nodeid="registry")
        preview = Node("bin/ingress preview\nselected framework gateway", nodeid="preview")
    startup >> Edge(label="1. ensure") >> client
    client >> Edge(label="2a. absent provider") >> ordinary
    client >> Edge(label="2b. configured provider") >> cli
    cli >> Edge(label="3. validate native identity;\nwrite enrolled binding") >> registry
    client >> Edge(label="4. after enrolled app startup;\nrequire supplier + leaf") >> preview
