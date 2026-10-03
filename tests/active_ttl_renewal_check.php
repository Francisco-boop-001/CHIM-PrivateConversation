<?php
declare(strict_types=1);

// 0.1.13 E1: an active scene renews its one-hour lifetime on every turn that keeps it active (at most once a
// minute), so it no longer switches off mid-scene one hour after it started.

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/state.php';

function ttlCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function ttlStored(string $directory): array
{
    return json_decode((string)file_get_contents($directory . '/state.json'), true) ?: [];
}

function ttlWrite(string $directory, array $state): void
{
    file_put_contents($directory . '/state.json', json_encode($state));
}

$stateDirectory = sys_get_temp_dir() . '/pcv-ttl-' . bin2hex(random_bytes(8));
$logDirectory = sys_get_temp_dir() . '/pcv-ttl-log-' . bin2hex(random_bytes(8));
$exitCode = 0;
try {
    ttlCheck(mkdir($stateDirectory, 0700) && mkdir($logDirectory, 0700), 'could not create fixture directories');
    ttlCheck(pcv_log_set_test_directory($logDirectory), 'could not select isolated logger fixture');
    $known = ['404' => 'Stalleo', '505' => 'Guard One'];
    $pair = ['enabled' => true, 'scene_mode' => 'pair', 'actor_a' => '404', 'actor_b' => '505', 'exclude_player' => true, 'bystander_mode' => 'exclude'];
    $key = hash('sha256', 'ttl renewal playthrough');
    pcv_stage($key, $pair, $known, $stateDirectory);
    pcv_begin_request($key, true, $stateDirectory, $known);
    $now = time();

    // 50 minutes into the scene: a turn renews the lifetime to a full hour from now.
    $state = ttlStored($stateDirectory);
    $state['active']['activated_at'] = $now - 3000;
    $state['active']['expires_at'] = $now + 600;
    ttlWrite($stateDirectory, $state);
    $r = pcv_begin_request($key, false, $stateDirectory, $known);
    $active = ttlStored($stateDirectory)['active'] ?? [];
    echo json_encode(['activated_at' => $active['activated_at'] - $now, 'expires_at' => $active['expires_at'] - $now,
        'renewed_at' => isset($active['renewed_at']) ? $active['renewed_at'] - $now : null]) . "\n";
    ttlCheck(($r['status'] ?? null) === 'active', 'The scene stays active.');
    ttlCheck(($active['expires_at'] ?? 0) >= $now + PCV_ACTIVE_TTL - 2 && is_int($active['renewed_at'] ?? null),
        'A turn renews the lifetime to a full hour from now.');

    // 70 minutes after activation the renewed scene is still active and valid.
    $state = ttlStored($stateDirectory);
    $state['active']['activated_at'] = $now - 4200;
    $state['active']['renewed_at'] = $now - 30;
    $state['active']['expires_at'] = $now - 30 + PCV_ACTIVE_TTL;
    ttlWrite($stateDirectory, $state);
    ttlCheck(pcv_valid_stored_state(ttlStored($stateDirectory)), 'A renewed scene older than one hour is a valid stored state.');
    ttlCheck((pcv_read($key, $stateDirectory)['status'] ?? null) === 'active', 'It reads as active.');

    // Renewal happens at most once a minute.
    $before = ttlStored($stateDirectory)['active']['expires_at'];
    pcv_begin_request($key, false, $stateDirectory, $known);
    ttlCheck(ttlStored($stateDirectory)['active']['expires_at'] === $before, 'No renewal within a minute of the last one.');

    // Validation bounds.
    $bad = ttlStored($stateDirectory);
    $bad['active']['expires_at'] = $bad['active']['renewed_at'] + PCV_ACTIVE_TTL + 5;
    ttlCheck(!pcv_valid_stored_state($bad), 'expires_at may not exceed renewed_at + TTL.');
    $bad = ttlStored($stateDirectory);
    $bad['active']['renewed_at'] = $bad['active']['activated_at'] - 1;
    ttlCheck(!pcv_valid_stored_state($bad), 'renewed_at may not precede activation.');

    // A refused turn does not renew: everyone gone.
    $state = ttlStored($stateDirectory);
    $state['active']['renewed_at'] = $now - 600;
    $state['active']['expires_at'] = $now - 600 + PCV_ACTIVE_TTL;
    ttlWrite($stateDirectory, $state);
    $before = $state['active']['expires_at'];
    pcv_begin_request($key, false, $stateDirectory, ['404' => 'Stalleo']);
    ttlCheck((ttlStored($stateDirectory)['active']['expires_at'] ?? null) === $before, 'A refused turn does not renew the scene.');

    echo "PASS active scenes renew their lifetime on use (at most once a minute) and refused turns do not\n";
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
