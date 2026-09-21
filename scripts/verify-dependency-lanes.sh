#!/usr/bin/env sh
set -eu

project_root=$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)
mode=${1:-verify}

copy_lane() {
    target=$1
    for path in bootstrap composer.json config src templates tests phpunit.xml; do
        if [ -e "$project_root/$path" ]; then
            cp -R "$project_root/$path" "$target/"
        fi
    done
}

assert_runtime_graph() {
    lane_root=$1
    php "$project_root/scripts/framework-support-profile.php" --assert-lane "$lane_root"
}

if [ "$mode" = "refresh-lowest" ]; then
    lane_root=$(mktemp -d)
    trap 'rm -rf "$lane_root"' EXIT
    copy_lane "$lane_root"
    composer update --working-dir="$lane_root" --prefer-lowest --prefer-stable --no-interaction --no-progress
    assert_runtime_graph "$lane_root"
    cp "$lane_root/composer.lock" "$project_root/composer-lowest.lock"
    php -r 'echo hash_file("sha256", $argv[1]), PHP_EOL;' "$project_root/composer-lowest.lock" > "$project_root/composer-lowest.lock.sha256"
    exit 0
fi

if [ "$mode" != "verify" ]; then
    echo "Usage: scripts/verify-dependency-lanes.sh [verify|refresh-lowest]" >&2
    exit 2
fi

expected_lowest=$(tr -d '\r\n' < "$project_root/composer-lowest.lock.sha256")
actual_lowest=$(php -r 'echo hash_file("sha256", $argv[1]);' "$project_root/composer-lowest.lock")
if [ "$expected_lowest" != "$actual_lowest" ]; then
    echo "composer-lowest.lock digest has drifted." >&2
    exit 1
fi

for lane in latest lowest; do
    lane_root=$(mktemp -d)
    trap 'rm -rf "$lane_root"' EXIT HUP INT TERM
    copy_lane "$lane_root"
    if [ "$lane" = latest ]; then
        cp "$project_root/composer.lock" "$lane_root/composer.lock"
    else
        cp "$project_root/composer-lowest.lock" "$lane_root/composer.lock"
    fi
    before=$(php -r 'echo hash_file("sha256", $argv[1]);' "$lane_root/composer.lock")
    composer install --working-dir="$lane_root" --no-interaction --prefer-dist --no-progress
    after=$(php -r 'echo hash_file("sha256", $argv[1]);' "$lane_root/composer.lock")
    if [ "$before" != "$after" ]; then
        echo "$lane dependency lane changed its lock during install." >&2
        exit 1
    fi
    assert_runtime_graph "$lane_root"
    (
        cd "$lane_root"
        vendor/bin/phpunit \
            tests/BootstrapTest.php \
            tests/Integration/ContainerContractsTest.php \
            tests/Integration/MessagingJourneyTest.php \
            tests/Integration/SecurityConfigurationTest.php \
            tests/Integration/SecurityAndValidationJourneyTest.php \
            tests/Integration/HttpRoutingAndPresentationJourneyTest.php \
            tests/Integration/PersistenceAndStorageJourneyTest.php \
            tests/Integration/SystemAndObservabilityJourneyTest.php \
            tests/Integration/CommunicationJourneyTest.php \
            tests/Functional/Http/ProductionRouteBoundaryTest.php \
            tests/Functional/Http/HttpPipelineTest.php
    )
    rm -rf "$lane_root"
    trap - EXIT HUP INT TERM
done
