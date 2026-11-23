#!/usr/bin/env sh
set -eu
cd "$(dirname "$0")/.."

runtime() { ./bin/runtime docker "$@"; }
setup_php() { runtime compose exec -T web php scripts/browser_smoke_setup.php "$@"; }
fail() { printf '%s\n' "$1" >&2; exit 1; }

mode=auto ca= certs=
while [ "$#" -gt 0 ]; do
    case "$1" in
        --ca) [ "$#" -ge 2 ] || exit 2; ca=$2; shift ;;
        --cert-dir) [ "$#" -ge 2 ] || exit 2; certs=$2; shift ;;
        --direct) [ "$mode" = auto ] || exit 2; mode=direct ;;
        --remove-direct) [ "$mode" = auto ] || exit 2; mode=remove ;;
        *) printf 'Usage: ./bin/browser-smoke setup {--ca PUBLIC_CA [--direct [--cert-dir DIRECTORY]]|--remove-direct}\n' >&2; exit 2 ;;
    esac
    shift
done

config=$(runtime compose --profile https config --format json 2>/dev/null) || fail 'Cannot resolve selected Compose project.'
if [ "$mode" = remove ]; then
    [ -z "$ca$certs" ] || exit 2
    # Use the already-built CLI image, not the application service or its dependencies.
    plan=$(printf '%s\n' "$config" | runtime compose --profile https run --rm --no-deps --pull never -T \
        --entrypoint php web scripts/browser_smoke_setup.php plan-removal) || fail 'Cannot verify direct gateway files.'
else
    plan=$(printf '%s\n' "$config" | setup_php plan) || fail 'Cannot read selected browser setup model.'
fi
# Command substitution strips trailing newlines, including the absent shared host in direct mode.
{
    IFS= read -r project
    IFS= read -r output
    IFS= read -r network
    IFS= read -r image
    IFS= read -r default_certs
    IFS= read -r shared_gateway || :
    IFS= read -r host || :
    IFS= read -r metadata || :
} <<EOF
$plan
EOF
[ -n "$project" ] && [ -d "$output" ] && [ -n "$network" ] && [ -n "$image" ] || fail 'Incomplete selected Compose model.'
state=$output/browser-smoke
name=$project-browser-https
[ ! -L "$state" ] || fail 'Preserve a linked browser setup directory.'

if [ "$mode" = remove ]; then
    id=$(runtime ps -aq --filter "name=^/$name\$")
    if [ -n "$id" ]; then
        owner=$(runtime inspect --format '{{index .Config.Labels "com.docker.compose.project"}}/{{index .Config.Labels "com.docker.compose.service"}}{{range .Mounts}}{{if eq .Destination "/etc/nginx/conf.d/default.conf"}}/{{.Source}}{{end}}{{end}}' "$id")
        [ "$owner" = "$project/browser_https/$state/direct.conf" ] || fail 'Preserve unknown gateway ownership.'
        runtime stop --time 10 "$id"
        runtime rm "$id"
    fi
    case "$metadata" in
        absent) ;;
        config-only) rm -f "$state/direct.conf" ;;
        owned) rm -f "$state/envelope.json" "$state/rootCA.pem" "$state/direct.conf" ;;
        *) fail 'Preserve unrecognized direct gateway files.' ;;
    esac
    printf 'Private direct gateway removed; ordinary HTTPS and databases preserved.\n'
    exit 0
fi

openssl=${FIGHT_INGRESS_OPENSSL:-openssl}
[ -f "$ca" ] && ! grep -q 'PRIVATE KEY' "$ca" && "$openssl" x509 -in "$ca" -noout 2>/dev/null ||
    fail 'An existing public CA certificate is required.'
