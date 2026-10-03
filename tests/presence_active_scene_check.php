<?php
declare(strict_types=1);

// Live 0.1.9 finding: inside an active pair scene the partner can wander out of the close
// infonpc_close range for a turn (Bruce 09:45, 09:58) or the first report after a gap is only a
// baseline (10:06). Active scenes must tolerate that; activation stays strict.

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/state.php';
require_once dirname(__DIR__) . '/server/scope.php';

function activeCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function activeCatalog(int $activityTimestamp): array
{
    $metadata = json_encode(['activity_status' => ['timestamp' => $activityTimestamp]], JSON_THROW_ON_ERROR);
    return [
        ['id' => 101, 'profile_id' => 1, 'npc_name' => 'Aela', 'metadata' => $metadata],
        ['id' => 202, 'profile_id' => 1, 'npc_name' => 'Lydia', 'metadata' => $metadata],
    ];
}

$stateDirectory = sys_get_temp_dir() . '/pcv-active-scene-' . bin2hex(random_bytes(8));
$logDirectory = sys_get_temp_dir() . '/pcv-active-scene-log-' . bin2hex(random_bytes(8));
$exitCode = 0;
try {
    activeCheck(mkdir($logDirectory, 0700), 'could not create isolated logger fixture');
    activeCheck(pcv_log_set_test_directory($logDirectory), 'could not select isolated logger fixture');

    // 1. Close captures keep a recent-name map so a name absent from the newest report is remembered.
    $key = hash('sha256', 'active scene presence playthrough');
    $h = 452_813_985_015_600;
    pcv_capture_background_presence_report($key, 'Aela/Lydia//Hawke', 'Hawke', $h, $stateDirectory);
    pcv_capture_background_presence_report($key, 'Aela//Hawke', 'Hawke', $h + 9_500_000_000, $stateDirectory);
    $doc = json_decode((string)file_get_contents($stateDirectory . '/background_presence.json'), true);
    $recent = $doc['recent'] ?? null;
    echo 'recent=' . json_encode(array_keys($recent ?? [])) . "\n";
    activeCheck(is_array($recent) && isset($recent[pcv_scope_name_key('Lydia')], $recent[pcv_scope_name_key('Aela')]),
        'A name absent from the newest report must stay in the recent map.');
    activeCheck(($recent[pcv_scope_name_key('Lydia')]['name'] ?? null) === 'Lydia'
        && is_int($recent[pcv_scope_name_key('Lydia')]['seen_at'] ?? null), 'Recent entries keep display name and server time.');

    echo "PASS recent-name map\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    foreach (['state.json', 'presence.json', 'background_presence.json', 'background_wide_presence.json', 'solo_inflight.json', 'state.lock'] as $name) {
        @unlink($stateDirectory . DIRECTORY_SEPARATOR . $name);
    }
    @rmdir($stateDirectory);
    foreach (glob($logDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
        @unlink($path);
    }
    @rmdir($logDirectory);
}
exit($exitCode);
