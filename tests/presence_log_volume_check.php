<?php
declare(strict_types=1);

// Heartbeats arrive about every 10 s and the page polls every 15 s, so routine successful presence
// observations must not flood the bounded log. State changes and failures must still be logged.

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/state.php';
require_once dirname(__DIR__) . '/server/scope.php';

function volumeCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function volumeObservations(string $logDirectory, string $source, string $outcome): int
{
    $path = $logDirectory . DIRECTORY_SEPARATOR . 'events.jsonl';
    $lines = is_file($path) ? file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    $count = 0;
    foreach ($lines ?: [] as $line) {
        $entry = json_decode($line, true, 32, JSON_THROW_ON_ERROR);
        if (($entry['event'] ?? null) === 'state.presence_observed'
            && ($entry['context']['source'] ?? null) === $source && ($entry['outcome'] ?? null) === $outcome) {
            $count++;
        }
    }
    return $count;
}

$stateDirectory = sys_get_temp_dir() . '/pcv-volume-' . bin2hex(random_bytes(8));
$logDirectory = sys_get_temp_dir() . '/pcv-volume-log-' . bin2hex(random_bytes(8));
$exitCode = 0;
try {
    volumeCheck(mkdir($logDirectory, 0700), 'could not create isolated logger fixture');
    volumeCheck(pcv_log_set_test_directory($logDirectory), 'could not select isolated logger fixture');

    $key = hash('sha256', 'presence log volume playthrough');
    $report = 'Aela/Lydia//Hawke';
    $ts = 452_813_985_015_600;
    pcv_capture_background_presence_report($key, $report, 'Hawke', $ts, $stateDirectory); // baseline
    for ($i = 1; $i <= 5; $i++) {
        pcv_capture_background_presence_report($key, $report, 'Hawke', $ts + $i * 9_500_000_000, $stateDirectory);
    }
    $sameRoster = volumeObservations($logDirectory, 'background_capture', 'available');
    echo "available_after_5_identical_heartbeats=$sameRoster\n";
    volumeCheck($sameRoster === 1, 'Identical heartbeats must log the roster once, when it becomes available.');

    pcv_capture_background_presence_report($key, 'Aela//Hawke', 'Hawke', $ts + 6 * 9_500_000_000, $stateDirectory);
    $changed = volumeObservations($logDirectory, 'background_capture', 'available');
    echo "available_after_roster_change=$changed\n";
    volumeCheck($changed === 2, 'A changed roster must be logged again.');

    $metadata = json_encode(['activity_status' => ['timestamp' => $ts + 6 * 9_500_000_000 + 80_000]], JSON_THROW_ON_ERROR);
    $catalog = [['id' => 101, 'profile_id' => 1, 'npc_name' => 'Aela', 'metadata' => $metadata]];
    for ($i = 0; $i < 3; $i++) {
        $read = pcv_read_eligible_npcs($key, $catalog, 'Hawke', $stateDirectory);
        volumeCheck(($read['status'] ?? null) === 'ready', 'fixture: reads must succeed');
    }
    $routineReads = volumeObservations($logDirectory, 'background_read', 'available');
    echo "available_reads_logged=$routineReads\n";
    volumeCheck($routineReads === 0, 'Successful routine reads must not be logged.');

    $stale = pcv_read_eligible_npcs($key, [['id' => 101, 'profile_id' => 1, 'npc_name' => 'Aela', 'metadata' => '{}']], 'Hawke', $stateDirectory);
    $staleReads = volumeObservations($logDirectory, 'background_read', 'stale');
    echo 'stale_read_status=' . ($stale['status'] ?? 'missing') . " stale_reads_logged=$staleReads\n";
    volumeCheck($staleReads === 1, 'A stale read must still be logged.');

    echo "PASS routine presence successes are logged on change only; failures still logged\n";
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