(umask 077; mkdir -p "$state")
[ ! -L "$state/rootCA.pem" ] && [ ! -L "$state/envelope.json" ] || fail 'Preserve linked browser setup files.'
if [ "$mode" = direct ] || [ -z "$host" ]; then
    certs=${certs:-$default_certs}
    [ -n "$certs" ] || fail 'Direct setup needs the existing localhost certificate directory.'
    certs=$(cd "$certs" && pwd -P)
    "$openssl" verify -CAfile "$ca" -verify_hostname localhost "$certs/localhost.pem" >/dev/null 2>&1 ||
        fail 'The existing localhost leaf did not verify against the supplied CA.'
    [ -z "$(runtime ps -aq --filter "name=^/$name\$")" ] && [ ! -e "$state/direct.conf" ] && [ ! -L "$state/direct.conf" ] ||
        fail 'Remove the existing private gateway explicitly before preparing it again.'
    [ ! -e "$state/envelope.json" ] || fail 'Direct setup refuses an existing browser envelope.'
    rendered=$(setup_php render direct < etc/docker/https/default.conf)
    (set -C; printf '%s\n' "$rendered" > "$state/direct.conf")
    mkdir -p "$output/client/check"
    id=$(runtime run --detach --pull never --name "$name" \
        --label "com.docker.compose.project=$project" --label com.docker.compose.service=browser_https \
        --network "$network" --read-only --tmpfs /var/cache/nginx --tmpfs /var/run --tmpfs /tmp \
        --memory 128m --cpus 0.5 --pids-limit 64 --security-opt no-new-privileges:true \
        --mount "type=bind,source=$state/direct.conf,target=/etc/nginx/conf.d/default.conf,readonly" \
        --mount "type=bind,source=$certs/localhost.pem,target=/etc/nginx/certs/localhost.pem,readonly" \
        --mount "type=bind,source=$certs/localhost-key.pem,target=/etc/nginx/certs/localhost-key.pem,readonly" \
        --mount "type=bind,source=$output/client/check,target=/app/var/client/check,readonly" \
        --entrypoint nginx "$image" -g 'daemon off;')
    runtime exec "$id" nginx -t
    address=$(runtime inspect --format "{{if .State.Running}}{{(index .NetworkSettings.Networks \"$network\").IPAddress}}{{end}}" "$id")
    [ -n "$address" ] || fail 'Private gateway not ready; remove the exact owned fixture before preparing again.'
    topology=private-direct-nginx origin=https://localhost:18443 gateway=$state/direct.conf
else
    [ -z "$certs" ] || fail 'Certificate directory is direct-only.'
    [ ! -e "$state/direct.conf" ] || fail 'Remove the private direct gateway before preparing shared smoke.'
    [ -n "$shared_gateway" ] || fail 'Missing selected shared gateway mount.'
    gateway=$shared_gateway/default.conf
    [ -f "$gateway" ] && [ ! -L "$gateway" ] || fail 'Missing selected gateway or linked configuration.'
    common=$(git rev-parse --path-format=absolute --git-common-dir)
    topology=$(setup_php front-door < "$(dirname "$common")/var/local-ingress/compose.json")
    origin=https://$host
    address=$(runtime compose exec -T web getent ahostsv4 host.docker.internal)
    address=${address%% *}
    [ -n "$address" ] || fail 'Cannot resolve the existing host front door.'
    original=$(cat "$gateway"; printf .)
    rendered=$(printf '%s' "${original%.}" | setup_php render shared)
    printf '%s\n' "$rendered" > "$gateway"
    reload() { runtime compose exec -T https nginx -t && runtime compose exec -T https nginx -s reload; }
    if ! reload; then
        if [ "$(cat "$gateway"; printf .)" = "$(printf '%s\n.' "$rendered")" ]; then
            printf '%s' "${original%.}" > "$gateway"
            reload || fail 'Shared task gateway rollback could not be verified.'
        fi
        fail 'Selected task gateway setup incomplete.'
    fi
fi
cp "$ca" "$state/rootCA.pem"
setup_php envelope "$topology" "$origin" "$address" < "$gateway"
printf 'Browser gateway prepared: %s (%s). No development FPM fallback.\n' "$origin" "$topology"
