#!/usr/bin/env sh
set -eu

cd "$(dirname "$0")/.."
# Keep the path short enough for Unix sockets in the Herdr fixtures.
scratch=$(mktemp -d /tmp/fph.XXXXXXXX)
trap 'rm -rf "$scratch"' EXIT
export HOME="$scratch" TMPDIR="$scratch" GIT_CEILING_DIRECTORIES="$scratch"
export PI_E2E_LIVE=0 npm_config_cache="$scratch/npm" npm_config_update_notifier=false

node --version
npm --version
git --version
python3 --version
node scripts/dependencies.mjs check
npm test
npm --prefix vendor/pi-subagents run check
node scripts/check-package.mjs
