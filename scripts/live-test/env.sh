#!/bin/bash
# Bring the disposable test distro's CHIM services up/down (docs/live-server-testing.md).
# Never runs /etc/start_env: that launcher also starts TTS servers, auto-updates and opens a dashboard.
set -u
TEST_DISTRO="${PCV_TEST_DISTRO:-DwemerAI4Skyrim3-test}"
if [ "${WSL_DISTRO_NAME:-}" != "$TEST_DISTRO" ]; then
    echo "REFUSING: not running inside $TEST_DISTRO"; exit 1
fi
CHIM=/var/www/html/HerikaServer
case "${1:-status}" in
    up)
        # Background workers make their own LLM calls; keep them off unless a test needs them.
        if [ -f "$CHIM/service/start.sh" ]; then
            mv "$CHIM/service/start.sh" "$CHIM/service/start.sh.disabled-for-pcv-test"
        fi
        service postgresql start | tail -1
        service apache2 start | tail -1
        ;;
    down)
        service apache2 stop | tail -1
        service postgresql stop | tail -1
        ;;
    restore-workers)
        if [ -f "$CHIM/service/start.sh.disabled-for-pcv-test" ]; then
            mv "$CHIM/service/start.sh.disabled-for-pcv-test" "$CHIM/service/start.sh"
        fi
        ;;
esac
ss -ltn | grep -E ':(5432|8081) ' || echo "postgres/apache not listening"
ls "$CHIM"/service/start.sh* 2>/dev/null
ps -eo pid,args | grep -E "worker.php|service/manager" | grep -v grep || echo "no background workers"
