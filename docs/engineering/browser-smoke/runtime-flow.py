"""Render the browser smoke request and fixture paths."""

from pathlib import Path

from diagrams import Cluster, Diagram, Edge, Node

REVISION = "d1d6bfad2e54dd37f88e63f586535ae2be613489"


def flow(label):
    return Edge(label=label, style="dotted", color="#334155", fontname="DejaVu Sans")


with Diagram(
    "HTTPS browser smoke — existing implementation\n"
    f"{REVISION}\nDotted arrow: runtime request or fixture access",
    filename=str(Path(__file__).with_suffix("")),
    outformat="png",
    show=False,
    direction="LR",
    graph_attr={"fontname": "DejaVu Sans", "fontsize": "15", "pad": "0.35", "splines": "polyline"},
    node_attr={"fontname": "DejaVu Sans", "shape": "box", "fixedsize": "false", "height": "0.6"},
):
    with Cluster("GitHub runner — native processes, no running containers"):
        native_browser = Node("Chromium\nprivate CA trust", nodeid="native_browser")
        native_gateway = Node("Native Nginx\nchecked static assets", nodeid="native_gateway")
        native_app = Node("Loopback PHP-FPM\npublic/index.php", nodeid="native_app")
        native_db = Node("Guarded test PostgreSQL", nodeid="native_db")
        native_mail = Node("Loopback Mailpit", nodeid="native_mail")
        native_browser >> flow("N1 HTTPS :18443") >> native_gateway
        native_gateway >> flow("N2 FastCGI :19087") >> native_app
        native_app >> flow("N3 test data") >> native_db
        native_app >> flow("N4 SMTP") >> native_mail

    with Cluster("Local Docker worktree route"):
        local_browser = Node("Chromium\nprivate CA trust", nodeid="local_browser")
        shared_edge = Node("External front door\nand shared ingress\n(not task-owned)", nodeid="shared_edge")
        local_gateway = Node("Task Nginx\nchecked static assets", nodeid="local_gateway")
        local_app = Node("Task PHP-FPM\npublic/index.php", nodeid="local_app")
        local_db = Node("Guarded test PostgreSQL", nodeid="local_db")
        local_mail = Node("Task Mailpit", nodeid="local_mail")
        local_browser >> flow("L1a HTTPS") >> shared_edge
        shared_edge >> flow("L1b verified HTTPS") >> local_gateway
        local_gateway >> flow("L2 FastCGI web:19087") >> local_app
        local_app >> flow("L3 test data") >> local_db
        local_app >> flow("L4 SMTP") >> local_mail
