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

    // 2. In-scene presence: close report, 60 s grace, wide report, baseline after a gap.
    $rows = activeCatalog($h);
    $now = time();
    $r = pcv_read_active_scene_npcs($key, $rows, 'Hawke', ['101', '202'], $stateDirectory, $now);
    echo 'grace=' . json_encode($r) . "\n";
    activeCheck(isset($r['known_npcs']['101'], $r['known_npcs']['202']) && $r['missing'] === [],
        'Lydia, absent from the newest close report but seen seconds ago, must count through the grace window.');

    // Expire Lydia's grace by ageing her recent entry (moving the clock would also age the wide report).
    $path = $stateDirectory . '/background_presence.json';
    $doc = json_decode((string)file_get_contents($path), true);
    $doc['recent'][pcv_scope_name_key('Lydia')]['seen_at'] = time() - PCV_PRESENCE_ACTIVE_GRACE - 1;
    file_put_contents($path, json_encode($doc) . "\n");
    $r = pcv_read_active_scene_npcs($key, $rows, 'Hawke', ['101', '202'], $stateDirectory, time());
    echo 'expired=' . json_encode($r) . "\n";
    activeCheck(!isset($r['known_npcs']['202']) && ($r['missing']['202'] ?? null) === 'wide_unavailable',
        'After the grace window, with no wide report, Lydia is missing (wide_unavailable).');

    pcv_capture_wide_presence_report($key, '(beings in range:Lydia,Aela,)', 'Hawke', $stateDirectory);
    $r = pcv_read_active_scene_npcs($key, $rows, 'Hawke', ['101', '202'], $stateDirectory, time());
    activeCheck(isset($r['known_npcs']['202']) && $r['missing'] === [], 'The wide report keeps Lydia in the scene after the grace window.');

    pcv_capture_wide_presence_report($key, '(beings in range:Aela,Lydia,Lydia,)', 'Hawke', $stateDirectory);
    $r = pcv_read_active_scene_npcs($key, $rows, 'Hawke', ['101', '202'], $stateDirectory, time());
    activeCheck(($r['missing']['202'] ?? null) === 'wide_absent', 'A duplicated wide name is ambiguous, never present.');

    pcv_capture_wide_presence_report($key, '(beings in range:Aela,)', 'Hawke', $stateDirectory);
    $r = pcv_read_active_scene_npcs($key, $rows, 'Hawke', ['101', '202'], $stateDirectory, time());
    activeCheck(($r['missing']['202'] ?? null) === 'wide_absent', 'Absent from close, grace and wide: wide_absent.');

    // Baseline after a gap (live 10:06:41Z): both named in the current report, unseen for minutes before it.
    $path = $stateDirectory . '/background_presence.json';
    $doc = json_decode((string)file_get_contents($path), true);
    $doc['state'] = 'baseline';
    $doc['actors'] = [['name' => 'Aela'], ['name' => 'Lydia']];
    $doc['baseline_timestamp'] = $doc['heartbeat_timestamp'];
    $doc['recent'] = [];
    $doc['observed_at'] = time();
    file_put_contents($path, json_encode($doc) . "\n");
    $r = pcv_read_active_scene_npcs($key, $rows, 'Hawke', ['101', '202'], $stateDirectory, time());
    echo 'baseline=' . json_encode($r) . "\n";
    activeCheck($r['missing'] === [] && count($r['known_npcs']) === 2, 'A fresh baseline report still counts its names for an active scene.');

    // Stale close report and no other evidence: nobody counts.
    $r = pcv_read_active_scene_npcs($key, $rows, 'Hawke', ['101', '202'], $stateDirectory, time() + PCV_PRESENCE_TTL + PCV_PRESENCE_ACTIVE_GRACE + 2);
    activeCheck(count($r['missing']) === 2, 'With every source stale, both participants are missing.');

    // Another playthrough or player never borrows this evidence.
    $r = pcv_read_active_scene_npcs(hash('sha256', 'other playthrough'), $rows, 'Hawke', ['101', '202'], $stateDirectory, time());
    activeCheck(count($r['missing']) === 2, 'A different playthrough key gets no in-scene presence.');

    // 3. pcv_begin_request: an active scene survives through the in-scene map; activation stays strict.
    $sceneKey = hash('sha256', 'active scene begin playthrough');
    $staged = pcv_stage($sceneKey, ['enabled' => true, 'scene_mode' => 'pair', 'actor_a' => '101', 'actor_b' => '202',
        'exclude_player' => true, 'bystander_mode' => 'exclude'], ['101' => 'Aela', '202' => 'Lydia'], $stateDirectory);
    activeCheck(($staged['status'] ?? null) === 'pending', 'fixture: scene must stage (status=' . ($staged['status'] ?? '-') . ').');
    $strictOnlyAela = ['101' => 'Aela'];
    $activation = pcv_begin_request($sceneKey, true, $stateDirectory, $strictOnlyAela, ['101' => 'Aela', '202' => 'Lydia'], null);
    activeCheck(($activation['status'] ?? null) !== 'active', 'Activation must stay strict: the in-scene map never activates a scene.');
    $activation = pcv_begin_request($sceneKey, true, $stateDirectory, ['101' => 'Aela', '202' => 'Lydia']);
    activeCheck(($activation['status'] ?? null) === 'active', 'fixture: scene activates with both strictly eligible.');
    $blocked = pcv_begin_request($sceneKey, false, $stateDirectory, $strictOnlyAela);
    activeCheck(($blocked['reason'] ?? null) === 'scene_not_eligible', 'Without the in-scene map, a missing partner still blocks.');
    $kept = pcv_begin_request($sceneKey, false, $stateDirectory, $strictOnlyAela, ['101' => 'Aela', '202' => 'Lydia'], null);
    activeCheck(($kept['status'] ?? null) === 'active', 'With the partner in the in-scene map, the active scene continues.');
    pcv_begin_request($sceneKey, false, $stateDirectory, $strictOnlyAela, $strictOnlyAela, 'wide_absent');
    $skips = array_values(array_filter(array_map(static fn($l) => json_decode($l, true), file($logDirectory . '/events.jsonl') ?: []),
        static fn($e) => is_array($e) && ($e['event'] ?? null) === 'state.scope_skipped'));
    $lastSkip = end($skips);
    echo 'skip_context=' . json_encode($lastSkip['context'] ?? null) . "\n";
    activeCheck(($lastSkip['context']['presence_check'] ?? null) === 'wide_absent' && ($lastSkip['context']['missing_count'] ?? null) === 1,
        'A refused active scene logs which check failed and how many participants were missing.');

    echo "PASS active scenes tolerate brief absence through grace, wide range and baseline; activation unchanged\n";
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
