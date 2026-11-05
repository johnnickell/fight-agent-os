"""Prepare the documented isolated setup and a synthetic, pending activation scenario."""
import html
import json
import re
import subprocess
import uuid

from resource_lifecycle.preparation import compose, environment
from resource_lifecycle.resources import require, run, write_private


# These are the target's host-executed setup boundary. Application PHP/JS still
# executes in trusted local containers, not a hostile-code sandbox. A changed
# boundary needs deliberate qualification, never implicit approval from --ref.
SETUP_PATHS = (
    'compose.yaml', 'bin/composer', 'bin/client', 'bin/runtime', 'bin/database',
    'bin/console', 'bin/validations', 'scripts/runtime.py', 'scripts/resource_lifecycle/cli.py',
    'scripts/resource_lifecycle/runtime.py', 'scripts/resource_lifecycle/resources.py',
    'scripts/resource_lifecycle/preparation.py', 'etc/docker',
)


def supported(root, oid):
    from resource_lifecycle.resources import text
    # Fixed setup commands import the modules below from scripts/ and its
    # resource_lifecycle package. Reject new names at BOTH import search roots:
    # initializers, package/module shadows, bytecode and extension modules can
    # execute before an unchanged module is reached. Other existing scripts are
    # not invoked by setup; their application code need not be byte-identical.
    for directory in ('scripts', 'scripts/resource_lifecycle'):
        def namespace(revision):
            return {tuple(row.split('\t', 1)[0].split()[:2]) + (row.split('\t', 1)[1],)
                    for row in text(['git', 'ls-tree', revision + ':' + directory], root).splitlines()}
        require(namespace(oid) <= namespace('HEAD'),
                'Unsupported revision: changed host import namespace ' + directory)
        initializer = directory + '/__init__.py'
        require(not text(['git', 'ls-tree', oid, '--', initializer], root),
                'Unsupported revision: unqualified host package initializer ' + initializer)
    entries = text(['git', 'ls-tree', '-r', oid, '--', *SETUP_PATHS], root).splitlines()
    require(entries, 'Unsupported revision: no documented setup boundary')
    paths = set()
    for entry in entries:
        metadata, path = entry.split('\t', 1)
        mode, kind, blob = metadata.split()
        local = root / path
        require(kind == 'blob' and mode in ('100644', '100755') and local.is_file() and
                not local.is_symlink(), 'Unsupported revision: unsafe setup path ' + path)
        require(local.read_bytes() == subprocess.check_output(['git', 'cat-file', 'blob', blob], cwd=root) and
                bool(local.stat().st_mode & 0o111) == (mode == '100755'),
                'Unsupported revision: changed host setup boundary ' + path)
        paths.add(path)
    required = {path for path in SETUP_PATHS if path != 'etc/docker'}
    required.update(str(path.relative_to(root)) for path in (root / 'etc/docker').rglob('*') if path.is_file())
    require(paths == required, 'Unsupported revision: incomplete documented setup boundary')
    # No lockfile rewriting or arbitrary Composer plugins/lifecycle scripts.
    for path in ('composer.lock', 'client/package-lock.json', 'docs/engineering/SUPER_ADMIN_BOOTSTRAP.md',
                 'docs/engineering/INVITATION_EMAIL.md'):
        require(text(['git', 'ls-tree', oid, '--', path], root).startswith('100644 blob '),
                'Unsupported revision: missing locked/documented synthetic setup ' + path)


def scenario_path(safe, project):
    return safe / 'var/worktrees' / project / 'output/qa-activation.html'


def setup(root, safe, checkout, project):
    """Use only the selected revision's documented wrappers, isolated config and data"""
    def tool(*argv, capture=False):
        return run([checkout / 'bin' / argv[0], *argv[1:]], checkout,
                   capture=capture, env=environment(True))

    # Public runtime preparation already creates independent keys/settings. Opt
    # only this disposable project into the documented local catcher adapter.
    private = safe / 'var/worktrees' / project / 'task.env'
    contents = private.read_text()
    require("APP_CREDENTIAL_DELIVERY_ADAPTER='null'\n" in contents,
            'Unexpected disposable delivery configuration; inspect privately')
    write_private(private, contents.replace("APP_CREDENTIAL_DELIVERY_ADAPTER='null'\n",
                                           "APP_CREDENTIAL_DELIVERY_ADAPTER='catcher'\n"))
    run([root / 'bin/up', '--worktree', checkout, '--build-only'], root)
    tool('composer', 'install', '--no-interaction', '--prefer-dist', '--no-progress', '--no-scripts', '--no-plugins')
    tool('client', 'setup')
    tool('client', 'build')
    tool('database', 'start')
    tool('database', 'migrate')
    # Suppress the supplier's ready URL until synthetic scenario setup also passes.
    run([root / 'bin/up', '--worktree', checkout], root, capture=True)
    # Public activation/login fail closed without the exact source/lock catalog.
    # Generate through the documented wrapper before reporting scenario readiness.
    tool('validations', 'export')
    tool('validations', 'check')
    operator = str(uuid.uuid4())
    email = 'human-qa@example.test'
    result = json.loads(tool('console', 'user:bootstrap-super-admin', '--internal', '--confirm-bootstrap',
                             '--operator=' + operator, '--email=' + email, capture=True))
    require(result.get('status') == 'bootstrap_complete', 'Synthetic bootstrap incomplete; inspect isolated state')
    args = compose(safe, checkout, project, True)

    def mailbox(path):
        # Response bodies contain credentials: capture in memory, never stream or log.
        code = 'echo file_get_contents(' + json.dumps('http://mailcatcher:8025/api/v1/' + path) + ');'
        return json.loads(run([*args, 'exec', '-T', 'web', 'php', '-r', code], checkout,
                              capture=True, env=environment(True)))

    messages = mailbox('messages')
    require(messages.get('total') == 1 and len(messages.get('messages', [])) == 1,
            'Synthetic catcher mail unavailable/ambiguous; no activation scenario ready')
    message_id = messages['messages'][0]['ID']
    require(re.fullmatch(r'[A-Za-z0-9-]+', message_id), 'Invalid catcher message identifier')
    message = mailbox('message/' + message_id)
    host = project + '.localhost'
    links = re.findall(r'https://' + re.escape(host) + r'/app/activate#[^\s<>"\']+', message.get('Text', ''))
    require(len(set(links)) == 1, 'Documented synthetic activation link unavailable; unsupported revision')
    # Only owner-readable runtime output holds the possession link. No password
    # is generated, printed or recorded; the human chooses it in the real UI.
    write_private(scenario_path(safe, project), '<!doctype html><meta name="referrer" content="no-referrer">'
                  '<title>Disposable human QA</title><h1>Synthetic QA account</h1>'
                  '<p>Fresh isolated data only. Activate human-qa@example.test, choose a password, then sign in.</p>'
                  '<a rel="noreferrer" href="' + html.escape(links[0], quote=True) + '">Activate synthetic account</a>\n')
