<?php
declare(strict_types=1);

// Live 0.1.9 finding: the player's browser opens CHIM through the WSL VM address
// (http://172.17.226.57:8081/...), so REMOTE_ADDR is the Windows host on the virtual switch and the
// Logs page refused it. Trust exactly that host (the guest's single private default gateway).

define('PCV_UI_TEST', true);
require_once dirname(__DIR__) . '/server/index.php';

function hostCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $route = "Iface\tDestination\tGateway \tFlags\tRefCnt\tUse\tMetric\tMask\t\tMTU\tWindow\tIRTT\n"
        . "eth0\t00000000\t01E011AC\t0003\t0\t0\t0\t00000000\t0\t0\t0\n"
        . "eth0\t00E011AC\t00000000\t0001\t0\t0\t0\t00F0FFFF\t0\t0\t0\n";
    hostCheck(pcv_ui_wsl_host_address($route) === '172.17.224.1', 'The default gateway must decode from little-endian hex.');
    hostCheck(pcv_ui_wsl_host_address('') === null, 'Unreadable routes trust nothing.');
    hostCheck(pcv_ui_wsl_host_address($route . "eth1\t00000000\t0101A8C0\t0003\t0\t0\t0\t00000000\t0\t0\t0\n") === null,
        'Two different default gateways are ambiguous.');
    hostCheck(pcv_ui_wsl_host_address("Iface\tDestination\tGateway\neth0\t00000000\t08080808\t0003\n") === null,
        'A public gateway is never trusted.');

    hostCheck(pcv_ui_logs_access_allowed(['REMOTE_ADDR' => '172.17.224.1'], '172.17.224.1'), 'The WSL host is admitted.');
    hostCheck(!pcv_ui_logs_access_allowed(['REMOTE_ADDR' => '172.17.224.9'], '172.17.224.1'), 'Another private address is refused.');
    hostCheck(!pcv_ui_logs_access_allowed(['REMOTE_ADDR' => '172.17.224.1', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4'], '172.17.224.1'),
        'Forwarded requests are refused even from the host.');
    hostCheck(!pcv_ui_logs_access_allowed(['REMOTE_ADDR' => '172.17.224.1'], null), 'Without a resolved host, only loopback counts.');
    hostCheck(pcv_ui_logs_access_allowed(['REMOTE_ADDR' => '127.0.0.1'], null), 'Loopback still works.');

    $page = pcv_render_logs_locked_page(['SERVER_PORT' => '8081', 'SCRIPT_NAME' => '/HerikaServer/ext/private_conversation/index.php']);
    hostCheck(str_contains($page, 'href="http://127.0.0.1:8081/HerikaServer/ext/private_conversation/index.php?view=logs"'),
        'The refusal page links to the same page through 127.0.0.1.');
    $hostile = pcv_render_logs_locked_page(['SERVER_PORT' => '80"><script>', 'SCRIPT_NAME' => '/x"><script>alert(1)</script>']);
    hostCheck(!str_contains($hostile, '<script>'), 'Hostile server values never reach the page.');

    echo "PASS logs access trusts only loopback and the WSL host, and explains refusals\n";
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
}
