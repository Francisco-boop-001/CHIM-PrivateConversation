<?php
declare(strict_types=1);

// 0.1.13 E9b: an error while reading the reflection scope is reported as scope_unavailable (and logged), not as
// identity_changed, which stays reserved for a real identity change.

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/state.php';
require_once dirname(__DIR__) . '/server/scope.php';
require_once dirname(__DIR__) . '/server/reflection.php';

$logDirectory = sys_get_temp_dir() . '/pcv-reflection-error-log-' . bin2hex(random_bytes(8));
$exitCode = 0;
try {
    if (!mkdir($logDirectory, 0700) || !pcv_log_set_test_directory($logDirectory)) {
        throw new RuntimeException('could not select isolated logger fixture');
    }
    $failure = null;
    $scope = pcv_reflection_fresh_scope(static function (): array {
        throw new RuntimeException('catalog went away');
    }, $failure);
    if ($scope !== null || $failure !== 'scope_unavailable') {
        throw new RuntimeException('A read error is reported as scope_unavailable, got ' . var_export($failure, true));
    }
    $failure = null;
    pcv_reflection_fresh_scope(static fn() => null, $failure);
    if ($failure !== 'identity_changed') {
        throw new RuntimeException('A missing scope stays identity_changed.');
    }
    if (!pcv_log_rule_matches('reflection.ack_skipped', 'info', 'skipped', 'scope_unavailable')
        || !pcv_log_rule_matches('reflection.registration_skipped', 'info', 'skipped', 'scope_unavailable')) {
        throw new RuntimeException('scope_unavailable is an allowed skip reason.');
    }
    $entries = array_values(array_filter(array_map(static fn($line) => json_decode($line, true), file($logDirectory . '/events.jsonl') ?: []), 'is_array'));
    if (count(array_filter($entries, static fn($e) => ($e['event'] ?? null) === 'state.unavailable')) !== 1) {
        throw new RuntimeException('The read error is logged once.');
    }
    echo "PASS reflection scope read errors are scope_unavailable and logged; missing scopes stay identity_changed\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    foreach (glob($logDirectory . '/*') ?: [] as $path) {
        @unlink($path);
    }
    @rmdir($logDirectory);
}
exit($exitCode);
