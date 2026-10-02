<?php
declare(strict_types=1);

// Regression: real infonpc_close reports in busy places list many actors (observed live: 22.9% of
// 13,224 reports exceeded 33 tokens; maximum 79). A crowded report must still yield eligible NPCs,
// keep duplicate names ambiguous, and fail closed only above the documented bound.

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/state.php';
require_once dirname(__DIR__) . '/server/scope.php';

function crowdCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function crowdCatalog(int $activityTimestamp): array
{
    $metadata = json_encode(['activity_status' => ['timestamp' => $activityTimestamp]], JSON_THROW_ON_ERROR);
    return [
        ['id' => 101, 'profile_id' => 1, 'npc_name' => 'Aela the Huntress', 'metadata' => $metadata],
        ['id' => 202, 'profile_id' => 1, 'npc_name' => 'Lidia Sobieska', 'metadata' => $metadata],
    ];
}

// A Solitude-like report: generic guards/mercenaries repeated, named NPCs once, an empty token,
// a status suffix, and the player exactly once. 79 tokens, like the largest real report.
$crowd = [];
for ($i = 0; $i < 30; $i++) {
    $crowd[] = 'Nightingale Mercenary';
}
for ($i = 0; $i < 30; $i++) {
    $crowd[] = 'Solitude Guard';
}
for ($i = 0; $i < 14; $i++) {
    $crowd[] = 'Citizen ' . $i;
}
$crowd[] = 'Aela the Huntress';
$crowd[] = 'Lidia Sobieska (busy)';
$crowd[] = '';
$crowd[] = 'Hawke';
$crowd[] = 'Vania';
$report = implode('/', $crowd);

$stateDirectory = sys_get_temp_dir() . '/pcv-crowd-' . bin2hex(random_bytes(8));
$logDirectory = sys_get_temp_dir() . '/pcv-crowd-log-' . bin2hex(random_bytes(8));
$exitCode = 0;
try {
    crowdCheck(mkdir($logDirectory, 0700), 'could not create isolated logger fixture');
    crowdCheck(pcv_log_set_test_directory($logDirectory), 'could not select isolated logger fixture');

    echo 'tokens=' . count($crowd) . "\n";
    $parsed = pcv_parse_background_presence_report($report, 'Hawke');
    echo 'parse_status=' . ($parsed['status'] ?? 'missing') . ' actors=' . count($parsed['actors'] ?? []) . "\n";
    crowdCheck(($parsed['status'] ?? null) === 'ready', 'A crowded real-sized report must parse.');

    $key = hash('sha256', 'crowd presence playthrough');
    $h1 = 452_813_985_015_600;
    $h2 = $h1 + 9_537_540_400;
    pcv_capture_background_presence_report($key, $report, 'Hawke', $h1, $stateDirectory);
    $captured = pcv_capture_background_presence_report($key, $report, 'Hawke', $h2, $stateDirectory);
    echo 'capture_status=' . ($captured['status'] ?? 'missing') . "\n";
    crowdCheck(($captured['status'] ?? null) === 'ready', 'A crowded report must be stored.');

    sleep(1); // read in a later second than the capture
    $read = pcv_read_eligible_npcs($key, crowdCatalog($h2 + 80_000), 'Hawke', $stateDirectory);
    $eligible = $read['known_npcs'] ?? [];
    ksort($eligible);
    echo 'read_status=' . ($read['status'] ?? 'missing') . ' eligible=' . json_encode($eligible) . "\n";
    crowdCheck(($read['status'] ?? null) === 'ready' && $eligible === ['101' => 'Aela the Huntress', '202' => 'Lidia Sobieska'],
        'Catalog NPCs in a crowded report must remain eligible.');

    // Duplicate catalog names stay ambiguous: two "Aela the Huntress" tokens make her ineligible.
    $ambiguous = $report . '/Aela the Huntress';
    pcv_capture_background_presence_report($key, $ambiguous, 'Hawke', $h2 + 9_000_000_000, $stateDirectory);
    sleep(1);
    $ambiguousRead = pcv_read_eligible_npcs($key, crowdCatalog($h2 + 9_000_080_000), 'Hawke', $stateDirectory);
    echo 'ambiguous_eligible=' . json_encode($ambiguousRead['known_npcs'] ?? []) . "\n";
    crowdCheck(!array_key_exists('101', $ambiguousRead['known_npcs'] ?? []),
        'A duplicated catalog name must not become eligible.');

    // The input-attached routing snapshot has the same crowd shape.
    $present = [];
    foreach (array_merge(array_fill(0, 50, 'Solitude Guard'), ['Aela the Huntress', 'Lidia Sobieska']) as $i => $name) {
        $present[] = ['form_id' => 0x1000 + $i, 'name' => $name, 'distance' => 100.0 + $i, 'managed' => true, 'creature' => false];
    }
    $snapshot = base64_encode(json_encode(['source' => 'plugin_player_routing_v2', 'speech_mode' => 'standard',
        'execution_mode' => 'STANDARD', 'audience_radius_units' => 2000, 'present_actors' => $present], JSON_THROW_ON_ERROR));
    $snapshotParsed = pcv_parse_presence_snapshot($snapshot);
    echo 'snapshot_status=' . ($snapshotParsed['status'] ?? 'missing') . ' actors=' . count($snapshotParsed['actors'] ?? []) . "\n";
    crowdCheck(($snapshotParsed['status'] ?? null) === 'ready', 'A crowded input snapshot must parse.');

    // Above the bound the report still fails closed.
    $oversized = implode('/', array_merge(array_fill(0, PCV_PRESENCE_MAX_ACTORS + 1, 'Solitude Guard'), ['Hawke']));
    $over = pcv_parse_background_presence_report($oversized, 'Hawke');
    echo 'oversized_status=' . ($over['status'] ?? 'missing') . ' reason=' . ($over['reason'] ?? '') . "\n";
    crowdCheck(($over['status'] ?? null) === 'unavailable' && ($over['reason'] ?? null) === 'presence_invalid',
        'A report above the actor bound must fail closed.');

    echo "PASS crowded reports keep catalog NPCs eligible, duplicates ambiguous, and the bound fail-closed\n";
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
