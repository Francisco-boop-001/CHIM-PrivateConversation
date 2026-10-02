#!/bin/bash
# Run PCV PHP fixture tests with the test distro's PHP 8.2. Fixtures use isolated temp dirs only.
# Usage: run-php-tests.sh tests/a_check.php [tests/b_check.php ...]
TEST_DISTRO="${PCV_TEST_DISTRO:-DwemerAI4Skyrim3-test}"
if [ "${WSL_DISTRO_NAME:-}" != "$TEST_DISTRO" ]; then
    echo "REFUSING: not running inside $TEST_DISTRO"; exit 1
fi
cd "$(dirname "$0")/../.." || exit 1
rc=0
for t in "$@"; do
    echo "===== $t"
    timeout 300 php "$t" 2>&1 | tail -n 12
    s=${PIPESTATUS[0]}; echo "exit=$s"; [ "$s" = 0 ] || rc=1
done
exit $rc
