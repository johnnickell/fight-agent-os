"""Render the Users table flow; see USER_DIRECTORY.md for source revision and traces."""
from pathlib import Path

from diagrams import Cluster, Diagram, Edge, Node


def flow(label):
    return Edge(label=label, style="dotted")


with Diagram(
    "Users directory table | existing implementation\n"
    "Dotted arrows: runtime messages or reads | Source revision in USER_DIRECTORY.md",
    filename=str(Path(__file__).with_suffix("")),
    outformat="png",
    show=False,
    direction="TB",
    graph_attr={"fontname": "DejaVu Sans", "pad": "0.3", "dpi": "110"},
    node_attr={
        "fontname": "DejaVu Sans", "shape": "box", "style": "rounded,filled",
        "fillcolor": "white", "fixedsize": "false",
    },
    edge_attr={"fontname": "DejaVu Sans", "fontsize": "10"},
):
    with Cluster("Browser | shared React package"):
        table = Node("DataTable\nPaging, size and sort controls", nodeid="table")
        page = Node("UsersPage\nOne authority-scoped read", nodeid="page")
        location = Node("useTableLocation\nNormalized query and history", nodeid="location")
    with Cluster("Agent OS | HTTP and PostgreSQL"):
        action = Node("ListUsersAction\nAfter the READ_USERS gate", nodeid="action")
        listing = Node("PostgresUserRepository\nCounts, orders, then slices", nodeid="listing")

    table >> flow("Commit one normalized change") >> page
    page >> flow("Restore controls without a callback") >> table
    page >> flow("Write a change or replace a corrected page") >> location
    location >> flow("Seed from URL / restore on Back or Forward") >> page
    page >> flow("GET /api/v1/users via UserDirectoryService") >> action
    action >> flow("Indirect: QueryBus + directory and package handlers") >> listing
    action >> flow("Safe page and total; discard if stale") >> page
