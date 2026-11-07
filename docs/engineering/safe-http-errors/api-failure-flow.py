"""Render the bounded, source-verified API failure and session recovery flow without executing the application."""

from pathlib import Path

from diagrams import Diagram, Edge, Node

SOURCE_REVISION = "997ba5c20e9faebb18c2f6be839d2dd2f09edfd1"
OUTPUT = Path(__file__).with_suffix("")

with Diagram(
    "Safe API failure / session recovery — EXISTING / 997ba5c2\n"
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
    safe = Node("[Common] SafeJSendErrorMiddleware\nprocess: generic catch / bounded fallback", nodeid="safe")
    guard = Node(
        "[Agent OS] ApiResponseBoundary\n"
        "Fresh anonymous handler per invocation\n"
        "Observe only SessionResponseFailure; rethrow\n"
        "No classification, reporting or shared state",
        nodeid="guard",
    )
    downstream = Node(
        "[Agent OS / Slim] Downstream pipeline\n"
        "NativeApiFailures → routing → security\n"
        "→ validation → Action/Responder (indirect)\n"
        "Session local exhaustion: SessionResponseFailure",
        nodeid="downstream",
    )
    collaborators = Node(
        "Common-invoked collaborators (compressed)\n"
        "7 [Agent OS] RedactedHttpDiagnostics.report\n"
        "8a [Common] SafeHttpErrorPolicy.status\n"
        "8b [Agent OS] PublicFailureProjector.project\n"
        "8c [Common] SafeHttpErrorPolicy.present\n"
        "9 [Common] JSendResponseFactory.fromEnvelope",
        nodeid="collaborators",
    )
    returned = Node(
        "[Agent OS] ApiResponseBoundary (return)\n"
        "11 If marked: BrowserSessionCookies.delete\n"
        "Add exact refresh + CSRF deletions\n"
        "Always: no-store + validated correlation",
        nodeid="returned",
    )
    client = Node("[Client] ApiClient → decodeResponse\nstrict typed outcomes; no replay", nodeid="client")

    entry >> Edge(label="1 request") >> scope
    scope >> Edge(label="2 delegate") >> safe
    safe >> Edge(label="3 invoke handler") >> guard
    guard >> Edge(label="4 invoke downstream") >> downstream
    downstream >> Edge(label="5 Throwable via native translation\n(indirect return)") >> guard
    guard >> Edge(label="6 rethrow unchanged; mark only\nsanitized session-deletion signal") >> safe
    safe >> Edge(label="7–9 handle caught failure\nordered calls shown in box") >> collaborators
    collaborators >> Edge(label="10 response via Common (indirect)") >> returned
    guard >> Edge(label="11 invocation-local flag read\nonly after Common returns") >> returned
    returned >> Edge(label="12 response") >> client
