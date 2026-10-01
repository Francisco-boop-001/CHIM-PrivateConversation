<?php
declare(strict_types=1);

if (($argv[1] ?? null) === '--storage-health-child') {
    require_once dirname(__DIR__) . '/server/log.php';
    putenv('PCV_LOG_DIR=' . ($argv[2] ?? ''));
    $health = pcv_log_storage_health();
    $health['mode'] = $health['storage_mode'];
    echo json_encode($health, JSON_THROW_ON_ERROR);
    exit(0);
}

if (($argv[1] ?? null) === '--storage-failure-child') {
    require_once dirname(__DIR__) . '/server/log.php';
    putenv('PCV_LOG_DIR=' . ($argv[2] ?? ''));
    pcv_log_event('ui.page_open', 'info', 'ok');
    $directory = pcv_log_default_directory(false);
    if (!is_string($directory)) {
        exit(2);
    }
    $lock = fopen($directory . '/events.lock', 'c');
    if (!is_resource($lock) || !flock($lock, LOCK_EX | LOCK_NB)) {
        exit(3);
    }
    pcv_log_event('ui.page_open', 'info', 'ok');
    flock($lock, LOCK_UN);
    fclose($lock);
    echo json_encode(pcv_log_storage_health(), JSON_THROW_ON_ERROR);
    exit(0);
}

if (($argv[1] ?? null) === '--fifo-lock-child') {
    define('PCV_LOG_TESTING', true);
    require_once dirname(__DIR__) . '/server/log.php';
    $directory = $argv[2] ?? '';
    if (!pcv_log_set_test_directory($directory)) {
        exit(2);
    }
    pcv_log_event('ui.page_open', 'info', 'ok');
    echo json_encode([
        'write_status' => pcv_log_storage_health()['write_status'],
        'failure_codes' => pcv_log_storage_health()['failure_codes'],
        'active_log_exists' => is_file($directory . '/events.jsonl'),
    ], JSON_THROW_ON_ERROR);
    exit(0);
}

if (($argv[1] ?? null) === '--shutdown-child' || ($argv[1] ?? null) === '--fatal-child') {
    define('PCV_LOG_TESTING', true);
    require_once dirname(__DIR__) . '/server/log.php';
    if (!pcv_log_set_test_directory($argv[2] ?? '')) {
        exit(2);
    }
    pcv_log_event('routing.request_started', 'info', 'ok', null, ['request_type' => 'inputtext']);
    pcv_log_set_terminal('postrequest_observed', null, ['phase' => 'postrequest', 'route' => 'player_speech']);
    if ($argv[1] === '--fatal-child') {
        trigger_error('DO NOT LOG THIS FATAL MESSAGE', E_USER_ERROR);
    }
    exit(0);
}

