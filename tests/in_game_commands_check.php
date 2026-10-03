<?php
declare(strict_types=1);

// 0.1.14 G1/G6: "end scene" typed or spoken in game ends the scene like END; "wrap up: <how>" makes the next reply a
// closing one and then ends the scene. Matching is exact, so ordinary speech never triggers either.

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/state.php';
require_once dirname(__DIR__) . '/server/scope.php';

function igCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$stateDirectory = sys_get_temp_dir() . '/pcv-in-game-' . bin2hex(random_bytes(8));
$logDirectory = sys_get_temp_dir() . '/pcv-in-game-log-' . bin2hex(random_bytes(8));
$exitCode = 0;
try {
    igCheck(mkdir($logDirectory, 0700) && pcv_log_set_test_directory($logDirectory), 'logger fixture');

    // Matching.
    foreach (['Hawke: end scene', 'Hawke: End Scene.', 'Hawke:   end the scene!  ', 'end scene'] as $text) {
        igCheck((pcvInGameCommand($text, 'Hawke')['command'] ?? null) === 'end', "'$text' ends the scene.");
    }
    foreach (['Hawke: end scene now', 'Hawke: we should end scene', 'Hawke: the end', 'Hawke: Lidia, end scene?? no'] as $text) {
        igCheck(pcvInGameCommand($text, 'Hawke') === null, "'$text' is ordinary speech.");
    }
    $wrap = pcvInGameCommand('Hawke: Wrap up: they part ways at the gate.', 'Hawke');
    igCheck(($wrap['command'] ?? null) === 'wrap' && ($wrap['direction'] ?? null) === 'they part ways at the gate.', 'Wrap up keeps the closing direction.');
    igCheck((pcvInGameCommand('Hawke: wrap up:', 'Hawke')['direction'] ?? null) === 'They part ways.', 'An empty wrap-up gets a default parting.');
    igCheck(pcvInGameCommand('Hawke: wrap up the deal, Bruce', 'Hawke') === null, 'Wrap up needs the colon.');

    // END with a reason, logged.
    $known = ['11' => 'A', '12' => 'B'];
    $key = hash('sha256', 'in game');
    pcv_stage($key, ['enabled' => true, 'scene_mode' => 'pair', 'actor_a' => '11', 'actor_b' => '12', 'exclude_player' => true,
        'bystander_mode' => 'exclude'], $known, $stateDirectory);
    $ended = pcv_stage($key, ['enabled' => false], [], $stateDirectory, 'ended_in_game');
    igCheck(($ended['status'] ?? null) === 'off', 'An in-game end clears the scene.');
    $entries = array_values(array_filter(array_map(static fn($line) => json_decode($line, true), file($logDirectory . '/events.jsonl') ?: []), 'is_array'));
    $endLog = array_values(array_filter($entries, static fn($e) => ($e['event'] ?? null) === 'state.scope_ended'));
    igCheck(($endLog[0]['reason'] ?? null) === 'ended_in_game', 'The end is logged with reason ended_in_game.');
    igCheck(pcv_log_rule_matches('state.scope_ended', 'info', 'ok', 'wrapped_up'), 'wrapped_up is an allowed end reason.');
    igCheck(pcv_log_rule_matches('routing.request_skipped', 'info', 'skipped', 'ended_in_game'), 'The consumed input is an informational skip.');

    echo "PASS in-game end and wrap-up phrases match exactly and end the scene with a logged reason\n";
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
