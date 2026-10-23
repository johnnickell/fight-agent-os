# Local PHP diagnostics

Both the built-in HTTP server (`web`, port 18087 by default) and the optional HTTPS FPM service (`https_php`)
load `etc/docker/php/development.ini`. These are local development images, not a qualified production profile.
PHP reports all error levels and sends diagnostics to container stderr, including failures before middleware
boots. Browser display remains off and PHP exception arguments are omitted. This preserves useful file/line and
stack information without turning browser responses into debug pages. Exception messages can still contain
sensitive application input: these local logs are operator-only and must not be published unreviewed.

Read the active checkout's logs with `docker compose logs --tail 100 --follow web https_php`. FPM forwards worker
stdout/stderr without adding child-warning decoration and supports lines up to 16 KiB before wrapping. It stays
in the foreground under the official image entrypoint, so Docker receives its signals and exit status. The old
FIFO/tail workaround is unnecessary with these native controls; see [PHP issue 71880](https://bugs.php.net/bug.php?id=71880)
and the [FPM configuration manual](https://www.php.net/manual/en/install.fpm.configuration.php).
FPM access logging is disabled, matching the existing Nginx policy, because request paths may carry credentials; ordinary PHP
request output stays in the HTTP response, not the diagnostic stream.

Each PHP process has a 512 MiB PHP allocation limit. The existing web service retains its 2 GiB/2 CPU/256 PID
budget for acceptance tools. FPM has a separate 2 GiB/2 CPU/64 PID budget, two on-demand workers, 500-request
recycling and a 60-second request deadline. These are bounded development defaults, not measured production
capacity: native extensions, child processes and the debugger also consume memory outside PHP's allocation limit.
CLI build commands retain their normal unlimited execution time. UTC timestamps and active assertions match
local diagnostic needs. No database, upload or frontend limit is increased without an observed need.

## Rebuild the selected checkout

After an image or environment configuration change, refresh both PHP runtimes and the gateway:

```sh
./bin/up
```

Enroll TLS explicitly once with `./bin/https setup`; use `./bin/up` for every startup/refresh. The shared main checkout is not
restarted by changes or validation in a worktree. Use a distinct `COMPOSE_PROJECT_NAME`, loopback port and private
`.env` for isolated runtime qualification. Changing image settings alone does not repair missing application
keys: configure independent auth-rate, activation-rate, CSRF and signing keys as documented in README.
`APP_DEBUG` remains owned by TASK-00154 and currently does not change application behavior. PHP logging and
Xdebug configuration do not enable it or replace the planned authorized/redacted application diagnostics.

## Trigger an IDE debugger

Both images include Xdebug 3.5.0, disabled by default. In the selected checkout's ignored `.env`, set
`XDEBUG_MODE=debug`, then recreate the relevant service. Configure the IDE to listen on port 9003 and map
`/app` to this checkout. On Docker Desktop, `host.docker.internal` targets that host; other engines need an
explicit reachable host configuration. No debugger listener is exposed by Compose and request headers cannot
select the debugger host.

Send `XDEBUG_TRIGGER=1` using a local debugging extension/cookie for an HTTP request. For one CLI invocation:

```sh
docker compose exec -e XDEBUG_MODE=debug -e XDEBUG_TRIGGER=1 web php scripts/console.php list
```

Use only local disposable data while debugging; an attached IDE can inspect values omitted from normal logs.
Set `XDEBUG_MODE=off` and recreate the service when finished. Coverage commands keep their explicit coverage
mode; they do not enable an IDE session. Long debugger pauses remain subject to the FPM request deadline.
No IDE connection or breakpoint success is implied by the image containing Xdebug.
