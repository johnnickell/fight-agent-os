"""Render the source-verified HTTP security envelope; see README.md for traces."""
from pathlib import Path

from diagrams import Cluster, Diagram, Edge, Node

REVISION = "6046ee3352438ddf8bdc32debe18b662a8e9d591"
with Diagram(
    "HTTP security envelope | existing implementation\n"
    f"Source: {REVISION}\n"
    "Dotted arrows: ordered request runtime flow; responses unwind the same pipeline\n"
    "Source trace, not deployment or acceptance evidence. See README.md",
    filename=str(Path(__file__).with_suffix("")), outformat="png", show=False, direction="TB",
    graph_attr={"fontname": "DejaVu Sans", "fontsize": "15", "bgcolor": "white", "dpi": "120"},
    node_attr={"shape": "box", "style": "rounded,filled", "fillcolor": "white", "fontname": "DejaVu Sans",
               "fontsize": "12", "fontcolor": "#17212b", "width": "4.0", "fixedsize": "false"},
    edge_attr={"fontname": "DejaVu Sans", "fontsize": "11", "style": "dotted", "color": "#185b9d"},
):
    with Cluster("HTTP startup | bootstrap/app.php", graph_attr={"bgcolor": "#fff7e6"}):
        startup = Node("Resolve security configuration + typed policies\nExisting keyring/CSRF/debug/password checks\nProduction provider still unavailable", nodeid="startup")
    with Cluster("Adapter HTTP pipeline | config/middleware.php", graph_attr={"bgcolor": "#eef4fa"}):
        headers = Node("SecurityHeaders\nWrap every response; strip CORS grants", nodeid="headers")
        transport = Node("CanonicalTransport\nProduction direct HTTPS + canonical Host\nInvalid authority: 421, no dispatch", nodeid="transport")
        fallback = Node("Slim fallback + ApiResponseBoundary\nOne Common safe API error/reporting owner", nodeid="errors")
        origin = Node("NativeApiFailures -> SameOriginApi\nForeign Origin / preflight: safe 403", nodeid="origin")
        routing = Node("Slim routing -> existing route controls\nAuthentication / permission / input / CSRF / admission", nodeid="routing")
        action = Node("Matched Action -> existing use case -> Responder\nIndependent endpoint authority remains required", nodeid="action")
    startup >> Edge(label="R1: validate before registration") >> headers
    headers >> Edge(label="R2: delegate") >> transport
    transport >> Edge(label="R3: admitted requests") >> fallback
    fallback >> Edge(label="R4: contained API scope") >> origin
    origin >> Edge(label="R5: same-origin or independently authenticated read") >> routing
    routing >> Edge(label="R6: admitted operation") >> action
