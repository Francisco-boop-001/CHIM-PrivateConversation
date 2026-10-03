<?php
declare(strict_types=1);

// 0.1.12 free mode: a pending free scene starts with the nearest six strictly eligible NPCs (nearest first),
// waits below two, and logs free_scene with the member count.

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/state.php';
require_once dirname(__DIR__) . '/server/scope.php';

function actCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function actStored(string $directory): array
{
    return json_decode((string)file_get_contents($directory . '/state.json'), true) ?: [];
}

$stateDirectory = sys_get_temp_dir() . '/pcv-free-activation-' . bin2hex(random_bytes(8));
$logDirectory = sys_get_temp_dir() . '/pcv-free-activation-log-' . bin2hex(random_bytes(8));
$exitCode = 0;
try {
    actCheck(mkdir($stateDirectory, 0700) && mkdir($logDirectory, 0700), 'could not create fixture directories');
    actCheck(pcv_log_set_test_directory($logDirectory), 'could not select isolated logger fixture');
    $free = ['enabled' => true, 'scene_mode' => 'pair', 'free' => true, 'exclude_player' => true, 'bystander_mode' => 'exclude'];
    $known = ['11' => 'A', '12' => 'B', '13' => 'C', '14' => 'D', '15' => 'E', '16' => 'F', '17' => 'G', '18' => 'H', '19' => 'I'];

    // 1. A crowd of nine: the nearest six become the members, nearest first.
    $key = hash('sha256', 'free activation');
    actCheck((pcv_stage($key, $free, $known, $stateDirectory)['status'] ?? null) === 'pending', 'fixture: the free scene stages.');
    $order = ['15', '11', '19', '12', '18', '13', '17', '14', '16'];
    $r = pcv_begin_request($key, true, $stateDirectory, $known, null, null, $order);
    $cfg = actStored($stateDirectory)['active']['config'] ?? [];
    echo json_encode($cfg) . "\n";
    actCheck(($r['status'] ?? null) === 'active' && ($cfg['actor_ids'] ?? null) === ['15', '11', '19', '12', '18', '13']
        && ($cfg['actor_a'] ?? null) === '15' && ($cfg['actor_b'] ?? null) === '11' && ($cfg['opener'] ?? null) === 'auto'
        && ($cfg['free'] ?? null) === true, 'The nearest six become the members, nearest first.');
    actCheck(!isset(actStored($stateDirectory)['active']['dropped']), 'Free scenes record no not-eligible drops at start.');

    // 2. Fewer than two candidates: the free scene waits.
    $key2 = hash('sha256', 'free too few');
    pcv_stage($key2, $free, $known, $stateDirectory);
    $r = pcv_begin_request($key2, true, $stateDirectory, ['11' => 'A'], null, null, ['11']);
    actCheck(($r['status'] ?? null) !== 'active' && ($r['reason'] ?? null) === 'scene_not_eligible', 'Fewer than two: the free scene waits.');

    // 3. Only strictly eligible candidates count.
    $key3 = hash('sha256', 'free filtered');
    pcv_stage($key3, $free, $known, $stateDirectory);
    pcv_begin_request($key3, true, $stateDirectory, ['12' => 'B', '13' => 'C'], null, null, ['11', '12', '99', '13']);
    actCheck((actStored($stateDirectory)['active']['config']['actor_ids'] ?? null) === ['12', '13'], 'Only strictly eligible candidates count.');

    // 4. Without a distance order, the eligible map order is used.
    $key4 = hash('sha256', 'free no order');
    pcv_stage($key4, $free, $known, $stateDirectory);
    pcv_begin_request($key4, true, $stateDirectory, ['13' => 'C', '12' => 'B']);
    actCheck((actStored($stateDirectory)['active']['config']['actor_ids'] ?? null) === ['13', '12'], 'No order: map order.');

    $actors = [['name' => 'C', 'distance' => 300.0], ['name' => 'Stranger', 'distance' => 10.0], ['name' => 'B', 'distance' => 120],
        ['name' => 'A', 'distance' => 300.0], ['name' => 'D', 'distance' => 50.5]];
    $ordered = pcvScopeFreeCandidateOrder($actors, ['11' => 'A', '12' => 'B', '13' => 'C', '14' => 'D']);
    echo 'order=' . json_encode($ordered) . "\n";
    actCheck($ordered === ['14', '12', '11', '13'], 'Candidates are nearest first, ties by catalog ID, strangers skipped.');

    actCheck(pcv_free_select_members(['3', '1', '2', '1'], ['1' => 'x', '2' => 'y', '3' => 'z'], 2) === ['3', '1'], 'Selection keeps order and caps.');

    // 5. Logs: staged and activated carry free_scene; activation counts six members.
    $entries = array_values(array_filter(array_map(static fn($line) => json_decode($line, true), file($logDirectory . '/events.jsonl') ?: []), 'is_array'));
    $staged = array_values(array_filter($entries, static fn($e) => ($e['event'] ?? null) === 'state.scope_staged'));
    $activated = array_values(array_filter($entries, static fn($e) => ($e['event'] ?? null) === 'state.scope_activated'));
    echo 'activated_context=' . json_encode($activated[0]['context'] ?? null) . "\n";
    actCheck(($staged[0]['context']['free_scene'] ?? null) === true, 'Staging logs free_scene.');
    actCheck(($activated[0]['context']['free_scene'] ?? null) === true && ($activated[0]['context']['member_count'] ?? null) === 6,
        'Activation logs free_scene and six members.');

    echo "PASS free activation picks the nearest eligible six and waits below two\n";
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
