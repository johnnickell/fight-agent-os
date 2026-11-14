from pathlib import Path
from diagrams import Cluster, Diagram, Edge, Node

output = str(Path(__file__).with_suffix(''))
with Diagram('PROPOSED TASK-00072 Planning runtime\nBaseline: 0bd021867406f29a69077efa3916e17c7a86a32b',
             filename=output, outformat='png', show=False, direction='LR',
             graph_attr={'fontsize': '18', 'fontname': 'DejaVu Sans'},
             node_attr={'fontname': 'DejaVu Sans', 'fontsize': '12', 'shape': 'box',
                        'labelloc': 'c', 'fixedsize': 'false', 'width': '2.8', 'height': '1.1'},
             edge_attr={'fontname': 'DejaVu Sans', 'fontsize': '11'}):
    browser = Node('Browser\ncanonical HTTPS')
    with Cluster('Separately operated, existing shared supplier'):
        ingress = Node('Public Nginx\nverified TLS to gateway')
        discovery = Node('docker-gen\nsole read-only socket mount\n(no network)')
    with Cluster('One installation / selected Compose project'):
        gateway = Node('https\napplication Nginx\nonly ingress-network peer')
        with Cluster('Private project network, no published service ports'):
            fpm = Node('https_php\nPHP-FPM\napplication secret only')
            postgres = Node('postgres\ndurable named volume\nexplicit migrations')
            storage = Node('seaweedfs\nprivate signed S3\ndurable named volume')
            mail = Node('mailcatcher\ndisposable tmpfs')
            tools = Node('web\ntrusted native tooling\nno HTTP listener')
            testdb = Node('postgres_test\nguarded disposable tmpfs')
    browser >> Edge(style='dotted', label='HTTPS / 443') >> ingress
    ingress >> Edge(style='dotted', label='verified HTTPS / 8443') >> gateway
    discovery >> Edge(style='dotted', label='discover gateway, reload routes') >> ingress
    gateway >> Edge(style='dotted', label='canonical FastCGI') >> fpm
    fpm >> Edge(style='dotted', label='shared DB authority') >> postgres
    fpm >> Edge(style='dotted', label='local catcher adapter') >> mail
    tools >> Edge(style='dotted', label='explicit migrations') >> postgres
    tools >> Edge(style='dotted', label='guarded tests') >> testdb
    tools >> Edge(style='dotted', label='operator qualification only') >> storage
    Node('Legend: dotted arrow = runtime interaction\nStorage application consumer not implemented\nNo Runner / Workflow / Artifact capability\nNo production or recovery certification')
