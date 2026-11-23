from pathlib import Path

from diagrams import Diagram, Edge, Node

output = str(Path(__file__).with_suffix(''))

with Diagram(
    'Planning validation — existing implementation at 8c5a3c68',
    filename=output, outformat='png', show=False, direction='TB',
    graph_attr={
        'fontname': 'DejaVu Sans', 'fontsize': '20', 'ranksep': '0.6',
        'splines': 'polyline', 'pad': '0.2', 'labelloc': 't',
    },
    node_attr={
        'fontname': 'DejaVu Sans', 'fontsize': '14', 'shape': 'box',
        'fixedsize': 'false', 'labelloc': 'c', 'width': '0', 'height': '0', 'margin': '0.18,0.12',
    },
    edge_attr={'fontname': 'DejaVu Sans', 'fontsize': '12'},
):
    host = Node('Host: ./bin/planning-check\nNo host PHP or Composer', nodeid='host')
    container = Node('Prepared one-shot Docker image\nNo network or application services', nodeid='container')
    build = Node('Application build: scripts/build.php\nPHP in the build environment or on GitHub', nodeid='build')
    cli = Node('Shared entry point: scripts/planning_portfolio.php\nRules owned by scripts/PlanningPortfolio.php', nodeid='cli')
    planning = Node('Markdown records and generated views', nodeid='planning')
    legend = Node('Dotted arrow = runtime call or data access', nodeid='legend', shape='plaintext')

    host >> Edge(style='dotted', label='Runs the prepared image') >> container
    container >> Edge(style='dotted', label='Invokes PHP; forwards --write only when explicit') >> cli
    build >> Edge(style='dotted', label='Calls PHP directly, before checking reuse') >> cli
    cli >> Edge(style='dotted', label='Reads by default; refreshes only with --write') >> planning
    planning >> Edge(style='invis') >> legend
