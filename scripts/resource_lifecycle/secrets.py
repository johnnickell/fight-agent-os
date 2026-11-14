"""Explicit service-specific installation secret preparation; no startup generation."""
import json
from pathlib import Path
import secrets
from urllib.parse import quote

from .resources import require, write_private

APPLICATION_KEYS = ('APP_ACCESS_TOKEN_CONFIG', 'APP_CSRF_MAC_KEY', 'APP_AUTH_RATE_KEY',
                    'APP_ACTIVATION_RATE_KEY', 'APP_CREDENTIAL_DELIVERY_KEY')


def provision(directory, values):
    directory = Path(directory)
    directory.mkdir(parents=True, exist_ok=True, mode=0o700)
    require(directory.resolve() == directory, 'Secret directory alias refused')
    paths = ('application.json', 'tooling.json', 'postgres', 'postgres_test', 'seaweedfs.json')
    existing = [name for name in paths if (directory / name).exists()]
    if existing:
        require(len(existing) == len(paths), 'Partial secret setup; inspect privately before continuing')
        print('Existing installation secrets retained; no rotation performed.')
        return
    require(all(values.get(key) for key in (*APPLICATION_KEYS, 'POSTGRES_PASSWORD', 'POSTGRES_TEST_PASSWORD')),
            'Configure independent application keys and database passwords before explicit runtime setup')
    app = {key: values[key] for key in APPLICATION_KEYS}
    for prefix, host, target in (('POSTGRES', 'postgres', 'DATABASE_URL'),
                                  ('POSTGRES_TEST', 'postgres_test', 'TEST_DATABASE_URL')):
        user = values.get(prefix + '_USER', 'agent_os' if host == 'postgres' else 'agent_os_test')
        database = values.get(prefix + '_DB', user)
        url = 'postgresql://' + quote(user, safe='') + ':' + quote(values[prefix + '_PASSWORD'], safe='')
        url += '@' + host + ':5432/' + quote(database, safe='')
        if host == 'postgres':
            app[target] = url
        else:
            test_url = url
        write_private(directory / host, values[prefix + '_PASSWORD'])
    write_private(directory / 'application.json', json.dumps(app) + '\n')
    write_private(directory / 'tooling.json', json.dumps({**app, 'TEST_DATABASE_URL': test_url}) + '\n')
    identity = {'name': 'installation-storage', 'credentials': [{'accessKey': secrets.token_hex(16),
                'secretKey': secrets.token_hex(32)}], 'actions': ['Admin', 'Read', 'Write', 'List', 'Tagging']}
    write_private(directory / 'seaweedfs.json', json.dumps({'identities': [identity]}) + '\n')
    print('Created service-specific ignored secrets; values were not printed.')
