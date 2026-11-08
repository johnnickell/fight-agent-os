"""Render the source-traced all-role SDK Build and retained Sync process boundary"""
from pathlib import Path

from diagrams import Cluster, Diagram, Edge, Node

SOURCE = '00c89f6339920da15505ed4d7a604faf91dc5125'
OUTPUT = Path(__file__).with_suffix('')

with Diagram(
    'Local Build — all-role SDK default / distinct Sync process path\nExisting implementation · ' + SOURCE,
    filename=str(OUTPUT), outformat='png', show=False, direction='TB',
    graph_attr={'fontname': 'DejaVu Sans', 'fontsize': '18', 'pad': '0.35',
                'nodesep': '0.55', 'ranksep': '0.8', 'bgcolor': 'white'},
    node_attr={'fontname': 'DejaVu Sans', 'fontsize': '13', 'shape': 'box', 'width': '3.4',
               'height': '1.0', 'style': 'rounded', 'fixedsize': 'false', 'labelloc': 'c', 'margin': '0.2'},
    edge_attr={'fontname': 'DejaVu Sans', 'fontsize': '11'},
):
    operator = Node('Operator / trusted native host\nworkflow-extension.ts + workflow/access.mjs\nactual session-local selection, not a prompt label', nodeid='operator')
    with Cluster('Build · one stage/evidence coordinator, no process fallback'):
        build = Node('Build coordinator · build/SKILL.md\nchooses each fresh role from actual evidence\noriginal pins / repair ledger / delivery scope', nodeid='build')
        tool = Node('fight_build_stage · runStage\nBuild/TASK/checkout/default mode; no SDK children\ncapability/profile/candidate/history guards', nodeid='tool')
        engine = Node('One bundled upstream SDK engine\nspawnTopLevel → agent-runner.ts\nfresh context, exact root, awaited setup hook', nodeid='engine')
        owner = Node('Actual fresh stage owner\nbindStageSession → workflow session_start/input\nWork | independent Review | independent QA | Land', nodeid='owner')
        reports = Node('Canonical independent Review / QA reports\nowning skills write at resolved canonical base\nreportObservation = bytes + identity, NOT verdict', nodeid='reports')
    with Cluster('Sync · distinct accepted-work protocol, not an SDK fallback'):
        sync = Node('Sync coordinator · sync/SKILL.md\ncurrent acceptance + same existing OPEN PR\nfresh Engineer then fresh authorized Land', nodeid='sync')
        process = Node('Retained provider run-stage.py\n→ package observation/run-stage.py\nnative package/trust/role checks; no screening', nodeid='process')
        sync_owner = Node('Fresh process Work / Land\nFIGHT_WORKFLOW_PHASE / TASK / CHECKOUT\ninitialized workflow; actual exit / handoff', nodeid='sync_owner')
    with Cluster('Shared passive native observation · never execution or acceptance'):
        observe = Node('Native custody + SubagentObserver / process adapter\nexact native root / stable record / registered checkout\nno foreign, rootless, nested or replaced admission', nodeid='observe')
        panes = Node('Existing Herdr AGENTS / LOGS + main viewer\nbounded activity and verified existing stack context\nclose/loss revokes readers, never replays a worker', nodeid='panes')
    operator >> Edge(label='1. initialized Build selection', style='dotted') >> build
    operator >> Edge(label='S1. separate Sync selection', style='dotted') >> sync
    build >> Edge(label='2. one fresh request / exact candidate', style='dotted') >> tool
    tool >> Edge(label='3. one spawn; no inherited conversation', style='dotted') >> engine
    engine >> Edge(label='4. bind actual role before initialization / input', style='dotted') >> owner
    owner >> Edge(label='5. Review / QA only: independent report', style='dotted') >> reports
    reports >> Edge(label='6. coordinator reads subject / owner / verdict / limits\nnegative or missing evidence HOLDS', style='dotted', constraint='false') >> build
    tool >> Edge(label='identity / lifecycle / settlement outcome\nnever stage acceptance', style='dotted', constraint='false') >> build
    sync >> Edge(label='S2. same retained dispatcher for both roles', style='dotted') >> process
    process >> Edge(label='S3. fresh process and trusted launcher inputs', style='dotted') >> sync_owner
    sync_owner >> Edge(label='handoff / exit → SAME Sync coordinator\nreconcile effects before next role', style='dotted', constraint='false') >> sync
    engine >> Edge(label='bounded live-session activity\nindirect via native observer bridge', style='dotted') >> observe
    process >> Edge(label='bounded process activity\nindirect via automatic observation / custodian', style='dotted') >> observe
    observe >> Edge(label='verified selection / lifetime only; human display\nno activity/log bodies to model', style='dotted') >> panes
    Node('Legend: dotted arrow = labeled runtime/data interaction (source trace, not runtime proof)\nNumbered Build / S-prefixed Sync steps are separate paths; return arrows carry evidence only\nNative tools are not OS confinement · missing capabilities hold · SDK completed ≠ ACCEPT/PASS\nOmitted: provider transport, detailed tools/storage, Herdr layout and actual delivery/cleanup effects',
         nodeid='legend', shape='note', width='7.0', fontsize='12')
