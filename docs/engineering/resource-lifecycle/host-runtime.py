"""Render the existing linked-worktree host runtime boundary."""
from pathlib import Path

from diagrams import Cluster, Diagram, Edge, Node

with Diagram(
    'Linked-worktree runtime | Existing implementation\nDotted arrows: runtime calls or effects, not static dependencies',
    filename=str(Path(__file__).with_suffix('')),
    show=False,
    direction='TB',
    graph_attr={'fontname': 'DejaVu Sans', 'pad': '0.3', 'nodesep': '0.5', 'ranksep': '0.65', 'splines': 'polyline'},
    node_attr={'fontname': 'DejaVu Sans', 'shape': 'box', 'fixedsize': 'false', 'labelloc': 'c',
               'width': '1.5', 'height': '0.6', 'margin': '0.15,0.1'},
    edge_attr={'fontname': 'DejaVu Sans', 'style': 'dotted'},
):
    shell = Node('Shell entry points\nbin/env, bin/runtime, bin/up, bin/down', nodeid='shell')
    with Cluster('Repository-owned tooling on the host'):
        bootstrap = Node('scripts/runtime-php\nVerify and cache pinned PHP CLI', nodeid='bootstrap')
        settings = Node('HostSetting::run\nOne invoking-checkout setting', nodeid='settings')
        runtime = Node('RuntimeCommand / Installation\nSelect registered Docker project', nodeid='runtime')
        startup = Node('Startup::up\nRevalidate approved roots', nodeid='startup')
        teardown = Node('Teardown::down\nRefused model: record hold, use empty model', nodeid='teardown')
        removal = Node('WorktreeRemoval::remove\nQualify source, ref and effective hooks', nodeid='removal')
    docker = Node('Docker / Compose\nOnly the selected disposable project', nodeid='docker')
    worktrunk = Node('Worktrunk / Git\nGuarded non-force source removal', nodeid='worktrunk')

    shell >> Edge(label='1. Start host bootstrap') >> bootstrap
    bootstrap >> Edge(label='2a. Read PROJECT_BASE') >> settings
    bootstrap >> Edge(label='2b. Dispatch lifecycle') >> runtime
    runtime >> Edge(label='3a. Up') >> startup
    runtime >> Edge(label='3b. Down or finish (teardown first)') >> teardown
    startup >> Edge(label='4a. Apply model; check actual binds') >> docker
    teardown >> Edge(label='4b. Compose down; sweep native ownership') >> docker
    teardown >> Edge(label='5. Finish only if teardown succeeds') >> removal
    removal >> Edge(label='6. Remove after source checks') >> worktrunk
