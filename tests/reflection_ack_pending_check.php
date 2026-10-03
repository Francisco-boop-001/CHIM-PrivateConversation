<?php
declare(strict_types=1);

// Live 0.1.9 finding: the real client ACKs early solo lines while CHIM is still generating, before
// PCV registers the reply, which logged a misleading `registration_missing`. While the solo request
// for that scope and actor is in flight, such an ACK is "reply in progress".

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/state.php';

function pendCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$directory = sys_get_temp_dir() . '/pcv-inflight-' . bin2hex(random_bytes(6));
$exitCode = 0;
try {
    pendCheck(mkdir($directory, 0700), 'could not create fixture directory');
    $config = '123e4567-e89b-42d3-a456-426614174000';
    pcv_solo_inflight_mark($config, '5124', $directory, 1000);
    pendCheck(pcv_solo_inflight_matches($config, '5124', $directory, 1100), 'A fresh marker for the same scope and actor matches.');
    pendCheck(!pcv_solo_inflight_matches($config, '2905', $directory, 1100), 'Another actor never matches.');
    pendCheck(!pcv_solo_inflight_matches('123e4567-e89b-42d3-a456-4266141740ff', '5124', $directory, 1100), 'Another scene never matches.');
    pendCheck(!pcv_solo_inflight_matches($config, '5124', $directory, 1000 + PCV_SOLO_INFLIGHT_TTL + 1), 'The marker expires.');
    pendCheck(!pcv_solo_inflight_matches($config, '5124', $directory, 999), 'A marker from the future never matches.');
    pcv_solo_inflight_clear($directory);
    pendCheck(!pcv_solo_inflight_matches($config, '5124', $directory, 1100), 'A cleared marker never matches.');
    file_put_contents($directory . '/solo_inflight.json', '{not json');
    pendCheck(!pcv_solo_inflight_matches($config, '5124', $directory, 1100), 'A corrupt marker never matches.');

    pendCheck(pcv_log_rule_matches('reflection.ack_pending', 'debug', 'skipped', 'reply_in_progress'),
        'The log rule accepts reflection.ack_pending / reply_in_progress at debug severity.');
    pendCheck(!pcv_log_rule_matches('reflection.ack_pending', 'debug', 'skipped', 'registration_missing'),
        'reflection.ack_pending carries only reply_in_progress.');

    echo "PASS early ACKs of an in-flight solo reply are classified as pending\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    foreach (glob($directory . '/{,.}*', GLOB_BRACE) ?: [] as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
    @rmdir($directory);
}
exit($exitCode);
