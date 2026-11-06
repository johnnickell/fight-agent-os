"""Render the bounded, source-verified thrown API failure flow without executing the application."""

from pathlib import Path

from diagrams import Diagram, Edge, Node

SOURCE_REVISION = "1a35aff126aa0f848648c43cc5d43471f8452da5"
OUTPUT = Path(__file__).with_suffix("")

with Diagram(
    "Safe API failure flow — EXISTING / 1a35aff1\n"
    "Legend: dotted numbered arrow = runtime interaction; indirect/compressed hops are labeled",
    filename=str(OUTPUT),
    outformat="png",
    show=False,
    direction="TB",
    graph_attr={"fontname": "DejaVu Sans", "fontsize": "20", "pad": "0.4", "ranksep": "0.7"},
    node_attr={"fontname": "DejaVu Sans", "fontsize": "18", "fixedsize": "false", "width": "4.3", "height": "0.9"},
    edge_attr={"fontname": "DejaVu Sans", "fontsize": "15", "style": "dotted"},
):
    entry = Node("API request\n/api or /api/...", nodeid="entry")
    scope = Node("[Agent OS] ApiResponseBoundary\nprocess: scope + correlation", nodeid="scope")
    safe = Node("[Common] SafeJSendErrorMiddleware\nprocess: generic catch / fallback", nodeid="safe")
    downstream = Node(
        "[Agent OS / Slim] Downstream pipeline\nNativeApiFailures → routing → security\n→ validation → Action (indirect)",
        nodeid="downstream",
    )
    collaborators = Node(
        "Common-invoked collaborators (compressed)\n"
        "5 [Agent OS] RedactedHttpDiagnostics.report\n"
        "6a [Common] SafeHttpErrorPolicy.status\n"
        "6b [Agent OS] PublicFailureProjector.project\n"
        "6c [Common] SafeHttpErrorPolicy.present\n"
        "7 [Common] JSendResponseFactory.fromEnvelope",
        nodeid="collaborators",
    )
    returned = Node("[Agent OS] ApiResponseBoundary (return)\nno-store + validated correlation", nodeid="returned")
    client = Node("[Client] ApiClient → decodeResponse\nstrict typed outcomes; no replay", nodeid="client")

    entry >> Edge(label="1 request") >> scope
    scope >> Edge(label="2 delegate") >> safe
    safe >> Edge(label="3 invoke downstream") >> downstream
    downstream >> Edge(label="4 Throwable via native translation\n(indirect return)") >> safe
    safe >> Edge(label="5–7 handle caught failure\nordered calls shown in box") >> collaborators
    collaborators >> Edge(label="8 response via Common (indirect)") >> returned
    returned >> Edge(label="9 response") >> client
