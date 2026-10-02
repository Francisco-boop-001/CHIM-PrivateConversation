<?php
declare(strict_types=1);

// Regression: the client reports NPC activity just after the infonpc_close heartbeat (observed live:
// +80 µs). A read in the same wall-clock second as the heartbeat receipt must still find the NPC fresh.

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/state.php';
require_once dirname(__DIR__) . '/server/scope.php';

function sameSecondCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function sameSecondCatalog(int $activityTimestamp): array
{
    $metadata = json_encode(['activity_status' => ['timestamp' => $activityTimestamp]], JSON_THROW_ON_ERROR);
    return [
        ['id' => 101, 'profile_id' => 1, 'npc_name' => 'Aela', 'metadata' => $metadata],
        ['id' => 202, 'profile_id' => 1, 'npc_name' => 'Lydia', 'metadata' => $metadata],
    ];
}

$stateDirectory = sys_get_temp_dir() . '/pcv-same-second-' . bin2hex(random_bytes(8));
$logDirectory = sys_get_temp_dir() . '/pcv-same-second-log-' . bin2hex(random_bytes(8));
$exitCode = 0;
try {
    sameSecondCheck(mkdir($logDirectory, 0700), 'could not create isolated logger fixture');
    sameSecondCheck(pcv_log_set_test_directory($logDirectory), 'could not select isolated logger fixture');

    $key = hash('sha256', 'same second presence playthrough');
    $report = 'Aela/Lydia//Hawke';
    $h1 = 452_813_985_015_600;          // real-shaped heartbeat clock (about 1e9 units per second)
    $h2 = $h1 + 9_537_540_400;          // next heartbeat ~9.5 s later, as observed live
    $activity = $h2 + 80_000;           // activity reported just after the heartbeat, as observed live

    sameSecondCheck((pcv_capture_background_presence_report($key, $report, 'Hawke', $h1, $stateDirectory)['status'] ?? null) === 'baseline',
        'first heartbeat should establish the baseline');

    // Start the second heartbeat at the beginning of a wall-clock second so the read shares that second.
    usleep((int)((1 - fmod(microtime(true), 1)) * 1_000_000) + 20_000);
    $captured = pcv_capture_background_presence_report($key, $report, 'Hawke', $h2, $stateDirectory);
    $captureSecond = time();
    $read = pcv_read_eligible_npcs($key, sameSecondCatalog($activity), 'Hawke', $stateDirectory);
    $sameSecond = time() === $captureSecond;

    echo 'capture_status=' . ($captured['status'] ?? 'missing') . ' read_same_second=' . ($sameSecond ? 'true' : 'false')
        . ' read_status=' . ($read['status'] ?? 'missing') . ' eligible=' . count($read['known_npcs'] ?? []) . "\n";
    sameSecondCheck($sameSecond, 'fixture timing: the read must share the heartbeat second');
    sameSecondCheck(($read['status'] ?? null) === 'ready' && count($read['known_npcs'] ?? []) === 2,
        'NPCs whose activity follows the heartbeat by microseconds must be fresh in the same second.');

    // Freshness must still expire: the same report read after the TTL is stale.
    $aged = pcv_read_eligible_npcs($key, sameSecondCatalog($h2 - (PCV_PRESENCE_TTL + 2) * 1_000_000_000), 'Hawke', $stateDirectory);
    echo 'aged_status=' . ($aged['status'] ?? 'missing') . "\n";
    sameSecondCheck(($aged['status'] ?? null) === 'stale', 'activity older than the TTL must remain stale');

    echo "PASS same-second heartbeat read keeps post-heartbeat activity fresh; TTL still enforced\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    foreach (['state.json', 'presence.json', 'background_presence.json', 'state.lock'] as $name) {
        @unlink($stateDirectory . DIRECTORY_SEPARATOR . $name);
    }
    @rmdir($stateDirectory);
    foreach (glob($logDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
        @unlink($path);
    }
    @rmdir($logDirectory);
}
exit($exitCode);
