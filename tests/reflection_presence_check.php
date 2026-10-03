<?php
declare(strict_types=1);

// 0.1.13 E6: the reflection (ACK) scope check accepts the same in-scene evidence as routing (60 s grace, wide
// report) when the close report is fresh, so an NPC drifting during a long reply keeps its reflection. During a
// heartbeat gap (no fresh report) it stays strict, as simulator case 8c expects.

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/state.php';
require_once dirname(__DIR__) . '/server/scope.php';

function rpCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$stateDirectory = sys_get_temp_dir() . '/pcv-reflection-presence-' . bin2hex(random_bytes(8));
$logDirectory = sys_get_temp_dir() . '/pcv-reflection-presence-log-' . bin2hex(random_bytes(8));
$exitCode = 0;
try {
    rpCheck(mkdir($logDirectory, 0700), 'could not create isolated logger fixture');
    rpCheck(pcv_log_set_test_directory($logDirectory), 'could not select isolated logger fixture');
    $key = hash('sha256', 'reflection presence playthrough');
    $h = 452_813_985_015_600;
    $metadata = json_encode(['activity_status' => ['timestamp' => $h]], JSON_THROW_ON_ERROR);
    $rows = [['id' => 101, 'profile_id' => 1, 'npc_name' => 'Aela', 'metadata' => $metadata],
        ['id' => 202, 'profile_id' => 1, 'npc_name' => 'Lydia', 'metadata' => $metadata]];
    pcv_capture_background_presence_report($key, 'Aela/Lydia//Hawke', 'Hawke', $h, $stateDirectory);
    pcv_capture_background_presence_report($key, 'Aela//Hawke', 'Hawke', $h + 9_500_000_000, $stateDirectory);

    // Fresh close report without Lydia, Lydia inside the grace window.
    $strict = ['101' => 'Aela'];
    $inScene = pcv_read_active_scene_npcs($key, $rows, 'Hawke', ['202'], $stateDirectory);
    echo 'fresh=' . json_encode($inScene) . "\n";
    rpCheck(($inScene['report_fresh'] ?? null) === true, 'A fresh close report is flagged.');
    $map = pcvScopeAckEligibleMap($strict, $inScene);
    rpCheck(isset($map['202'], $map['101']), 'With a fresh report, the reflection check accepts Lydia through grace.');

    // Heartbeat gap: the report ages past the TTL; the reflection check stays strict.
    $path = $stateDirectory . '/background_presence.json';
    $doc = json_decode((string)file_get_contents($path), true);
    $doc['observed_at'] = time() - PCV_PRESENCE_TTL - 5;
    file_put_contents($path, json_encode($doc));
    $gap = pcv_read_active_scene_npcs($key, $rows, 'Hawke', ['202'], $stateDirectory);
    rpCheck(($gap['report_fresh'] ?? null) === false, 'An aged report is not fresh.');
    rpCheck(pcvScopeAckEligibleMap($strict, $gap) === $strict, 'During a heartbeat gap the reflection check stays strict.');

    echo "PASS reflection checks share routing's in-scene evidence with a fresh report and stay strict during gaps\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    foreach (glob($stateDirectory . '/{,.}*', GLOB_BRACE) ?: [] as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
    @rmdir($stateDirectory);
    foreach (glob($logDirectory . '/*') ?: [] as $path) {
        @unlink($path);
    }
    @rmdir($logDirectory);
}
exit($exitCode);
