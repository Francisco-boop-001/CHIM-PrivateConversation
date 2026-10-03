<?php
declare(strict_types=1);

// 0.1.11 group mode: a group starts with the checked members who are present (at least 2), records who was
// dropped, never lets them rejoin, and drops members who leave mid-scene while 2 remain. Pairs stay all-or-nothing.

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

$stateDirectory = sys_get_temp_dir() . '/pcv-group-activation-' . bin2hex(random_bytes(8));
$logDirectory = sys_get_temp_dir() . '/pcv-group-activation-log-' . bin2hex(random_bytes(8));
$exitCode = 0;
try {
    actCheck(mkdir($stateDirectory, 0700) && mkdir($logDirectory, 0700), 'could not create fixture directories');
    actCheck(pcv_log_set_test_directory($logDirectory), 'could not select isolated logger fixture');
    $known = ['404' => 'Stalleo', '505' => 'Guard One', '606' => 'Guard Two'];
    $trio = ['enabled' => true, 'scene_mode' => 'pair', 'actor_ids' => ['404', '505', '606'], 'opener' => 'auto',
        'exclude_player' => true, 'bystander_mode' => 'exclude'];

    // 1. One member not eligible at activation: start with the other two, record the drop.
    $key = hash('sha256', 'group activation playthrough');
    actCheck((pcv_stage($key, $trio, $known, $stateDirectory)['status'] ?? null) === 'pending', 'fixture: the group stages.');
    $r = pcv_begin_request($key, true, $stateDirectory, ['404' => 'Stalleo', '505' => 'Guard One']);
    $active = actStored($stateDirectory)['active'] ?? [];
    echo 'active=' . json_encode($active['config'] ?? null) . ' dropped=' . json_encode($active['dropped'] ?? null) . "\n";
    actCheck(($r['status'] ?? null) === 'active', 'Two of three present: the scene starts.');
    actCheck(($active['config']['actor_ids'] ?? null) === ['404', '505'] && ($active['config']['actor_a'] ?? null) === '404'
        && ($active['config']['actor_b'] ?? null) === '505', 'Active members exclude the absent one and A/B mirror them.');
    actCheck(($active['dropped'] ?? null) === [['id' => '606', 'reason' => 'not_eligible_at_start']], 'The drop is recorded with its reason.');

    // 2. The dropped member never rejoins, even when eligible again.
    pcv_begin_request($key, false, $stateDirectory, ['404' => 'Stalleo', '505' => 'Guard One', '606' => 'Guard Two']);
    actCheck((actStored($stateDirectory)['active']['config']['actor_ids'] ?? null) === ['404', '505'], 'Dropped members do not rejoin.');

    // 3. Only one of three present: the pending group waits.
    $key2 = hash('sha256', 'group pending playthrough');
    pcv_stage($key2, $trio, $known, $stateDirectory);
    $r = pcv_begin_request($key2, true, $stateDirectory, ['404' => 'Stalleo']);
    actCheck(($r['status'] ?? null) !== 'active' && ($r['reason'] ?? null) === 'scene_not_eligible', 'One of three present: the scene waits.');

    // 4. Mid-scene: a member gone from the in-scene map is dropped when two remain; below two, refused.
    $key3 = hash('sha256', 'group midscene playthrough');
    pcv_stage($key3, $trio, $known, $stateDirectory);
    pcv_begin_request($key3, true, $stateDirectory, ['404' => 'S', '505' => 'G1', '606' => 'G2']);
    $r = pcv_begin_request($key3, false, $stateDirectory, null, ['404' => 'S', '505' => 'G1'], 'wide_absent');
    $stored = actStored($stateDirectory);
    actCheck(($r['status'] ?? null) === 'active' && ($stored['active']['config']['actor_ids'] ?? null) === ['404', '505']
        && in_array(['id' => '606', 'reason' => 'left_scene'], $stored['active']['dropped'] ?? [], true),
        'A member gone mid-scene is dropped and the scene continues with two.');
    $r = pcv_begin_request($key3, false, $stateDirectory, null, ['404' => 'S'], 'wide_absent');
    actCheck(($r['reason'] ?? null) === 'scene_not_eligible', 'Below two members the request is refused as today.');

    // 5. Picked opener who is dropped falls back to auto.
    $key5 = hash('sha256', 'group opener playthrough');
    pcv_stage($key5, array_replace($trio, ['opener' => '606']), $known, $stateDirectory);
    pcv_begin_request($key5, true, $stateDirectory, ['404' => 'S', '505' => 'G1']);
    actCheck((actStored($stateDirectory)['active']['config']['opener'] ?? null) === 'auto', 'A dropped picked opener becomes auto.');

    // 6. Legacy two-member pair: unchanged all-or-nothing.
    $key4 = hash('sha256', 'legacy pair playthrough');
    pcv_stage($key4, ['enabled' => true, 'scene_mode' => 'pair', 'actor_a' => '404', 'actor_b' => '505',
        'exclude_player' => true, 'bystander_mode' => 'exclude'], $known, $stateDirectory);
    $r = pcv_begin_request($key4, true, $stateDirectory, ['404' => 'S']);
    actCheck(($r['status'] ?? null) !== 'active', 'A pair with one member present still waits.');

    // 7. Logs: activation counts and mid-scene drops.
    $entries = array_values(array_filter(array_map(static fn($line) => json_decode($line, true), file($logDirectory . '/events.jsonl') ?: []), 'is_array'));
    $activated = array_values(array_filter($entries, static fn($e) => ($e['event'] ?? null) === 'state.scope_activated'));
    echo 'activated_context=' . json_encode($activated[0]['context'] ?? null) . "\n";
    actCheck(($activated[0]['context']['member_count'] ?? null) === 2 && ($activated[0]['context']['dropped_count'] ?? null) === 1,
        'Activation logs member and dropped counts.');
    $dropped = array_values(array_filter($entries, static fn($e) => ($e['event'] ?? null) === 'state.scope_members_dropped'));
    actCheck(($dropped[0]['reason'] ?? null) === 'left_scene' && ($dropped[0]['context']['dropped_count'] ?? null) === 1
        && ($dropped[0]['context']['member_count'] ?? null) === 2, 'A mid-scene drop is logged.');

    echo "PASS groups start with those present (>=2), drop leavers, and keep pairs all-or-nothing\n";
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
