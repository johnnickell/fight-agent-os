"""Render the exact approved-root installation and application observation flow."""
from pathlib import Path

from diagrams import Diagram, Edge, Node

with Diagram(
    'Approved project roots | existing runtime flow\nDotted arrows: runtime actions or data',
    filename=str(Path(__file__).with_suffix('')),
    show=False,
    direction='LR',
    graph_attr={'fontname': 'DejaVu Sans', 'pad': '0.3', 'nodesep': '0.5', 'ranksep': '0.6'},
    node_attr={'fontname': 'DejaVu Sans', 'shape': 'box', 'fixedsize': 'false', 'labelloc': 'c',
               'width': '1.7', 'height': '0.6', 'margin': '0.15,0.1'},
    edge_attr={'fontname': 'DejaVu Sans', 'style': 'dotted'},
):
    operator = Node('Host operator', nodeid='operator')
    settings = Node('Private versioned\nroot settings', nodeid='settings')
    blocked = Node('No new root binds\nBind identity unavailable', nodeid='blocked')
    fpm = Node('Existing PHP-FPM\nApprovedRootAccess', nodeid='fpm')
    view = Node('Safe root locator\nand access facts', nodeid='view')
    operator >> Edge(label='1. Preview and apply') >> settings
    settings >> Edge(label='2. Refuse nonempty settings at startup') >> blocked
    fpm >> Edge(label='3. Observe existing mounts and permissions') >> view
