"""Render the exact approved-root installation and application observation flow."""
from pathlib import Path

from diagrams import Diagram, Edge, Node

with Diagram(
    'Approved project roots | runtime flow\nDotted arrows: runtime actions or data',
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
    startup = Node('Explicit startup\nRevalidate path and identity', nodeid='startup')
    docker = Node('Compose / Docker\nFPM-only root binds', nodeid='docker')
    fpm = Node('PHP-FPM\nApprovedRootAccess', nodeid='fpm')
    view = Node('Safe root locator\nand access facts', nodeid='view')
    operator >> Edge(label='1. Preview and apply') >> settings
    settings >> Edge(label='2. Read settings') >> startup
    startup >> Edge(label='3. Stream admitted model') >> docker
    docker >> Edge(label='4. Publish snapshot after mount check') >> fpm
    fpm >> Edge(label='5. Observe permissions') >> view
