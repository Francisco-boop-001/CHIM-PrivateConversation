<?php
declare(strict_types=1);

// CHIM's wider `infonpc` report ("beings in range") keeps a partner who briefly left the close
// `infonpc_close` range. Observed shape (clone eventlog): "(beings in range:Name,Name,...,)" with a
// trailing comma; CHIM itself splits it on commas.

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/state.php';
require_once dirname(__DIR__) . '/server/scope.php';

function wideCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$stateDirectory = sys_get_temp_dir() . '/pcv-wide-' . bin2hex(random_bytes(8));
$logDirectory = sys_get_temp_dir() . '/pcv-wide-log-' . bin2hex(random_bytes(8));
$exitCode = 0;
try {
    wideCheck(mkdir($logDirectory, 0700), 'could not create isolated logger fixture');
    wideCheck(pcv_log_set_test_directory($logDirectory), 'could not select isolated logger fixture');

    $key = hash('sha256', 'wide presence playthrough');
    $parsed = pcv_parse_wide_presence_report(
        '(beings in range:Aela,Solitude Guard,Argonian (dead),Solitude Guard,Hawke,Bruce Wayne,)', 'Hawke');
    $names = array_column($parsed['actors'] ?? [], 'name');
    echo 'parsed=' . json_encode($names) . "\n";
    wideCheck(($parsed['status'] ?? null) === 'ready' && $names === ['Aela', 'Solitude Guard', 'Solitude Guard', 'Bruce Wayne'],
        'Wide parse keeps live names in order, drops the player, dead actors and the trailing empty token.');
    wideCheck((pcv_parse_wide_presence_report('nonsense', 'Hawke')['status'] ?? null) === 'unavailable',
        'An unknown shape fails closed.');
    $tooMany = '(beings in range:' . implode(',', array_map(static fn($i) => "N$i", range(1, 129))) . ')';
    wideCheck((pcv_parse_wide_presence_report($tooMany, 'Hawke')['status'] ?? null) === 'unavailable',
        'More than 128 names fails closed.');

    $captured = pcv_capture_wide_presence_report($key, '(beings in range:Aela,Bruce Wayne,)', 'Hawke', $stateDirectory);
    $read = pcv_read_wide_presence_names($key, 'Hawke', $stateDirectory);
    echo 'captured=' . ($captured['status'] ?? '-') . ' read=' . json_encode($read) . "\n";
    wideCheck(($read['status'] ?? null) === 'ready' && ($read['counts'][pcv_scope_name_key('Bruce Wayne')] ?? 0) === 1,
        'A fresh wide report is readable by name key.');
    $stale = pcv_read_wide_presence_names($key, 'Hawke', $stateDirectory, time() + PCV_PRESENCE_TTL + 1);
    wideCheck(($stale['status'] ?? null) === 'stale', 'The wide report expires with the presence TTL.');
    wideCheck((pcv_read_wide_presence_names(hash('sha256', 'other'), 'Hawke', $stateDirectory)['status'] ?? null) === 'unavailable',
        'A different playthrough key is refused.');
    wideCheck((pcv_read_wide_presence_names($key, 'Someone Else', $stateDirectory)['status'] ?? null) === 'unavailable',
        'A different player is refused.');

    pcv_capture_wide_presence_report($key, 'garbage', 'Hawke', $stateDirectory);
    wideCheck((pcv_read_wide_presence_names($key, 'Hawke', $stateDirectory)['status'] ?? null) === 'unavailable',
        'An invalid report removes the previous one (fail closed).');

    echo "PASS wide-range report parses, bounds, stores and expires\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    foreach (['background_wide_presence.json', 'state.lock'] as $name) {
        @unlink($stateDirectory . DIRECTORY_SEPARATOR . $name);
    }
    @rmdir($stateDirectory);
    foreach (glob($logDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
        @unlink($path);
    }
    @rmdir($logDirectory);
}
exit($exitCode);
