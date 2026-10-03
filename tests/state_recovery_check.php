<?php
declare(strict_types=1);

// 0.1.13 E2: END recovers an unreadable state file (preserving the bad copy); ARM still refuses to overwrite it.
// 0.1.13 E3: a readable store with no active or pending scene does not count as stored state, so an identity
// hiccup no longer blocks ordinary input; unreadable or scene-holding stores still do.

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/state.php';
require_once dirname(__DIR__) . '/server/scope.php';

function recCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$stateDirectory = sys_get_temp_dir() . '/pcv-recovery-' . bin2hex(random_bytes(8));
$logDirectory = sys_get_temp_dir() . '/pcv-recovery-log-' . bin2hex(random_bytes(8));
$exitCode = 0;
try {
    recCheck(mkdir($stateDirectory, 0700) && mkdir($logDirectory, 0700), 'could not create fixture directories');
    recCheck(pcv_log_set_test_directory($logDirectory), 'could not select isolated logger fixture');
    $known = ['404' => 'Stalleo', '505' => 'Guard One'];
    $pair = ['enabled' => true, 'scene_mode' => 'pair', 'actor_a' => '404', 'actor_b' => '505', 'exclude_player' => true, 'bystander_mode' => 'exclude'];
    $key = hash('sha256', 'recovery playthrough');

    // E2: ARM on a corrupt store refuses and leaves it intact; END recovers it.
    file_put_contents($stateDirectory . '/state.json', '{broken json');
    recCheck((pcv_stage($key, $pair, $known, $stateDirectory)['status'] ?? null) === 'unavailable', 'ARM refuses a corrupt store.');
    recCheck((string)file_get_contents($stateDirectory . '/state.json') === '{broken json', 'ARM never overwrites a corrupt store.');
    $ended = pcv_stage($key, ['enabled' => false], $known, $stateDirectory);
    echo 'end=' . json_encode($ended['status'] ?? null) . "\n";
    recCheck(($ended['status'] ?? null) === 'off', 'END on a corrupt store succeeds.');
    recCheck((pcv_read($key, $stateDirectory)['status'] ?? null) === 'off', 'The store reads as off afterwards.');
    $copies = glob($stateDirectory . '/state.json.corrupt-*') ?: [];
    recCheck(count($copies) === 1 && (string)file_get_contents($copies[0]) === '{broken json', 'The bad file is preserved.');
    recCheck((pcv_stage($key, $pair, $known, $stateDirectory)['status'] ?? null) === 'pending', 'ARM works again after recovery.');

    // E2: a schema-invalid store is recovered the same way.
    file_put_contents($stateDirectory . '/state.json', json_encode(['version' => 999]));
    recCheck((pcv_stage($key, ['enabled' => false], $known, $stateDirectory)['status'] ?? null) === 'off', 'END recovers an invalid store.');

    // E2: a non-regular state.json is not "recovered".
    $odd = $stateDirectory . '-odd';
    mkdir($odd, 0700);
    mkdir($odd . '/state.json', 0700);
    recCheck((pcv_stage($key, ['enabled' => false], $known, $odd)['status'] ?? null) === 'unavailable', 'A directory named state.json is left alone.');
    rmdir($odd . '/state.json');
    @unlink($odd . '/state.lock');
    rmdir($odd);

    $entries = array_values(array_filter(array_map(static fn($line) => json_decode($line, true), file($logDirectory . '/events.jsonl') ?: []), 'is_array'));
    $recovered = array_values(array_filter($entries, static fn($e) => ($e['event'] ?? null) === 'state.store_recovered'));
    recCheck(count($recovered) === 2 && ($recovered[0]['reason'] ?? null) === 'invalid_json' && ($recovered[1]['reason'] ?? null) === 'invalid_state'
        && ($recovered[0]['severity'] ?? null) === 'warning', 'Each recovery is logged as a warning with its reason.');

    // E3: an empty readable store is not "stored state"; a scene-holding or unreadable one is.
    $empty = $stateDirectory . '-empty';
    mkdir($empty, 0700);
    file_put_contents($empty . '/state.json', json_encode(pcv_empty_store($key)));
    recCheck(!pcvScopeStoredStateExists($empty), 'A store with no active or pending scene does not block an identity hiccup.');
    pcv_stage($key, $pair, $known, $empty);
    recCheck(pcvScopeStoredStateExists($empty), 'A store holding a pending scene still counts.');
    file_put_contents($empty . '/state.json', '{broken');
    recCheck(pcvScopeStoredStateExists($empty), 'An unreadable store still counts (fail closed).');
    foreach (glob($empty . '/{,.}*', GLOB_BRACE) ?: [] as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
    @rmdir($empty);

    echo "PASS END recovers unreadable state (bad copy kept, ARM still refuses); empty stores no longer block identity hiccups\n";
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
