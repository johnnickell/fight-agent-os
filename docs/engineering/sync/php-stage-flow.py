"""Render the current Sync process-stage ownership and startup flow"""
from pathlib import Path

from diagrams import Diagram, Edge, Node

with Diagram(
    'Sync PHP stage — existing runtime flow',
    filename=str(Path(__file__).with_suffix('')), outformat='png', show=False, direction='TB',
    graph_attr={'fontname': 'DejaVu Sans', 'fontsize': '16', 'pad': '0.25', 'nodesep': '0.45',
                'ranksep': '0.72', 'bgcolor': 'white'},
    node_attr={'fontname': 'DejaVu Sans', 'fontsize': '12', 'shape': 'box', 'width': '2.4',
               'style': 'rounded', 'fixedsize': 'false', 'margin': '0.15', 'labelloc': 'c', 'height': '0.7'},
    edge_attr={'fontname': 'DejaVu Sans', 'fontsize': '10'},
):
    provider = Node('Sync provider launcher\nrun-stage.php', nodeid='provider')
    monitor = Node('Selected Harness monitor\nobservation/run-stage.php\nOwns shutdown and private evidence', nodeid='monitor')
    child = Node('Owned child\nPHP bootstrap → native Pi', nodeid='child')
    discovery = Node('Passive PHP helper\nstage-discovery.php', nodeid='discovery')
    custodian = Node('Existing native custodian\nAdmits or refuses observation', nodeid='custodian')
    provider >> Edge(style='dotted', label='1. Select one package and exec its monitor') >> monitor
    monitor >> Edge(style='dotted', dir='both', arrowtail='normal',
                    label='2–3. Monitor → child: verify group, then allow Pi exec\n'
                          'Cancel before release: close pipe, no Pi exec\n'
                          'Cancel after release: signal only verified group\n'
                          '4. Child → monitor: Pi events and stderr') >> child
    monitor >> Edge(style='dotted', label='Best-effort progress; no execution control') >> discovery
    discovery >> Edge(style='dotted', label='Bounded progress after admission') >> custodian
    Node('Dotted arrows: runtime actions or data\nSource trace; not live execution proof',
         nodeid='legend', shape='note', fontsize='10')