if (($argv[1] ?? null) === '--debug-child' || ($argv[1] ?? null) === '--writer-child') {
    define('PCV_LOG_TESTING', true);
    if ($argv[1] === '--debug-child') {
        putenv('PCV_LOG_DEBUG_UNTIL=' . (time() + 120));
    }
    require_once dirname(__DIR__) . '/server/log.php';
    if (!pcv_log_set_test_directory($argv[2] ?? '')) {
        exit(2);
    }
    if ($argv[1] === '--debug-child') {
        pcv_log_event('routing.request_detail', 'debug', 'ok', 'debug_detail', [
            'phase' => 'context_pre',
            'decision' => 'context_prepared',
            'request_type' => 'instruction',
        ]);
    } else {
        $count = filter_var($argv[3] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($count === false) {
            exit(3);
        }
        for ($index = 0; $index < $count; $index++) {
            pcv_log_event('ui.page_open', 'info', 'ok');
        }
    }
    exit(0);
}

if (($argv[1] ?? null) === '--lock-contention-child') {
    define('PCV_LOG_TESTING', true);
    require_once dirname(__DIR__) . '/server/log.php';
    $directory = $argv[2] ?? '';
    $fallback = $argv[3] ?? '';
    $count = filter_var($argv[4] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
    if (!pcv_log_set_test_directory($directory) || $count === false) {
        exit(2);
    }
    ini_set('error_log', $fallback);
    pcv_log_begin_request(pcv_log_new_uuid());
    fwrite(STDOUT, "READY\n");
    fflush(STDOUT);
    if (fgets(STDIN) === false) {
        exit(3);
    }
    fwrite(STDOUT, "ATTEMPT\n");
    fflush(STDOUT);
    $started = hrtime(true);
    for ($index = 0; $index < $count; $index++) {
        pcv_log_event('state.scope_staged', 'info', 'ok', null, [
            'action' => 'enable',
            'scene_mode' => 'solo',
            'actor_a_id' => '101',
            'actor_b_id' => '202',
            'exclude_player' => true,
            'bystander_mode' => 'exclude',
            'dialogue' => 'PRIVATE-DIALOGUE-SHOULD-NEVER-LEAVE-THIS-CHILD',
            'prompt' => 'PRIVATE-PROMPT-SHOULD-NEVER-LEAVE-THIS-CHILD',
            'claim_token' => 'PRIVATE-CLAIM-TOKEN-SHOULD-NEVER-LEAVE-THIS-CHILD',
        ]);
    }
    echo json_encode([
        'elapsed_ms' => round((hrtime(true) - $started) / 1000000, 2),
        'health' => pcv_log_storage_health(),
    ], JSON_THROW_ON_ERROR);
    exit(0);
}

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';

function logCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function cleanLogFixture(string $directory, string $fallback): bool
{
    $regularFile = 0100000;
    $fifo = 0010000;
    $logFiles = array_fill_keys([
        'events.jsonl', 'events.1.jsonl', 'events.2.jsonl', 'events.3.jsonl', 'events.4.jsonl', 'events.lock',
    ], [$regularFile]);
    $ok = true;
    $removeFile = static function (string $path, array $allowedTypes) use (&$ok): void {
        $stat = @lstat($path);
        if ($stat === false) {
            return;
        }
        if (!in_array($stat['mode'] & 0170000, $allowedTypes, true) || !@unlink($path)) {
            $ok = false;
        }
    };
    $cleanDirectory = static function (string $path, array $allowedFiles) use (&$ok, $removeFile): void {
        $stat = @lstat($path);
        if ($stat === false) {
            return;
        }
        if (($stat['mode'] & 0170000) !== 0040000) {
            $ok = false;
            return;
        }
        $entries = @scandir($path);
        if (!is_array($entries)) {
            $ok = false;
            return;
        }
        foreach ($entries as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            if (!array_key_exists($name, $allowedFiles)) {
                $ok = false;
                continue;
            }
            $removeFile($path . DIRECTORY_SEPARATOR . $name, $allowedFiles[$name]);
        }
        if (!@rmdir($path)) {
            $ok = false;
        }
    };

    $stat = @lstat($directory);
    if ($stat !== false) {
        if (($stat['mode'] & 0170000) !== 0040000) {
            $ok = false;
        } else {
            $rootFiles = $logFiles;
            $rootFiles['fallback.log'] = [$regularFile];
            $rootFiles['lock-retry.log'] = [$regularFile];
            $rootFiles['lock-timeout.log'] = [$regularFile];
            $rootDirectories = [
                'debug' => $logFiles,
                'concurrent' => $logFiles,
                'lock-retry' => $logFiles,
                'lock-timeout' => $logFiles,
                'unsafe' => ['marker' => [$regularFile]],
                'fifo-lock' => [
                    'events.jsonl' => [$regularFile],
                    'events.lock' => [$regularFile, $fifo],
                ],
                'normal-shutdown' => $logFiles,
                'fatal-shutdown' => $logFiles,
            ];
            $entries = @scandir($directory);
            if (!is_array($entries)) {
                $ok = false;
            } else {
                foreach ($entries as $name) {
                    if ($name === '.' || $name === '..') {
                        continue;
                    }
                    $path = $directory . DIRECTORY_SEPARATOR . $name;
                    if (array_key_exists($name, $rootFiles)) {
                        $removeFile($path, $rootFiles[$name]);
                    } elseif (array_key_exists($name, $rootDirectories)) {
                        $cleanDirectory($path, $rootDirectories[$name]);
                    } elseif ($name === 'fallback-temp') {
                        $tempStat = @lstat($path);
                        if ($tempStat === false || ($tempStat['mode'] & 0170000) !== 0040000) {
                            $ok = false;
                            continue;
                        }
                        $tempEntries = @scandir($path);
                        if (!is_array($tempEntries)) {
                            $ok = false;
                            continue;
                        }
                        foreach ($tempEntries as $tempName) {
                            if ($tempName === '.' || $tempName === '..') {
                                continue;
                            }
                            if (preg_match('/^private-conversation-[0-9]+-[a-f0-9]{16}$/D', $tempName) !== 1) {
                                $ok = false;
                                continue;
                            }
                            $cleanDirectory($path . DIRECTORY_SEPARATOR . $tempName, $logFiles);
                        }
                        $cleanDirectory($path, []);
                    } else {
                        $ok = false;
                    }
                }
            }
            if (!@rmdir($directory)) {
                $ok = false;
            }
        }
    }
    $removeFile($fallback, [$regularFile]);
    return $ok;
}

$fixture = sys_get_temp_dir() . '/chim-private-conversation-log-check-' . bin2hex(random_bytes(8));
$fallback = $fixture . '-fallback.log';
$fifoLockDirectory = null;
$oldErrorLog = ini_get('error_log');
$exitCode = 0;
$cleanupFailureProbe = ($argv[1] ?? null) === '--cleanup-failure-probe';
register_shutdown_function(static function () use ($fixture, $fallback, $cleanupFailureProbe): void {
    register_shutdown_function(static function () use ($fixture, $fallback, $cleanupFailureProbe): void {
        if ($cleanupFailureProbe) {
            $terminalWritten = false;
            $logPath = $fixture . DIRECTORY_SEPARATOR . 'events.jsonl';
            $stat = @lstat($logPath);
            $contents = $stat !== false && ($stat['mode'] & 0170000) === 0100000 ? @file_get_contents($logPath) : false;
            foreach (is_string($contents) ? explode("\n", $contents) : [] as $line) {
                $entry = json_decode($line, true);
                if (is_array($entry) && ($entry['event'] ?? null) === 'routing.request_finished'
                    && ($entry['reason'] ?? null) === 'fatal_error') {
                    $terminalWritten = true;
                    break;
                }
            }
            if ($terminalWritten) {
                fwrite(STDOUT, "CLEANUP_PROBE_TERMINAL_WRITTEN\n");
            } else {
                fwrite(STDERR, "FAIL: terminal observer did not write before cleanup.\n");
            }
        }
        if (!cleanLogFixture($fixture, $fallback)) {
            fwrite(STDERR, "FAIL: isolated log fixture cleanup was incomplete; unknown or unsafe entries were preserved.\n");
        }
    });
});

if (($argv[1] ?? null) === '--cleanup-failure-probe') {
    logCheck(@mkdir($fixture, 0700), 'could not create isolated cleanup probe directory');
    logCheck(pcv_log_set_test_directory($fixture), 'logger rejected isolated cleanup probe directory');
    ini_set('error_log', $fallback);
    pcv_log_event('routing.request_started', 'info', 'ok', null, ['request_type' => 'inputtext']);
    pcv_log_event('ui.page_open', 'info', 'ok');
    foreach (['debug', 'concurrent', 'normal-shutdown', 'fatal-shutdown'] as $name) {
        mkdir($fixture . DIRECTORY_SEPARATOR . $name, 0700);
        file_put_contents($fixture . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'events.jsonl', "probe\n");
    }
    mkdir($fixture . DIRECTORY_SEPARATOR . 'unsafe', 0700);
    file_put_contents($fixture . DIRECTORY_SEPARATOR . 'unsafe' . DIRECTORY_SEPARATOR . 'marker', 'probe');
    mkdir($fixture . DIRECTORY_SEPARATOR . 'fallback-temp', 0700);
    $fallbackDirectory = $fixture . DIRECTORY_SEPARATOR . 'fallback-temp' . DIRECTORY_SEPARATOR
        . 'private-conversation-' . (function_exists('posix_geteuid') ? posix_geteuid() : 0) . '-abcdef0123456789';
    mkdir($fallbackDirectory, 0700);
    file_put_contents($fallbackDirectory . DIRECTORY_SEPARATOR . 'events.jsonl', "probe\n");
    file_put_contents($fallbackDirectory . DIRECTORY_SEPARATOR . 'events.lock', '');
    mkdir($fixture . DIRECTORY_SEPARATOR . 'fifo-lock', 0700);
    if (function_exists('posix_mkfifo')) {
        posix_mkfifo($fixture . DIRECTORY_SEPARATOR . 'fifo-lock' . DIRECTORY_SEPARATOR . 'events.lock', 0600);
    }
    echo $fixture . "\n";
    trigger_error('cleanup failure regression probe', E_USER_ERROR);
}

try {
    logCheck(@mkdir($fixture, 0700), 'could not create isolated log directory');
    logCheck(pcv_log_set_test_directory($fixture), 'logger rejected isolated test directory');
    ini_set('error_log', $fallback);
    $cleanupProcess = proc_open([PHP_BINARY, __FILE__, '--cleanup-failure-probe'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $cleanupPipes);
    logCheck(is_resource($cleanupProcess), 'could not start fatal cleanup probe');
    fclose($cleanupPipes[0]);
    $cleanupOutput = stream_get_contents($cleanupPipes[1]);
    $cleanupError = stream_get_contents($cleanupPipes[2]);
    fclose($cleanupPipes[1]);
    fclose($cleanupPipes[2]);
    $cleanupExit = proc_close($cleanupProcess);
    $cleanupProbeDirectory = strtok((string)$cleanupOutput, "\r\n");
    $temporaryDirectory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
    logCheck($cleanupExit !== 0 && is_string($cleanupProbeDirectory)
        && dirname($cleanupProbeDirectory) === $temporaryDirectory
        && preg_match('/^chim-private-conversation-log-check-[a-f0-9]{16}$/D', basename($cleanupProbeDirectory)) === 1,
        'fatal cleanup probe did not report its isolated fixture and fail as expected: ' . $cleanupError);
    logCheck(str_contains((string)$cleanupOutput, 'CLEANUP_PROBE_TERMINAL_WRITTEN'),
        'fixture cleanup must run after the fatal request terminal producer: ' . $cleanupError);
    logCheck(!file_exists($cleanupProbeDirectory) && !is_link($cleanupProbeDirectory)
        && !file_exists($cleanupProbeDirectory . '-fallback.log') && !is_link($cleanupProbeDirectory . '-fallback.log'),
        'shutdown cleanup must remove only known fixture files and directories after a fatal error');
    putenv('PCV_LOG_DEBUG_UNTIL');
    logCheck(!pcv_log_debug_setting_is_active(), 'debug should be off without an expiry setting');
    putenv('PCV_LOG_DEBUG_UNTIL=' . (time() + PCV_LOG_DEBUG_MAX_SECONDS + 1));
    logCheck(!pcv_log_debug_setting_is_active(), 'debug expiry must be limited to one hour');
    putenv('PCV_LOG_DEBUG_UNTIL');

    $configId = pcv_log_new_uuid();
    $playthroughKey = hash('sha256', 'fixture-playthrough');
    logCheck(is_string($configId), 'secure config ID generator should return an ID');
    pcv_log_begin_request($configId);
    pcv_log_set_playthrough_ref($playthroughKey);
    pcv_log_set_correlation([
        'event_id' => 123,
        'utterance_id' => 'utt_abcdefgh12345678',
        'linked_request_id' => 'abcdefabcdefabcdefabcdef',
        'source_request_id' => 'DO NOT LOG UNKNOWN CORRELATION',
        'speech_hash' => 'DO NOT LOG THIS HASH',
    ]);
    pcv_log_event('state.scope_staged', 'info', 'ok', null, [
        'action' => 'enable',
        'scene_mode' => 'solo',
        'actor_a_id' => '101',
        'actor_b_id' => '202',
        'exclude_player' => true,
        'bystander_mode' => 'silent',
        'dialogue' => 'DO NOT LOG THIS SECRET',
    ]);
    $exception = new RuntimeException('DO NOT LOG THIS EXCEPTION MESSAGE', 37);
    pcv_log_exception('state.unavailable', 'error', 'unavailable', 'state_read_failed', $exception, ['operation' => 'read']);
    pcv_log_event('ui.unavailable', 'error', 'unavailable', 'catalog_unavailable', ['operation' => 'catalog']);
    pcv_log_event('ui.scope_stage_failed', 'error', 'failed', 'readback_mismatch', [
        'action' => 'enable',
        'operation' => 'readback',
    ]);
    pcv_log_event('routing.request_detail', 'debug', 'ok', 'debug_detail', [
        'phase' => 'context_pre',
        'decision' => 'context_prepared',
        'request_type' => 'instruction',
        'audience_before_count' => 8,
        'audience_after_count' => 2,
    ]);
    pcv_log_event('state.scope_skipped', 'info', 'skipped', 'scene_not_eligible', [
        'operation' => 'begin',
        'scene_mode' => 'solo',
    ]);
    $reflectionContext = ['phase' => 'registration', 'route' => 'solo_reflection', 'actor_a_id' => '101',
        'utterance_id' => 'DO NOT LOG THIS ID', 'speech_hash' => 'DO NOT LOG THIS DIGEST', 'speech' => 'DO NOT LOG THIS SPEECH'];
    pcv_log_event('reflection.registration_skipped', 'info', 'skipped', 'baseline_stale', $reflectionContext);
    pcv_log_event('reflection.registration_error', 'error', 'failed', 'registry_corrupt', $reflectionContext);
    pcv_log_event('reflection.output_registered', 'info', 'accepted', null, $reflectionContext);
    pcv_log_event('reflection.ack_skipped', 'info', 'skipped', 'evaluation_rejected', array_replace($reflectionContext, ['phase' => 'ack']));
    pcv_log_event('reflection.ack_error', 'error', 'failed', 'database_unavailable', array_replace($reflectionContext, ['phase' => 'ack']));
    pcv_log_event('reflection.evaluation_finished', 'info', 'accepted', null, array_replace($reflectionContext, ['phase' => 'ack']));
    pcv_log_event('reflection.observer_unavailable', 'info', 'unavailable', 'observer_unsupported', array_replace($reflectionContext, ['phase' => 'ack']));
    pcv_log_presence_observed('background_read', 'stale', 0, 'presence_stale');
    pcv_log_presence_observed('background_read', 'unavailable', 0, 'presence_baseline');
    pcv_log_import_mp_record([
        'schema_version' => 1,
        'plugin' => 'mind_poisoning',
        'request_id' => '1234567890abcdef12345678',
        'event' => 'persistence_finished',
        'source_kind' => 'reflection',
        'config_id' => $configId,
        'event_id' => 123,
        'utterance_id' => 'utt_abcdefgh12345678',
        'speaker_id' => '101',
        'speaker_kind' => 'npc',
        'persistence_outcome' => 'committed',
        'persistence_reason' => 'zero-change',
        'commit_state' => 'confirmed',
        'committed' => true,
        'changed_count' => 0,
        'changes' => [],
        'model_reason' => 'DO NOT LOG THIS MODEL DETAIL',
        'speech' => 'DO NOT LOG THIS SPEECH',
    ], 'info');
    pcv_log_import_mp_record([
        'schema_version' => 1,
        'plugin' => 'mind_poisoning',
        'request_id' => 'abcdef1234567890abcdef12',
        'event' => 'persistence_finished',
        'source_kind' => 'reflection',
        'config_id' => $configId,
        'event_id' => 123,
        'utterance_id' => 'utt_abcdefgh12345678',
        'speaker_id' => '101',
        'speaker_kind' => 'npc',
        'persistence_outcome' => 'failed',
        'persistence_reason' => 'commit-failed',
        'commit_state' => 'unconfirmed',
        'committed' => false,
        'changed_count' => 0,
        'changes' => [],
        'reason' => 'commit-failed',
    ], 'error');
    pcv_log_import_mp_record([
        'schema_version' => 1,
        'plugin' => 'mind_poisoning',
        'request_id' => 'fedcba0987654321fedcba09',
        'event' => 'request_finished',
        'source_kind' => 'reflection',
        'config_id' => $configId,
        'event_id' => 123,
        'utterance_id' => 'utt_abcdefgh12345678',
        'outcome' => 'skipped',
        'reason' => 'pause_control_invalid',
    ], 'warning');
    pcv_log_event('routing.request_started', 'info', 'ok', null, ['request_type' => 'inputtext']);
    pcv_log_set_terminal('failed', 'hook_exception', ['phase' => 'prerequest', 'request_type' => 'inputtext']);
    pcv_log_set_terminal('postrequest_observed', null, ['phase' => 'postrequest']);
    pcv_log_shutdown_terminal();
    logCheck(!pcv_log_rule_matches('routing.request_finished', 'info', 'postrequest_observed', 'fatal_error'),
        'A contradictory terminal outcome/reason pair must be rejected by the shared rule.');

    $path = pcv_log_path();
    logCheck(is_string($path) && is_file($path), 'CLI path accessor should return the isolated JSONL file');
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    logCheck(is_array($lines) && count($lines) === 19, 'debug event should be suppressed while ordinary events are retained');
    $entries = array_map(static fn(string $line) => json_decode($line, true, 16, JSON_THROW_ON_ERROR), $lines);
    $manifest = json_decode((string)file_get_contents(dirname(__DIR__) . '/server/manifest.json'), true, 16, JSON_THROW_ON_ERROR);
    foreach ($entries as $entry) {
        logCheck(($entry['schema_version'] ?? null) === PCV_LOG_SCHEMA_VERSION, 'schema version missing');
        logCheck(($entry['logging_revision'] ?? null) === 2, 'new records must identify the instrumentation revision');
        logCheck(($entry['plugin_version'] ?? null) === ($manifest['version'] ?? null), 'plugin version should come from manifest');
        logCheck(is_string($entry['request_id'] ?? null) && $entry['request_id'] === pcv_log_request_id(), 'request ID should be stable');
        logCheck(($entry['config_id'] ?? null) === $configId, 'config ID should correlate the request');
        logCheck(($entry['playthrough_ref'] ?? null) === substr(hash('sha256', $playthroughKey), 0, 16), 'playthrough key should be pseudonymized');
        logCheck(is_int($entry['elapsed_ms'] ?? null), 'monotonic elapsed time missing');
        logCheck(preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/D', $entry['timestamp'] ?? '') === 1,
            'timestamp must be UTC with millisecond precision');
    }
    logCheck(!str_contains((string)file_get_contents($path), 'DO NOT LOG THIS'), 'raw exception/dialogue text leaked');
    logCheck(!str_contains((string)file_get_contents($path), 'DO NOT LOG UNKNOWN CORRELATION')
        && !str_contains((string)file_get_contents($path), 'DO NOT LOG THIS HASH'), 'unknown correlation fields must be omitted');
    logCheck(!str_contains((string)file_get_contents($path), $playthroughKey), 'full playthrough key leaked');
    logCheck(($entries[0]['context']['actor_a_id'] ?? null) === '101', 'approved actor IDs should be retained');
    logCheck(($entries[0]['context']['scene_mode'] ?? null) === 'solo', 'scene mode should be retained as a fixed enum');
    logCheck(!array_key_exists('dialogue', $entries[0]['context']), 'unapproved context must be omitted');
    logCheck(($entries[0]['context']['correlation'] ?? null) === [
        'event_id' => '123',
        'utterance_id' => 'utt_abcdefgh12345678',
        'linked_request_id' => 'abcdefabcdefabcdefabcdef',
    ], 'only validated correlation fields should be retained');
    logCheck(($entries[1]['context']['exception_class'] ?? null) === RuntimeException::class, 'exception class should be structured');
    logCheck(($entries[1]['context']['exception_code'] ?? null) === 37, 'exception code should be structured');
    logCheck(preg_match('/^[A-Za-z0-9_.-]+$/D', $entries[1]['context']['source_file'] ?? '') === 1, 'exception path should be reduced to basename');
    logCheck(($entries[2]['reason'] ?? null) === 'catalog_unavailable'
        && ($entries[2]['context']['operation'] ?? null) === 'catalog', 'UI unavailable event should retain fixed failure fields');
    logCheck(($entries[3]['reason'] ?? null) === 'readback_mismatch'
        && ($entries[3]['context']['operation'] ?? null) === 'readback', 'UI stage-failure event should retain fixed failure fields');
    logCheck(($entries[4]['event'] ?? null) === 'state.scope_skipped'
        && ($entries[4]['severity'] ?? null) === 'info'
        && ($entries[4]['reason'] ?? null) === 'scene_not_eligible'
        && ($entries[4]['context']['scene_mode'] ?? null) === 'solo',
        'expected actor ineligibility should be logged as a fixed informational skip');
    $reflectionEntries = array_values(array_filter($entries, static fn(array $entry): bool => str_starts_with((string)($entry['event'] ?? ''), 'reflection.')));
    logCheck(count($reflectionEntries) === 10
        && ($reflectionEntries[0]['reason'] ?? null) === 'baseline_stale'
        && ($reflectionEntries[1]['severity'] ?? null) === 'error'
        && ($reflectionEntries[2]['outcome'] ?? null) === 'accepted'
        && ($reflectionEntries[3]['context']['phase'] ?? null) === 'ack'
        && ($reflectionEntries[4]['reason'] ?? null) === 'database_unavailable'
        && ($reflectionEntries[5]['event'] ?? null) === 'reflection.evaluation_finished'
        && ($reflectionEntries[6]['event'] ?? null) === 'reflection.observer_unavailable'
        && ($reflectionEntries[6]['reason'] ?? null) === 'observer_unsupported',
        'reflection log entries must use the fixed skip/error/accepted contracts');
    logCheck(!str_contains((string)file_get_contents($path), 'DO NOT LOG THIS ID')
        && !str_contains((string)file_get_contents($path), 'DO NOT LOG THIS DIGEST')
        && !str_contains((string)file_get_contents($path), 'DO NOT LOG THIS SPEECH'),
        'reflection log context leaked an output ID, digest, or speech');
    $presenceEntry = $entries[12] ?? [];
    logCheck(($presenceEntry['event'] ?? null) === 'state.presence_observed'
        && ($presenceEntry['severity'] ?? null) === 'info'
        && ($presenceEntry['reason'] ?? null) === 'presence_stale', 'expected stale presence should not be logged as an error');
    $baselinePresence = $entries[13] ?? [];
    logCheck(($baselinePresence['event'] ?? null) === 'state.presence_observed'
        && ($baselinePresence['outcome'] ?? null) === 'unavailable'
        && ($baselinePresence['severity'] ?? null) === 'info'
        && ($baselinePresence['reason'] ?? null) === 'presence_baseline',
        'An initial ordering baseline must remain distinct from both empty and age-stale presence.');
    $imported = $entries[14] ?? [];
    logCheck(($imported['event'] ?? null) === 'reflection.persistence_finished'
        && ($imported['outcome'] ?? null) === 'zero_change'
        && ($imported['context']['commit_state'] ?? null) === 'confirmed'
        && ($imported['context']['changed_count'] ?? null) === 0
        && ($imported['context']['source_reason'] ?? null) === 'zero-change'
        && ($imported['context']['correlation']['linked_request_id'] ?? null) === '1234567890abcdef12345678',
        'MP persistence summaries must preserve safe outcome and correlation fields');
    logCheck(($entries[15]['event'] ?? null) === 'reflection.persistence_finished'
        && ($entries[15]['outcome'] ?? null) === 'unconfirmed'
        && ($entries[15]['severity'] ?? null) === 'error'
        && ($entries[15]['reason'] ?? null) === 'commit_unconfirmed'
        && ($entries[15]['context']['commit_state'] ?? null) === 'unconfirmed'
        && ($entries[15]['context']['source_reason'] ?? null) === 'commit-failed',
        'StoreDb error-level uncertain commits must retain failure severity and the fixed source reason');
    logCheck(($entries[16]['event'] ?? null) === 'reflection.evaluation_result'
        && ($entries[16]['outcome'] ?? null) === 'rejected'
        && ($entries[16]['severity'] ?? null) === 'warning'
        && ($entries[16]['context']['source_reason'] ?? null) === 'pause_control_invalid',
        'Warning-level invalid pause controls must not disappear into an informational skip');
    logCheck(!str_contains((string)file_get_contents($path), 'DO NOT LOG THIS MODEL DETAIL')
        && !str_contains((string)file_get_contents($path), 'DO NOT LOG THIS SPEECH'), 'MP import leaked model detail or dialogue');
    logCheck(($entries[18]['event'] ?? null) === 'routing.request_finished'
        && ($entries[18]['outcome'] ?? null) === 'failed'
        && ($entries[18]['reason'] ?? null) === 'hook_exception',
        'A later postrequest observation must not overwrite an earlier caught failure');

    pcv_log_set_correlation([]);
    $beforeFirstImport = count(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    pcv_log_import_mp_record([
        'schema_version' => 1,
        'plugin' => 'mind_poisoning',
        'request_id' => '1234567890abcdef12345678',
        'event' => 'request_finished',
        'source_kind' => 'reflection',
        'config_id' => $configId,
        'event_id' => 125,
        'utterance_id' => 'utt_mnopqrst12345678',
        'outcome' => 'skipped',
        'reason' => 'pause_control_invalid',
    ], 'info');
    $firstCorrelation = pcv_log_request_context()['correlation'];
    $afterFirstImport = count(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    logCheck(($firstCorrelation['event_id'] ?? null) === '125'
        && ($firstCorrelation['utterance_id'] ?? null) === 'utt_mnopqrst12345678'
        && $afterFirstImport === $beforeFirstImport + 1,
        'A first valid observer tuple must still be accepted when no correlation is established.');

    $registeredTuple = ['event_id' => '123', 'utterance_id' => 'utt_abcdefgh12345678'];
    $beforeMismatchedImports = count(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    pcv_log_set_correlation($registeredTuple);
    pcv_log_import_mp_record([
        'schema_version' => 1,
        'plugin' => 'mind_poisoning',
        'request_id' => '1234567890abcdef12345678',
        'event' => 'request_finished',
        'source_kind' => 'reflection',
        'config_id' => $configId,
        'event_id' => 124,
        'utterance_id' => $registeredTuple['utterance_id'],
        'outcome' => 'skipped',
        'reason' => 'pause_control_invalid',
    ], 'info');
    $eventMismatchStayed = pcv_log_request_context()['correlation'] === $registeredTuple;
    pcv_log_set_correlation($registeredTuple);
    pcv_log_import_mp_record([
        'schema_version' => 1,
        'plugin' => 'mind_poisoning',
        'request_id' => '1234567890abcdef12345678',
        'event' => 'request_finished',
        'source_kind' => 'reflection',
        'config_id' => $configId,
        'event_id' => 123,
        'utterance_id' => 'utt_ijklmnop12345678',
        'outcome' => 'skipped',
        'reason' => 'pause_control_invalid',
    ], 'info');
    $utteranceMismatchStayed = pcv_log_request_context()['correlation'] === $registeredTuple;
    $afterMismatchedImports = count(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    logCheck($eventMismatchStayed && $utteranceMismatchStayed && $afterMismatchedImports === $beforeMismatchedImports,
        'MP observer rows with a different event or utterance ID must not relabel or add to the established ACK tuple');

    $permissions = fileperms($path) & 0777;
    logCheck($permissions === 0600, 'log file should be owner-only');
    $uid = function_exists('posix_geteuid') ? posix_geteuid() : null;
    logCheck($uid !== null && fileowner($path) === $uid, 'log file should belong to the effective server user');

    foreach ([1, 2, 3, 4] as $index) {
        $rotation = $fixture . '/events.' . $index . '.jsonl';
        file_put_contents($rotation, 'old rotation ' . $index . "\n");
        chmod($rotation, 0600);
    }
    file_put_contents($path, str_repeat('x', PCV_LOG_MAX_FILE_BYTES));
    chmod($path, 0600);
    pcv_log_event('ui.page_open', 'info', 'ok');
    logCheck(is_file($fixture . '/events.1.jsonl'), 'full log should rotate before append');
    logCheck(filesize($fixture . '/events.1.jsonl') <= PCV_LOG_MAX_FILE_BYTES, 'rotated log exceeded size limit');
    logCheck(filesize($path) <= PCV_LOG_MAX_ENTRY_BYTES, 'active entry exceeded size limit');
    logCheck(!file_exists($fixture . '/events.5.jsonl'), 'rotation should retain only four old files');
    $segments = glob($fixture . '/events*.jsonl');
    logCheck(is_array($segments) && count($segments) <= PCV_LOG_MAX_FILES, 'rotation must cap JSONL segments at five');

    $debugDirectory = $fixture . '/debug';
    mkdir($debugDirectory, 0700);
    $debugProcess = proc_open([PHP_BINARY, __FILE__, '--debug-child', $debugDirectory], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $debugPipes);
    logCheck(is_resource($debugProcess), 'could not start isolated debug fixture process');
    fclose($debugPipes[0]);
    stream_get_contents($debugPipes[1]);
    stream_get_contents($debugPipes[2]);
    fclose($debugPipes[1]);
    fclose($debugPipes[2]);
    logCheck(proc_close($debugProcess) === 0, 'debug fixture process failed');
    $debugLines = file($debugDirectory . '/events.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $debugEntry = is_array($debugLines) ? json_decode($debugLines[0] ?? '', true, 16, JSON_THROW_ON_ERROR) : null;
    logCheck(($debugEntry['severity'] ?? null) === 'debug', 'unexpired server debug setting should admit debug event');
    foreach (['events.jsonl', 'events.1.jsonl', 'events.2.jsonl', 'events.3.jsonl', 'events.4.jsonl', 'events.lock'] as $name) {
        @unlink($debugDirectory . '/' . $name);
    }
    rmdir($debugDirectory);

    $concurrentDirectory = $fixture . '/concurrent';
    mkdir($concurrentDirectory, 0700);
    $writers = [];
    for ($index = 0; $index < 3; $index++) {
        $process = proc_open([PHP_BINARY, __FILE__, '--writer-child', $concurrentDirectory, '30'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        logCheck(is_resource($process), 'could not start concurrent writer');
        fclose($pipes[0]);
        $writers[] = ['process' => $process, 'stdout' => $pipes[1], 'stderr' => $pipes[2]];
    }
    foreach ($writers as $writer) {
        stream_get_contents($writer['stdout']);
        stream_get_contents($writer['stderr']);
        fclose($writer['stdout']);
        fclose($writer['stderr']);
        logCheck(proc_close($writer['process']) === 0, 'concurrent writer failed');
    }
    $concurrentLines = file($concurrentDirectory . '/events.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    logCheck(is_array($concurrentLines) && count($concurrentLines) > 0, 'concurrent writers produced no records');
    foreach ($concurrentLines as $line) {
        $concurrentEntry = json_decode($line, true, 16, JSON_THROW_ON_ERROR);
        logCheck(($concurrentEntry['event'] ?? null) === 'ui.page_open', 'concurrent write produced malformed JSONL');
    }
    foreach (['events.jsonl', 'events.1.jsonl', 'events.2.jsonl', 'events.3.jsonl', 'events.4.jsonl', 'events.lock'] as $name) {
        @unlink($concurrentDirectory . '/' . $name);
    }
    rmdir($concurrentDirectory);

    $unsafeDirectory = $fixture . '/unsafe';
    mkdir($unsafeDirectory, 0700);
    $marker = $unsafeDirectory . '/marker';
    file_put_contents($marker, 'keep');
    chmod($unsafeDirectory, 0755);
    ob_start();
    $acceptedUnsafeDirectory = pcv_log_set_test_directory($unsafeDirectory);
    $output = ob_get_clean();
    logCheck(!$acceptedUnsafeDirectory && $output === '', 'unsafe directory must be rejected without response output');
    logCheck((fileperms($unsafeDirectory) & 0777) === 0755 && file_get_contents($marker) === 'keep',
        'rejected directory must remain untouched');
    chmod($unsafeDirectory, 0700);
    unlink($marker);
    chmod($unsafeDirectory, 0755);
    $healthProcess = proc_open([PHP_BINARY, __FILE__, '--storage-health-child', $unsafeDirectory],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $healthPipes);
    logCheck(is_resource($healthProcess), 'could not start storage health fixture');
    fclose($healthPipes[0]);
    $healthOutput = stream_get_contents($healthPipes[1]);
    $healthError = stream_get_contents($healthPipes[2]);
    fclose($healthPipes[1]);
    fclose($healthPipes[2]);
    logCheck(proc_close($healthProcess) === 0, 'storage health fixture failed: ' . $healthError);
    $health = json_decode((string)$healthOutput, true, 16, JSON_THROW_ON_ERROR);
    logCheck(($health['mode'] ?? null) === 'temporary_fallback'
        && ($health['reason'] ?? null) === 'override_invalid'
        && !array_key_exists('path', $health), 'unsafe storage override must be visible without disclosing the fallback path');
    $fallbackTemp = $fixture . '/fallback-temp';
    mkdir($fallbackTemp, 0700);
    $failureProcess = proc_open([PHP_BINARY, __FILE__, '--storage-failure-child', $unsafeDirectory],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $failurePipes, null,
        array_replace($_ENV, ['TMPDIR' => $fallbackTemp]));
    logCheck(is_resource($failureProcess), 'could not start sequential storage failure fixture');
    fclose($failurePipes[0]);
    $failureOutput = stream_get_contents($failurePipes[1]);
    $failureError = stream_get_contents($failurePipes[2]);
    fclose($failurePipes[1]);
    fclose($failurePipes[2]);
    logCheck(proc_close($failureProcess) === 0, 'sequential storage failure fixture failed: ' . $failureError);
    $failureHealth = json_decode((string)$failureOutput, true, 16, JSON_THROW_ON_ERROR);
    logCheck(($failureHealth['write_status'] ?? null) === 'degraded'
        && in_array('override_invalid', $failureHealth['failure_codes'] ?? [], true)
        && in_array('lock_unavailable', $failureHealth['failure_codes'] ?? [], true),
        'A successful fallback append must not erase the invalid-override or later lock-failure health');

    $fifoLockDirectory = $fixture . '/fifo-lock';
    mkdir($fifoLockDirectory, 0700);
    logCheck(function_exists('posix_mkfifo') && posix_mkfifo($fifoLockDirectory . '/events.lock', 0600),
        'could not create isolated FIFO lock fixture');
    $fifoProcess = proc_open([PHP_BINARY, __FILE__, '--fifo-lock-child', $fifoLockDirectory],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $fifoPipes);
    logCheck(is_resource($fifoProcess), 'could not start FIFO lock fixture');
    fclose($fifoPipes[0]);
    stream_set_blocking($fifoPipes[1], false);
    stream_set_blocking($fifoPipes[2], false);
    $fifoOutput = '';
    $fifoError = '';
    $fifoDeadline = microtime(true) + 2.0;
    $fifoFinished = false;
    do {
        $fifoOutput .= stream_get_contents($fifoPipes[1]);
        $fifoError .= stream_get_contents($fifoPipes[2]);
        $fifoStatus = proc_get_status($fifoProcess);
        if (!$fifoStatus['running']) {
            $fifoFinished = true;
            break;
        }
        usleep(20000);
    } while (microtime(true) < $fifoDeadline);
    if (!$fifoFinished) {
        proc_terminate($fifoProcess, 9);
    }
    fclose($fifoPipes[1]);
    fclose($fifoPipes[2]);
    $fifoExit = proc_close($fifoProcess);
    if ($fifoExit === -1 && isset($fifoStatus['exitcode'])) {
        $fifoExit = $fifoStatus['exitcode'];
    }
    logCheck($fifoFinished && $fifoExit === 0,
        'a FIFO lock path must be rejected promptly instead of blocking the request: ' . $fifoError);
    $fifoResult = json_decode($fifoOutput, true, 16, JSON_THROW_ON_ERROR);
    logCheck(($fifoResult['write_status'] ?? null) === 'degraded'
        && in_array('lock_unavailable', $fifoResult['failure_codes'] ?? [], true)
        && ($fifoResult['active_log_exists'] ?? null) === false,
        'a rejected FIFO lock must set lock health without creating a JSONL segment');
    logCheck(!str_contains($fifoOutput . $fifoError, 'DO NOT LOG'),
        'FIFO lock rejection must not emit private fixture details');

    $startLockChild = static function (string $directory, string $childFallback, int $count): array {
        $process = proc_open([PHP_BINARY, __FILE__, '--lock-contention-child', $directory, $childFallback, (string)$count],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        logCheck(is_resource($process), 'could not start lock contention fixture');
        $ready = fgets($pipes[1]);
        logCheck($ready === "READY\n", 'lock contention child did not reach its synchronization point');
        fwrite($pipes[0], "go\n");
        fflush($pipes[0]);
        fclose($pipes[0]);
        $attempt = fgets($pipes[1]);
        logCheck($attempt === "ATTEMPT\n", 'lock contention child did not start its writes');
        return ['process' => $process, 'stdout' => $pipes[1], 'stderr' => $pipes[2]];
    };
    $finishLockChild = static function (array $child, float $timeoutSeconds): array {
        stream_set_blocking($child['stdout'], false);
        stream_set_blocking($child['stderr'], false);
        $output = '';
        $error = '';
        $deadline = microtime(true) + $timeoutSeconds;
        $finished = false;
        do {
            $output .= stream_get_contents($child['stdout']);
            $error .= stream_get_contents($child['stderr']);
            $status = proc_get_status($child['process']);
            if (!$status['running']) {
                $finished = true;
                break;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        if (!$finished) {
            proc_terminate($child['process'], 9);
        }
        $output .= stream_get_contents($child['stdout']);
        $error .= stream_get_contents($child['stderr']);
        fclose($child['stdout']);
        fclose($child['stderr']);
        $exit = proc_close($child['process']);
        if ($exit === -1 && isset($status['exitcode'])) {
            $exit = $status['exitcode'];
        }
        return ['finished' => $finished, 'exit' => $exit, 'output' => $output, 'error' => $error];
    };

    $retryDirectory = $fixture . '/lock-retry';
    mkdir($retryDirectory, 0700);
    $retryLock = fopen($retryDirectory . '/events.lock', 'c');
    chmod($retryDirectory . '/events.lock', 0600);
    logCheck(is_resource($retryLock) && flock($retryLock, LOCK_EX | LOCK_NB), 'could not hold short-contention lock');
    $retryChild = $startLockChild($retryDirectory, $fixture . '/lock-retry.log', 1);
    usleep(25000);
    $retryStillWaiting = proc_get_status($retryChild['process'])['running'];
    usleep(15000);
    flock($retryLock, LOCK_UN);
    fclose($retryLock);
    $retryResult = $finishLockChild($retryChild, 2.0);
    logCheck($retryStillWaiting && $retryResult['finished'] && $retryResult['exit'] === 0,
        'short lock contention should wait for the lock and finish within the bounded window: waiting='
            . var_export($retryStillWaiting, true) . ' finished=' . var_export($retryResult['finished'], true)
            . ' exit=' . $retryResult['exit'] . ' output=' . $retryResult['output'] . ' error=' . $retryResult['error']);
    $retryLines = is_file($retryDirectory . '/events.jsonl')
        ? file($retryDirectory . '/events.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    $retryRows = array_map(static fn(string $line): array => json_decode($line, true, 16, JSON_THROW_ON_ERROR), $retryLines);
    logCheck(count($retryRows) === 1 && ($retryRows[0]['event'] ?? null) === 'state.scope_staged'
        && ($retryRows[0]['context']['action'] ?? null) === 'enable'
        && !array_key_exists('dialogue', $retryRows[0]['context']),
        'brief contention should append exactly one sanitized scope event');
    logCheck(!is_file($fixture . '/lock-retry.log') || filesize($fixture . '/lock-retry.log') === 0,
        'recovered short contention should not use the PHP error-log fallback');

    $timeoutDirectory = $fixture . '/lock-timeout';
    mkdir($timeoutDirectory, 0700);
    $timeoutLock = fopen($timeoutDirectory . '/events.lock', 'c');
    chmod($timeoutDirectory . '/events.lock', 0600);
    logCheck(is_resource($timeoutLock) && flock($timeoutLock, LOCK_EX | LOCK_NB), 'could not hold persistent-contention lock');
    $timeoutChild = $startLockChild($timeoutDirectory, $fixture . '/lock-timeout.log', 8);
    $timeoutResult = $finishLockChild($timeoutChild, 2.0);
    logCheck($timeoutResult['finished'] && $timeoutResult['exit'] === 0,
        'persistent lock contention should return without blocking indefinitely: ' . $timeoutResult['error']);
    $timeoutSummary = json_decode($timeoutResult['output'], true, 16, JSON_THROW_ON_ERROR);
    logCheck(($timeoutSummary['health']['write_status'] ?? null) === 'degraded'
        && in_array('lock_unavailable', $timeoutSummary['health']['failure_codes'] ?? [], true),
        'persistent contention must expose degraded lock health');
    logCheck(is_float($timeoutSummary['elapsed_ms'] ?? null) || is_int($timeoutSummary['elapsed_ms'] ?? null),
        'persistent contention child must report its total write latency');
    logCheck($timeoutSummary['elapsed_ms'] >= 50 && $timeoutSummary['elapsed_ms'] < 500,
        'a burst should consume one bounded request wait budget, not one budget per event');
    $timeoutText = is_file($fixture . '/lock-timeout.log') ? (string)file_get_contents($fixture . '/lock-timeout.log') : '';
    preg_match_all('/Private Conversation event fallback: (\{[^\r\n]*\})/', $timeoutText, $fallbackMatches);
    logCheck(count($fallbackMatches[1] ?? []) === 8,
        'every already-sanitized bounded event should reach the PHP error-log fallback');
    foreach ($fallbackMatches[1] as $fallbackLine) {
        $fallbackEntry = json_decode($fallbackLine, true, 16, JSON_THROW_ON_ERROR);
        logCheck(($fallbackEntry['event'] ?? null) === 'state.scope_staged'
            && ($fallbackEntry['outcome'] ?? null) === 'ok'
            && ($fallbackEntry['context']['action'] ?? null) === 'enable'
            && ($fallbackEntry['context']['scene_mode'] ?? null) === 'solo'
            && ($fallbackEntry['context']['actor_a_id'] ?? null) === '101'
            && !array_key_exists('dialogue', $fallbackEntry['context']),
            'fallback should preserve the full sanitized event record');
    }
    logCheck(!str_contains($timeoutText, 'PRIVATE-DIALOGUE-SHOULD-NEVER-LEAVE-THIS-CHILD')
        && !str_contains($timeoutText, 'PRIVATE-PROMPT-SHOULD-NEVER-LEAVE-THIS-CHILD')
        && !str_contains($timeoutText, 'PRIVATE-CLAIM-TOKEN-SHOULD-NEVER-LEAVE-THIS-CHILD')
        && !str_contains($timeoutResult['output'] . $timeoutResult['error'], 'PRIVATE-DIALOGUE-SHOULD-NEVER-LEAVE-THIS-CHILD')
        && !str_contains($timeoutResult['output'] . $timeoutResult['error'], 'PRIVATE-PROMPT-SHOULD-NEVER-LEAVE-THIS-CHILD')
        && !str_contains($timeoutResult['output'] . $timeoutResult['error'], 'PRIVATE-CLAIM-TOKEN-SHOULD-NEVER-LEAVE-THIS-CHILD'),
        'contention fallback must not expose the private fixture');

    $failedSinkChild = $startLockChild($timeoutDirectory, $fixture . '/missing-fallback/error.log', 1);
    $failedSinkResult = $finishLockChild($failedSinkChild, 2.0);
    flock($timeoutLock, LOCK_UN);
    fclose($timeoutLock);
    $failedSinkSummary = json_decode($failedSinkResult['output'], true, 16, JSON_THROW_ON_ERROR);
    logCheck($failedSinkResult['finished'] && $failedSinkResult['exit'] === 0
        && ($failedSinkSummary['health']['write_status'] ?? null) === 'degraded'
        && in_array('lock_unavailable', $failedSinkSummary['health']['failure_codes'] ?? [], true)
        && !str_contains($failedSinkResult['output'] . $failedSinkResult['error'], 'PRIVATE-DIALOGUE-SHOULD-NEVER-LEAVE-THIS-CHILD'),
        'a failed PHP error-log sink must not escape the logger or expose the fixture');
    logCheck(!file_exists($fixture . '/missing-fallback') && !is_link($fixture . '/missing-fallback'),
        'fallback must not create its own directory when the configured PHP error-log path is unavailable');

    foreach (glob($fallbackTemp . '/private-conversation-*') ?: [] as $fallbackDirectory) {
        foreach (glob($fallbackDirectory . '/*') ?: [] as $fallbackFile) {
            @unlink($fallbackFile);
        }
        @rmdir($fallbackDirectory);
    }
    @rmdir($fallbackTemp);
    chmod($unsafeDirectory, 0700);
    rmdir($unsafeDirectory);

    $lockPath = $fixture . '/events.lock';
    $lock = fopen($lockPath, 'c');
    logCheck(is_resource($lock) && flock($lock, LOCK_EX | LOCK_NB), 'could not hold fixture lock');
    pcv_log_event('ui.page_open', 'info', 'ok');
    pcv_log_event('ui.page_open', 'info', 'ok');
    flock($lock, LOCK_UN);
    fclose($lock);
    $fallbackLines = is_file($fallback) ? file($fallback, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    $genericFallbacks = array_filter($fallbackLines ?: [], static fn(string $line): bool => str_contains($line, 'Private Conversation logger: lock_unavailable'));
    preg_match_all('/Private Conversation event fallback: (\{[^\r\n]*\})/', (string)file_get_contents($fallback), $eventFallbacks);
    logCheck(count($genericFallbacks) === 1 && count($eventFallbacks[1] ?? []) === 2,
        'lock contention should keep one generic warning and preserve each bounded event line');
    foreach ($eventFallbacks[1] as $eventFallback) {
        $fallbackEntry = json_decode($eventFallback, true, 16, JSON_THROW_ON_ERROR);
        logCheck(($fallbackEntry['event'] ?? null) === 'ui.page_open'
            && ($fallbackEntry['outcome'] ?? null) === 'ok',
            'generic lock fallback should retain full sanitized event records');
    }
    $fallbackText = (string)file_get_contents($fallback);
    logCheck(str_contains($fallbackText, 'request_id=' . pcv_log_request_id()), 'fallback should identify the request');
    logCheck(str_contains($fallbackText, 'config_id=' . $configId), 'fallback should identify the active config when valid');
    logCheck(!str_contains($fallbackText, 'DO NOT LOG THIS'), 'fallback should not contain raw exception text');

    pcv_log_event('state.scope_ended', 'info', 'ok', null, [
        'action' => 'end',
        'exclude_player' => true,
        'bystander_mode' => 'exclude',
        'dialogue' => 'DO NOT LOG THIS END SECRET',
    ]);
    $scopeEndRows = [];
    foreach (glob($fixture . '/events*.jsonl') ?: [] as $segment) {
        foreach (file($segment, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $entry = json_decode($line, true);
            if (is_array($entry) && ($entry['event'] ?? null) === 'state.scope_ended') {
                $scopeEndRows[] = $entry;
            }
        }
    }
    logCheck(count($scopeEndRows) === 1 && ($scopeEndRows[0]['outcome'] ?? null) === 'ok'
        && ($scopeEndRows[0]['context']['action'] ?? null) === 'end'
        && !array_key_exists('dialogue', $scopeEndRows[0]['context']),
        'scope end should have its own successful event with only allowlisted context');

    $normalShutdown = $fixture . '/normal-shutdown';
    $fatalShutdown = $fixture . '/fatal-shutdown';
    mkdir($normalShutdown, 0700);
    mkdir($fatalShutdown, 0700);
    foreach ([['--shutdown-child', $normalShutdown], ['--fatal-child', $fatalShutdown]] as [$mode, $directory]) {
        $process = proc_open([PHP_BINARY, __FILE__, $mode, $directory],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        logCheck(is_resource($process), 'could not start isolated shutdown fixture');
        fclose($pipes[0]);
        $childOutput = stream_get_contents($pipes[1]);
        $childError = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $childStatus = proc_close($process);
        if ($mode === '--shutdown-child') {
            logCheck($childStatus === 0, 'normal shutdown child failed: ' . $childError);
            $childRows = array_map(static fn(string $line) => json_decode($line, true, 16, JSON_THROW_ON_ERROR),
                file($directory . '/events.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
            $terminalRows = array_values(array_filter($childRows, static fn(array $row): bool => ($row['event'] ?? null) === 'routing.request_finished'));
            logCheck(count($terminalRows) === 1 && ($terminalRows[0]['outcome'] ?? null) === 'postrequest_observed',
                'Registered shutdown should emit one observed terminal without manual invocation.');
        } else {
            logCheck($childStatus !== 0, 'fatal shutdown child unexpectedly returned successfully');
            $fatalPath = $directory . '/events.jsonl';
            $fatalLines = file($fatalPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $fatalRows = array_map(static fn(string $line) => json_decode($line, true, 16, JSON_THROW_ON_ERROR), $fatalLines);
            $fatalTerminals = array_values(array_filter($fatalRows, static fn(array $row): bool => ($row['event'] ?? null) === 'routing.request_finished'));
            logCheck(count($fatalTerminals) === 1 && ($fatalTerminals[0]['outcome'] ?? null) === 'failed'
                && ($fatalTerminals[0]['reason'] ?? null) === 'fatal_error'
                && ($fatalTerminals[0]['context']['source_file'] ?? null) === 'log_check.php'
                && ($fatalTerminals[0]['context']['exception_code'] ?? null) === E_USER_ERROR
                && is_int($fatalTerminals[0]['context']['source_line'] ?? null)
                && !str_contains((string)file_get_contents($fatalPath), 'DO NOT LOG THIS FATAL MESSAGE'),
                'Fatal shutdown should record fixed basename/type/line metadata without the error message.');
        }
        foreach (['events.jsonl', 'events.1.jsonl', 'events.2.jsonl', 'events.3.jsonl', 'events.4.jsonl', 'events.lock'] as $name) {
            @unlink($directory . '/' . $name);
        }
        @rmdir($directory);
    }

    $lockLatency = number_format((float)$timeoutSummary['elapsed_ms'], 2, '.', '');
    echo "PASS: schema/redaction, IDs, debug window, five-segment rotation, private permissions, concurrent JSONL, unsafe-path rejection, bounded lock retry/fallback; eight-event contention elapsed {$lockLatency} ms\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    if (is_string($oldErrorLog)) {
        ini_set('error_log', $oldErrorLog);
    }
    if (is_string($fifoLockDirectory)) {
        @unlink($fifoLockDirectory . '/events.lock');
        @unlink($fifoLockDirectory . '/events.jsonl');
        @rmdir($fifoLockDirectory);
    }
    if (!cleanLogFixture($fixture, $fallback)) {
        fwrite(STDERR, "FAIL: isolated log fixture cleanup was incomplete; unknown or unsafe entries were preserved.\n");
        $exitCode = 1;
    }
}

exit($exitCode);
