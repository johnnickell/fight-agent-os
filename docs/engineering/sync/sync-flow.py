"""Render the source-traced local Sync instruction/dispatch flow"""
from pathlib import Path

from diagrams import Diagram, Edge, Node

SOURCE = 'bc257339c132f1b5ee9735048eb82fe44dffe244'
OUTPUT = Path(__file__).with_suffix('')

with Diagram(
    'Local Sync — existing instruction/admission flow\nSource: ' + SOURCE,
    filename=str(OUTPUT), outformat='png', show=False, direction='TB',
    graph_attr={'fontname': 'DejaVu Sans', 'fontsize': '15', 'pad': '0.3', 'nodesep': '0.5',
                'ranksep': '0.75', 'labeljust': 'l', 'bgcolor': 'white'},
    node_attr={'fontname': 'DejaVu Sans', 'fontsize': '12', 'shape': 'box', 'width': '2.7',
               'height': '1.0', 'style': 'rounded', 'fixedsize': 'false', 'labelloc': 'c', 'margin': '0.2'},
    edge_attr={'fontname': 'DejaVu Sans', 'fontsize': '10'},
):
    operator = Node('Operator\nexplicit /skill:sync TASK-ID\nworkflow/access.mjs + workflow-extension.ts', nodeid='operator')
    coordinator = Node('Sync coordinator\nsync/SKILL.md\nno source edits or acceptance', nodeid='sync')
    engineer = Node('Fresh Engineer / Work\nsync/references/integration.md\nmerge → commit → normal gate', nodeid='engineer')
    land = Node('Fresh Land\nland/SKILL.md + LANDING.md\nsame open PR, non-force push', nodeid='land')
    human = Node('Human review / merge\nactual verified PR URL\npublication ≠ merge', nodeid='human')
    legend = Node('Legend: dotted arrows = specified ordered interaction\n3 and 5 use the SAME existing run-stage.py dispatcher\nPrior native journey observed; see owning TASK evidence\nReconciled owner: direct checks, not another live journey\nSource trace is not runtime or acceptance proof',
                  nodeid='legend', shape='note', width='4.5', fontsize='11')
    operator >> Edge(label='1. interactive admission\n2. accepted TASK + existing PR intake', style='dotted') >> coordinator
    coordinator >> Edge(label='3. fresh Work\nshared dispatcher', style='dotted') >> engineer
    engineer >> Edge(label='4. verified handoff to SAME Sync coordinator\n5. Sync delegates fresh Land via SAME dispatcher\n(indirect hop; blocker stops progression)', style='dotted') >> land
    land >> Edge(label='6. verify PR + eligible cleanup\nor finite partial/held action', style='dotted') >> human
