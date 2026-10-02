<?php
declare(strict_types=1);

define('PCV_LOG_TESTING', true);
define('CHIM_MIND_POISONING_TEST_FIXTURES_ONLY', true);
$mindPoisoningRoot = null;
foreach ([dirname(__DIR__, 2) . '/CHIM-MindPoisoning', dirname(__DIR__, 3)] as $candidate) {
    if (is_file($candidate . '/tests/runtime_test.php') && is_file($candidate . '/server/reflection.php')) {
        $mindPoisoningRoot = $candidate;
        break;
    }
}
if ($mindPoisoningRoot === null) {
    throw new RuntimeException('Mind Poisoning reflection test fixtures were not found.');
}
require_once $mindPoisoningRoot . '/tests/runtime_test.php';
require_once $mindPoisoningRoot . '/server/reflection.php';
require_once __DIR__ . '/../server/reflection.php';

function reflectionScopeFixture(array $changes = []): array
{
    return array_replace_recursive([
        'status' => 'active', 'pcv_key' => str_repeat('a', 64),
        'config_id' => '123e4567-e89b-42d3-a456-426614174000', 'actor_a_id' => '11',
        'scope' => ['scene_mode' => 'solo', 'actor_a' => 'Aela', 'actor_b' => null, 'exclude_player' => true],
    ], $changes);
}

function reflectionRequestScope(string $baselineOutput): array
{
    return [
        'status' => 'active', 'config_id' => '123e4567-e89b-42d3-a456-426614174000', 'actor_a_id' => '11',
        'scope' => ['scene_mode' => 'solo', 'actor_a' => 'Aela', 'actor_b' => null, 'exclude_player' => true],
        'origin_request_type' => 'inputtext', 'origin_mode' => 'STANDARD', 'origin_dialogue' => 'What do you think?',
        'route' => 'solo_reflection', 'baseline_utterance_id' => 'utt_baseline12345678',
        'baseline_output_log' => $baselineOutput,
    ];
}

function reflectionWire(string $subtitle, string $id, string $speaker = 'Aela', string $atomic = 'explicit_disable_rechat', string $rechat = 'explicit_disable_rechat'): string
{
    return $speaker . '|ScriptQueue|' . $subtitle . '/neutral/' . $atomic . '/none/phonetic/1/' . $rechat . '/' . $id . "\r\n";
}

function reflectionSetInteraction(int $requestGeneration = 1, int $currentGeneration = 1, bool $enabled = true): void
{
    resetInteractionTestRequest($requestGeneration, $currentGeneration, $enabled, false);
}

function reflectionEnsureActiveState(string $directory, array $scope): void
{
    $path = $directory . DIRECTORY_SEPARATOR . 'state.json';
    if (is_file($path) && !is_link($path)) {
        return;
    }
    $handle = pcv_lock_state($directory, true, LOCK_EX);
    check(is_resource($handle), 'Lock the current active-state fixture.');
    try {
        if (!is_file($path)) {
            $now = time();
            $state = pcv_empty_store($scope['pcv_key']);
            $state['active'] = [
                'config' => [
                    'enabled' => true,
                    'scene_mode' => $scope['scope']['scene_mode'] ?? 'solo',
                    'actor_a' => (string)$scope['actor_a_id'], // production stores the catalog ID
                    'actor_b' => $scope['scope']['actor_b'] ?? null,
                    'exclude_player' => $scope['scope']['exclude_player'] ?? true,
                    'bystander_mode' => 'exclude',
                ],
                'config_id' => $scope['config_id'],
                'activated_at' => $now,
                'expires_at' => $now + PCV_ACTIVE_TTL,
            ];
            pcv_write_store($directory, $state);
        }
    } finally {
        pcv_unlock_state($handle);
    }
}

function reflectionStoreAckReceipt(array $tuple, array $scope, int $generation, string $directory): array
{
    reflectionEnsureActiveState($directory, $scope);
    return pcv_reflection_store_ack_receipt($tuple, $scope, $generation, $directory);
}

function reflectionStore(string $id, string $delivery = 'emitted', string $source = 'Aela: I met the steward at the gate. (Talking to explicit_disable_rechat)'): MemoryStoreDb
{
    [, , , $store] = baseFixture();
    $store->events[200] = ['event_id' => 200, 'utterance_id' => $id, 'delivery_state' => $delivery, 'source_data' => $source, 'gamets' => 30.0];
    return $store;
}

function reflectionRegister(
    MemoryStoreDb $store,
    string $directory,
    string $wire,
    ?string $baseline = null,
    ?callable $nativeAckReader = null,
    ?callable $requestModel = null,
    int $sourceGeneration = 1,
    string $utteranceId = 'utt_1234567890abcdef'
): string
{
    reflectionSetInteraction($sourceGeneration, $sourceGeneration);
    $GLOBALS['SCRIPTLINE_UTTERANCE_ID'] = $utteranceId;
    $GLOBALS['DEBUG_DATA'] = ['OUTPUT_LOG' => $wire];
    $GLOBALS['HERIKA_NAME'] = 'Aela';
    $GLOBALS['CHIM_EXECUTION_MODE'] = 'STANDARD';
    return pcv_reflection_register_with_store(
        reflectionRequestScope($baseline ?? 'Aela|ScriptQueue|previous/neutral/explicit_disable_rechat/none/phonetic/1/explicit_disable_rechat/utt_baseline12345678'),
        $store, $directory, static fn(): array => reflectionScopeFixture(), $nativeAckReader, $requestModel
    );
}

function reflectionAck(string $speech, string $id = 'utt_1234567890abcdef', string $listener = 'Dragonborn'): array
{
    return ['_speech', '', '', json_encode(['speaker' => 'Aela', 'listener' => $listener, 'speech' => $speech, 'utterance_id' => $id], JSON_THROW_ON_ERROR)];
}

function reflectionRemoveTestDirectory(string $path): void
{
    if (!str_starts_with($path, sys_get_temp_dir() . DIRECTORY_SEPARATOR) || !is_dir($path)) {
        return;
    }
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($path);
}

function reflectionPcvLogEntries(): array
{
    $path = pcv_log_path();
    if (!is_string($path) || !is_file($path) || is_link($path)) {
        return [];
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return is_array($lines)
        ? array_map(static fn(string $line): array => json_decode($line, true, 32, JSON_THROW_ON_ERROR), $lines)
        : [];
}

function reflectionPcvEventCount(string $event, string $utteranceId): int
{
    return count(array_filter(reflectionPcvLogEntries(), static fn(array $entry): bool =>
        ($entry['event'] ?? null) === $event
        && ($entry['context']['correlation']['utterance_id'] ?? null) === $utteranceId));
}

$legacyLog = new class {};
check(!pcv_reflection_attach_mp_observer($legacyLog),
    'A logger without the optional observer method must remain supported.');
$mpObserverSupported = method_exists(\ChimMindPoisoning\RequestLog::class, 'observe');
$actualLog = new \ChimMindPoisoning\RequestLog(static function (string $json): void {}, false);
check(pcv_reflection_attach_mp_observer($actualLog) === $mpObserverSupported,
    'The current Mind Poisoning RequestLog observer capability should be detected without changing its evaluation path.');
$optionalLog = new class {
    public $observer = null;
    public function observe(?callable $observer): void
    {
        $this->observer = $observer;
    }
};
check(pcv_reflection_attach_mp_observer($optionalLog) && is_callable($optionalLog->observer),
    'An optional observer-capable logger should be attached without affecting the evaluation path.');
($optionalLog->observer)([], 'info');
$failingOptionalLog = new class {
    public function observe(?callable $observer): void
    {
        throw new RuntimeException('optional observer setup failure');
    }
};
check(!pcv_reflection_attach_mp_observer($failingOptionalLog),
    'An observer setup failure must be contained and reported as unsupported.');
foreach ([
    ['reflection.registration_skipped', 'registration_busy'],
    ['reflection.ack_skipped', 'receipt_busy'],
    ['reflection.ack_skipped', 'ack_conflict'],
    ['reflection.ack_skipped', 'interaction_stale'],
    ['reflection.ack_skipped', 'native_ack_ambiguous'],
    ['reflection.ack_error', 'receipt_unavailable'],
    ['reflection.ack_error', 'receipt_corrupt'],
] as [$event, $reason]) {
    check(pcv_log_reason_allowed($event, $reason), 'The shared log reason registry should accept ' . $event . '/' . $reason . '.');
}
check(pcv_reflection_mind_poisoning_api_compatible() && pcv_reflection_load_mind_poisoning(),
    'A compatible already-loaded Mind Poisoning API should be reusable without a second path lookup.');

function checkBrokenOptionalModule(string $testRoot, string $serverSource): void
{
    check(function_exists('proc_open'), 'The local PHP CLI must support isolated subprocess fixtures.');
    $runner = $testRoot . DIRECTORY_SEPARATOR . 'broken_dependency.php';
    $source = <<<'PHP'
<?php
declare(strict_types=1);
define('PCV_LOG_TESTING', true);
$layout = __LAYOUT__;
$root = $layout . '/webroot/HerikaServer';
$serverSource = __SERVER_SOURCE__;
$extension = $root . '/ext/private_conversation';
$mindPoisoning = $root . '/ext/mind_poisoning';
mkdir($extension, 0700, true);
mkdir($mindPoisoning, 0700, true);
mkdir($layout . '/logs', 0700, true);
foreach (['reflection.php', 'reflection_receipt.php', 'log.php', 'state.php', 'scope.php'] as $name) {
    if (!copy($serverSource . '/' . $name, $extension . '/' . $name)) {
        throw new RuntimeException('Could not prepare isolated extension layout.');
    }
}
file_put_contents($mindPoisoning . '/reflection.php', "<?php\nfunction broken(\n");
require $extension . '/reflection.php';
if (!pcv_log_set_test_directory($layout . '/logs')) {
    throw new RuntimeException('Could not isolate diagnostics.');
}
$utteranceId = 'utt_1234567890abcdef';
$ack = ['_speech', '', '', json_encode([
    'speaker' => 'Aela', 'listener' => 'Dragonborn', 'speech' => 'private test utterance', 'utterance_id' => $utteranceId,
], JSON_THROW_ON_ERROR)];
$ackTuple = pcv_reflection_ack_tuple($ack);
$receipt = [
    'created_at' => time(), 'utterance_id' => $utteranceId, 'pcv_key' => str_repeat('b', 64),
    'config_id' => '123e4567-e89b-42d3-a456-426614174000', 'actor_id' => 11, 'actor_name' => 'Aela',
    'ack_generation' => 1, 'tuple_digest' => pcv_reflection_ack_tuple_digest($ackTuple),
];
pcvReflectionQueueAckReconciliation($ack, $receipt);
register_shutdown_function(static function (): void {
    $path = pcv_log_path();
    if (!is_string($path) || !is_file($path)) {
        throw new RuntimeException('The ACK shutdown callback did not write an isolated diagnostic.');
    }
    $records = array_map(static fn(string $line): array => json_decode($line, true, 32, JSON_THROW_ON_ERROR), array_filter(explode("\n", (string)file_get_contents($path))));
    $failures = array_values(array_filter($records, static fn(array $entry): bool =>
        ($entry['event'] ?? null) === 'reflection.ack_error'
        && ($entry['reason'] ?? null) === 'internal_error'
        && ($entry['context']['actor_a_id'] ?? null) === '11'
        && ($entry['context']['exception_class'] ?? null) === 'ParseError'
    ));
    if (count($failures) !== 1) {
        throw new RuntimeException('A broken optional MP module was not contained by ACK shutdown reconciliation and logged.');
    }
    echo "broken MP module was contained by ACK shutdown reconciliation\n";
});
PHP;
    $source = str_replace(
        ['__LAYOUT__', '__SERVER_SOURCE__'],
        [var_export($testRoot . DIRECTORY_SEPARATOR . 'broken-layout', true), var_export($serverSource, true)],
        $source
    );
    check(file_put_contents($runner, $source) === strlen($source), 'Write the isolated dependency fixture.');
    $process = proc_open([PHP_BINARY, $runner], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    check(is_resource($process), 'Start the isolated dependency fixture.');
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    same(0, proc_close($process), 'A broken optional dependency must not abort ACK processing: ' . $stderr);
    same("broken MP module was contained by ACK shutdown reconciliation\n", $stdout, 'The ACK shutdown callback should return normally after logging an optional dependency failure.');
}

$testRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pcv_reflection_' . bin2hex(random_bytes(8));
check(mkdir($testRoot, 0700), 'Create the isolated reflection test root.');
$logDirectory = $testRoot . DIRECTORY_SEPARATOR . 'logs';
check(mkdir($logDirectory, 0700) && pcv_log_set_test_directory($logDirectory), 'Use isolated PCV logs.');
register_shutdown_function(static fn() => reflectionRemoveTestDirectory($testRoot));
checkBrokenOptionalModule($testRoot, dirname(__DIR__) . '/server');

$id = 'utt_1234567890abcdef';
$subtitle = 'Jarl Balgruuf betrayed me last night.';
$wire = reflectionWire($subtitle, $id);
$earlyDirectory = $testRoot . DIRECTORY_SEPARATOR . 'ack_before_registration';
$earlyStore = reflectionStore($id);
$earlyAck = reflectionAck($subtitle);
resetAckLoggingInteraction();
reflectionSetInteraction();
$earlyTuple = pcv_reflection_ack_tuple($earlyAck);
$earlyReceipt = reflectionStoreAckReceipt($earlyTuple, reflectionScopeFixture(), 1, $earlyDirectory);
same('ready', $earlyReceipt['kind'] ?? null, 'Capture the ACK request snapshot before native source registration.');
$earlyModelCalls = 0;
$earlyAckStatus = pcv_reflection_evaluate_with_store(
    $earlyAck,
    $earlyStore,
    static function () use (&$earlyModelCalls): string {
        $earlyModelCalls++;
        return validModelResponse([['subject' => 'npc:33', 'delta' => 2, 'reason' => 'The reflection supports a change.', 'evidence' => 'Jarl Balgruuf betrayed me']]);
    },
    $earlyDirectory,
    static fn(): array => reflectionScopeFixture()
);
$earlyModel = static function () use (&$earlyModelCalls): string {
    $earlyModelCalls++;
    return validModelResponse([['subject' => 'npc:33', 'delta' => 2, 'reason' => 'The reflection supports a change.', 'evidence' => 'Jarl Balgruuf betrayed me']]);
};
$earlyRow = ['utterance_id' => $id, 'speaker' => 'Aela', 'listener' => 'Dragonborn', 'speech' => $subtitle];
$earlyNativeReader = static fn(string $requestedId): array => $requestedId === $id
    ? ['kind' => 'row', 'row' => $earlyRow] : ['kind' => 'missing'];
$lateRegistrationStatus = reflectionRegister($earlyStore, $earlyDirectory, $wire, null, $earlyNativeReader, $earlyModel);
same('registration_missing', $earlyAckStatus, 'Record the current ACK-before-registration outcome.');
same('registered', $lateRegistrationStatus, 'The exact native output should register after the early ACK.');
same(1, $earlyModelCalls, 'An ACK received before source registration must be reconciled when registration arrives: ' . json_encode(reflectionPcvLogEntries(), JSON_THROW_ON_ERROR));
same(27, $earlyStore->npcs[11]['extended_data']->relationships->{'Jarl Balgruuf'}->aff,
    'The recovered early ACK must persist exactly one actor opinion effect.');

$multiDirectory = $testRoot . DIRECTORY_SEPARATOR . 'multi_chunk_early_ack';
$firstChunkId = 'utt_aaaaaaaaaaaaaaaa';
$finalChunkId = 'utt_bbbbbbbbbbbbbbbb';
$firstChunkTuple = pcv_reflection_ack_tuple(reflectionAck('Aela began the thought.', $firstChunkId));
$finalChunkSpeech = 'Jarl Balgruuf betrayed me in the final sentence.';
$finalChunkTuple = pcv_reflection_ack_tuple(reflectionAck($finalChunkSpeech, $finalChunkId, 'the Dragonborn'));
check(pcv_reflection_ack_matches_solo_scope(
    reflectionAck($finalChunkSpeech, $finalChunkId, 'the Dragonborn'), reflectionScopeFixture()
), 'A normalized player listener alias must pass the exact solo ACK speaker/listener gate.');
same('ready', reflectionStoreAckReceipt($firstChunkTuple, reflectionScopeFixture(), 1, $multiDirectory)['kind'] ?? null,
    'An early ACK for a nonfinal sentence may remain pending.');
same('ready', reflectionStoreAckReceipt($finalChunkTuple, reflectionScopeFixture(), 1, $multiDirectory)['kind'] ?? null,
    'The final sentence ACK must fit beside a distinct earlier chunk.');
$multiStore = reflectionStore($finalChunkId);
$multiModelCalls = 0;
$multiModel = static function () use (&$multiModelCalls): string {
    $multiModelCalls++;
    return validModelResponse([['subject' => 'npc:33', 'delta' => 2, 'reason' => 'The final reflection supports a change.', 'evidence' => 'Jarl Balgruuf betrayed me']]);
};
$multiNativeReader = static fn(string $requestedId): array => $requestedId === $finalChunkId
    ? ['kind' => 'row', 'row' => ['utterance_id' => $finalChunkId, 'speaker' => 'Aela', 'listener' => 'the Dragonborn', 'speech' => $finalChunkSpeech]]
    : ['kind' => 'missing'];
same('registered', reflectionRegister(
    $multiStore, $multiDirectory, reflectionWire($finalChunkSpeech, $finalChunkId), null,
    $multiNativeReader, $multiModel, 1, $finalChunkId
), 'Register the final output while retaining a prior nonfinal receipt.');
same(1, $multiModelCalls, 'Only the receipt with the registered final utterance ID may evaluate.');
same(27, $multiStore->npcs[11]['extended_data']->relationships->{'Jarl Balgruuf'}->aff,
    'The final early ACK must persist one opinion effect.');
$multiReceipts = pcv_reflection_read_receipts_locked($multiDirectory);
same(2, count($multiReceipts['receipts'] ?? []), 'Reconciling the final chunk must not evict the earlier fresh receipt.');

$busyDirectory = $testRoot . DIRECTORY_SEPARATOR . 'unresolved_registration_busy';
$busyFirstId = 'utt_9999999999999991';
$busySecondId = 'utt_9999999999999992';
$busyFirstWire = reflectionWire($subtitle, $busyFirstId);
same('registered', reflectionRegister(
    reflectionStore($busyFirstId), $busyDirectory, $busyFirstWire, null, null, null, 1, $busyFirstId
), 'Register the single unresolved effect slot.');
$busyBefore = json_decode((string)file_get_contents($busyDirectory . DIRECTORY_SEPARATOR . 'reflection.json'), true, 16, JSON_THROW_ON_ERROR);
same('registration_busy', reflectionRegister(
    reflectionStore($busySecondId), $busyDirectory, reflectionWire($subtitle, $busySecondId), null, null, null, 1, $busySecondId
), 'A fresh distinct output must not overwrite an unresolved registered effect.');
$busyAfter = json_decode((string)file_get_contents($busyDirectory . DIRECTORY_SEPARATOR . 'reflection.json'), true, 16, JSON_THROW_ON_ERROR);
same($busyFirstId, $busyAfter['registration']['utterance_id'] ?? null, 'Busy registration must preserve the pending source ID.');
same($busyBefore['created_at'], $busyAfter['created_at'] ?? null, 'Busy registration must not refresh the pending record age.');
$leasePath = $busyDirectory . DIRECTORY_SEPARATOR . 'reflection.json';
$leaseRecord = $busyAfter;
$leaseRecord['created_at'] = time() - 50;
file_put_contents($leasePath, json_encode($leaseRecord, JSON_THROW_ON_ERROR));
@chmod($leasePath, 0600);
same('registered', reflectionRegister(
    reflectionStore($busyFirstId), $busyDirectory, $busyFirstWire, null, null, null, 1, $busyFirstId
), 'A duplicate source within the registration lease must remain idempotent.');
$afterDuplicateLease = json_decode((string)file_get_contents($leasePath), true, 16, JSON_THROW_ON_ERROR);
same($leaseRecord['created_at'], $afterDuplicateLease['created_at'] ?? null,
    'A same-ID duplicate must not renew the original registration timestamp.');
same('registration_busy', reflectionRegister(
    reflectionStore($busySecondId), $busyDirectory, reflectionWire($subtitle, $busySecondId), null, null, null, 1, $busySecondId
), 'A distinct output before the supersession lease expires must remain busy.');
$beforeLeaseExpiry = json_decode((string)file_get_contents($leasePath), true, 16, JSON_THROW_ON_ERROR);
same($busyFirstId, $beforeLeaseExpiry['registration']['utterance_id'] ?? null,
    'A pre-expiry registration attempt must preserve the old source ID.');
$beforeLeaseExpiry['created_at'] = time() - 60;
file_put_contents($leasePath, json_encode($beforeLeaseExpiry, JSON_THROW_ON_ERROR));
@chmod($leasePath, 0600);
same('registered', reflectionRegister(
    reflectionStore($busySecondId), $busyDirectory, reflectionWire($subtitle, $busySecondId), null, null, null, 1, $busySecondId
), 'A distinct output may supersede an unclaimed registration at 60 seconds.');
$afterSupersession = json_decode((string)file_get_contents($leasePath), true, 16, JSON_THROW_ON_ERROR);
same($busySecondId, $afterSupersession['registration']['utterance_id'] ?? null,
    'Supersession must atomically install the new source ID.');
$lateOldModelCalls = 0;
same('registration_missing', pcv_reflection_evaluate_with_store(
    reflectionAck($subtitle, $busyFirstId),
    reflectionStore($busySecondId),
    static function () use (&$lateOldModelCalls): never {
        $lateOldModelCalls++;
        throw new RuntimeException('superseded ACK must not call the provider');
    },
    $busyDirectory
), 'An exact late ACK for the superseded ID must not claim the new registration.');
same(0, $lateOldModelCalls, 'A superseded late ACK must not invoke the provider.');
$afterLateOldAck = json_decode((string)file_get_contents($leasePath), true, 16, JSON_THROW_ON_ERROR);
same($busySecondId, $afterLateOldAck['registration']['utterance_id'] ?? null,
    'A late old ACK must leave the replacement registration intact.');

$claimedLeaseDirectory = $testRoot . DIRECTORY_SEPARATOR . 'claimed_registration_lease';
$claimedLeaseId = 'utt_8888888888888881';
same('registered', reflectionRegister(
    reflectionStore($claimedLeaseId), $claimedLeaseDirectory,
    reflectionWire($subtitle, $claimedLeaseId), null, null, null, 1, $claimedLeaseId
), 'Register a claimed-slot lease fixture.');
$claimedLeasePath = $claimedLeaseDirectory . DIRECTORY_SEPARATOR . 'reflection.json';
$claimedLeaseRecord = json_decode((string)file_get_contents($claimedLeasePath), true, 16, JSON_THROW_ON_ERROR);
$claimedLeaseRecord['created_at'] = time() - 60;
$claimedLeaseRecord['status'] = 'claimed';
$claimedLeaseRecord['claim_token'] = str_repeat('a', 32);
$claimedLeaseTuple = pcv_reflection_ack_tuple(reflectionAck($subtitle, $claimedLeaseId));
$claimedLeaseReceipt = reflectionStoreAckReceipt($claimedLeaseTuple, reflectionScopeFixture(), 1, $claimedLeaseDirectory);
$claimedLeaseRecord['ack_receipt'] = pcv_reflection_receipt_metadata($claimedLeaseReceipt['receipt']);
check(pcv_reflection_valid_record($claimedLeaseRecord), 'The age-bound claimed fixture must remain a valid registry record.');
file_put_contents($claimedLeasePath, json_encode($claimedLeaseRecord, JSON_THROW_ON_ERROR));
@chmod($claimedLeasePath, 0600);
$claimedLeaseNextId = 'utt_8888888888888882';
same('claim_taken', reflectionRegister(
    reflectionStore($claimedLeaseNextId), $claimedLeaseDirectory,
    reflectionWire($subtitle, $claimedLeaseNextId), null, null, null, 1, $claimedLeaseNextId
), 'A claimed effect remains protected at 60 seconds.');
$afterClaimedLeaseAttempt = json_decode((string)file_get_contents($claimedLeasePath), true, 16, JSON_THROW_ON_ERROR);
same($claimedLeaseId, $afterClaimedLeaseAttempt['registration']['utterance_id'] ?? null,
    'A new output must not supersede the 60-second claimed slot.');
same('claimed', $afterClaimedLeaseAttempt['status'] ?? null,
    'The claimed status must remain unchanged after a supersession attempt.');
$busyLogEntries = array_values(array_filter(reflectionPcvLogEntries(), static fn(array $entry): bool =>
    ($entry['event'] ?? null) === 'reflection.registration_skipped'
    && ($entry['reason'] ?? null) === 'registration_busy'
    && ($entry['context']['phase'] ?? null) === 'registration'));
check(count($busyLogEntries) >= 1, 'A busy registration must emit its fixed reflection diagnostic.');
check(!in_array('invalid_event', pcv_log_storage_health()['failure_codes'] ?? [], true),
    'A valid busy-registration diagnostic must not be rejected by the shared logger reason registry.');

$receiptMapDirectory = $testRoot . DIRECTORY_SEPARATOR . 'receipt_map_bounds';
$receiptMapScope = reflectionScopeFixture();
$receiptMapIds = [
    'utt_4444444444444444', 'utt_4444444444444445', 'utt_4444444444444446', 'utt_4444444444444447',
    'utt_4444444444444448', 'utt_4444444444444449', 'utt_444444444444444a', 'utt_444444444444444b',
];
$firstMapId = $receiptMapIds[0];
$firstMapSpeech = 'Aela shared a pending receipt for the capacity check.';
$firstMapTuple = pcv_reflection_ack_tuple(reflectionAck($firstMapSpeech, $firstMapId));
same('ready', reflectionStoreAckReceipt($firstMapTuple, $receiptMapScope, 1, $receiptMapDirectory)['kind'] ?? null,
    'Create the first bounded receipt candidate.');
$receiptMapPath = $receiptMapDirectory . DIRECTORY_SEPARATOR . PCV_REFLECTION_RECEIPT_FILE;
$receiptMapContents = json_decode((string)file_get_contents($receiptMapPath), true, 16, JSON_THROW_ON_ERROR);
$receiptMapContents['receipts'][0]['created_at'] = time() - 10;
file_put_contents($receiptMapPath, json_encode($receiptMapContents, JSON_THROW_ON_ERROR));
@chmod($receiptMapPath, 0600);
$duplicateMapReceipt = reflectionStoreAckReceipt($firstMapTuple, $receiptMapScope, 1, $receiptMapDirectory);
same(true, $duplicateMapReceipt['duplicate'] ?? null, 'An exact receipt duplicate should reuse the pending candidate.');
same($receiptMapContents['receipts'][0]['created_at'], $duplicateMapReceipt['receipt']['created_at'] ?? null,
    'An exact duplicate must not refresh the receipt TTL.');
$conflictingMapTuple = pcv_reflection_ack_tuple(reflectionAck('Conflicting speech for the same ID.', $firstMapId));
same('conflict', reflectionStoreAckReceipt($conflictingMapTuple, $receiptMapScope, 1, $receiptMapDirectory)['kind'] ?? null,
    'A same-ID receipt with different exact speech must fail closed.');
$afterConflictMap = json_decode((string)file_get_contents($receiptMapPath), true, 16, JSON_THROW_ON_ERROR);
same($firstMapSpeech, $firstMapTuple['speech'] ?? null, 'The original exact speech tuple remains the expected receipt.');
same($duplicateMapReceipt['receipt']['tuple_digest'], $afterConflictMap['receipts'][0]['tuple_digest'] ?? null,
    'A conflicting duplicate must not replace the retained digest.');
foreach (array_slice($receiptMapIds, 1) as $mapId) {
    $tuple = pcv_reflection_ack_tuple(reflectionAck('Pending bounded receipt.', $mapId));
    same('ready', reflectionStoreAckReceipt($tuple, $receiptMapScope, 1, $receiptMapDirectory)['kind'] ?? null,
        'Distinct early receipt candidates should fit until the configured count ceiling.');
}
$ninthMapId = 'utt_5555555555555555';
$ninthMapTuple = pcv_reflection_ack_tuple(reflectionAck('The ninth candidate must wait.', $ninthMapId));
same('busy', reflectionStoreAckReceipt($ninthMapTuple, $receiptMapScope, 1, $receiptMapDirectory)['kind'] ?? null,
    'A fresh ninth candidate must report capacity busy instead of evicting a receipt.');
$fullReceiptMap = json_decode((string)file_get_contents($receiptMapPath), true, 16, JSON_THROW_ON_ERROR);
same(PCV_REFLECTION_RECEIPT_MAX_COUNT, count($fullReceiptMap['receipts'] ?? []), 'The bounded map must stay at its eight-entry ceiling.');
same(1, count(array_filter($fullReceiptMap['receipts'], static fn(array $item): bool => $item['utterance_id'] === $firstMapId)),
    'Capacity pressure must retain the existing first fresh candidate.');
check(filesize($receiptMapPath) <= PCV_REFLECTION_RECEIPT_MAX_BYTES, 'The bounded receipt map must remain under its byte ceiling.');
$fullReceiptMap['receipts'][0]['created_at'] = time() - PCV_REFLECTION_RECEIPT_TTL - 1;
file_put_contents($receiptMapPath, json_encode($fullReceiptMap, JSON_THROW_ON_ERROR));
@chmod($receiptMapPath, 0600);
same('ready', reflectionStoreAckReceipt($ninthMapTuple, $receiptMapScope, 1, $receiptMapDirectory)['kind'] ?? null,
    'A safely expired candidate should free one bounded slot.');
$prunedReceiptMap = json_decode((string)file_get_contents($receiptMapPath), true, 16, JSON_THROW_ON_ERROR);
same(PCV_REFLECTION_RECEIPT_MAX_COUNT, count($prunedReceiptMap['receipts'] ?? []), 'TTL pruning should keep the receipt count bounded.');
check(count(array_filter($prunedReceiptMap['receipts'], static fn(array $item): bool => $item['utterance_id'] === $firstMapId)) === 0,
    'Only the expired candidate should be pruned.');
same(1, count(array_filter($prunedReceiptMap['receipts'], static fn(array $item): bool => $item['utterance_id'] === $receiptMapIds[1])),
    'Fresh candidates must survive cleanup of an expired receipt.');
check(!str_contains((string)file_get_contents($receiptMapPath), $firstMapSpeech),
    'Receipt metadata must not persist ACK dialogue.');
$expiredDuplicateDirectory = $testRoot . DIRECTORY_SEPARATOR . 'expired_same_id_receipt';
$expiredDuplicateTuple = pcv_reflection_ack_tuple(reflectionAck('Aela repeated the expired exact utterance.', 'utt_5555555555555556'));
same('ready', reflectionStoreAckReceipt($expiredDuplicateTuple, $receiptMapScope, 1, $expiredDuplicateDirectory)['kind'] ?? null,
    'Capture the first request-time receipt for the expiry policy fixture.');
$expiredDuplicatePath = $expiredDuplicateDirectory . DIRECTORY_SEPARATOR . PCV_REFLECTION_RECEIPT_FILE;
$expiredDuplicateData = json_decode((string)file_get_contents($expiredDuplicatePath), true, 16, JSON_THROW_ON_ERROR);
$expiredDuplicateData['receipts'][0]['created_at'] = time() - PCV_REFLECTION_RECEIPT_TTL - 1;
file_put_contents($expiredDuplicatePath, json_encode($expiredDuplicateData, JSON_THROW_ON_ERROR));
@chmod($expiredDuplicatePath, 0600);
$reissuedExpiredTuple = reflectionStoreAckReceipt($expiredDuplicateTuple, $receiptMapScope, 1, $expiredDuplicateDirectory);
same('ready', $reissuedExpiredTuple['kind'] ?? null,
    'A later newly received request may capture the same exact ID after its old pending entry expires.');
same(false, $reissuedExpiredTuple['duplicate'] ?? null,
    'A post-expiry request is a new capture, not an unexpired duplicate that renews receipt age.');
check(($reissuedExpiredTuple['receipt']['created_at'] ?? 0) > $expiredDuplicateData['receipts'][0]['created_at'],
    'A post-expiry same-ID request receives a new request-time timestamp.');

$directory = $testRoot . DIRECTORY_SEPARATOR . 'valid';
$store = reflectionStore($id);
$GLOBALS['CHIM_EXECUTION_MODE'] = 'STANDARD';
$GLOBALS['HERIKA_NAME'] = 'Aela';
$GLOBALS['DEBUG_DATA'] = [];
same('output_unavailable', pcv_reflection_register_with_store(
    reflectionRequestScope('Aela|ScriptQueue|previous/neutral/explicit_disable_rechat/none/phonetic/1/explicit_disable_rechat/utt_baseline12345678'),
    reflectionStore($id), $testRoot . DIRECTORY_SEPARATOR . 'output_unavailable', static fn(): array => reflectionScopeFixture()
), 'Do not attempt a registration until the native output log exists.');
resetAckLoggingInteraction();
same('registered', reflectionRegister($store, $directory, $wire), 'Register only a fresh full native output line.');
$registryPath = $directory . DIRECTORY_SEPARATOR . 'reflection.json';
$registry = json_decode((string)file_get_contents($registryPath), true, 16, JSON_THROW_ON_ERROR);
same(2, $registry['version'] ?? null, 'The registry envelope version is required.');
same(hash('sha256', $subtitle), $registry['registration']['speech_hash'] ?? null, 'Hash only the trimmed subtitle field.');
check(!str_contains((string)file_get_contents($registryPath), $subtitle), 'Do not persist raw dialogue.');
$registeredEntries = array_values(array_filter(reflectionPcvLogEntries(), static fn(array $entry): bool =>
    ($entry['event'] ?? null) === 'reflection.output_registered'));
$registeredCorrelation = $registeredEntries[0]['context']['correlation'] ?? [];
same('200', $registeredCorrelation['event_id'] ?? null, 'The accepted registration log should carry the exact matched source event ID.');
same($id, $registeredCorrelation['utterance_id'] ?? null, 'The accepted registration log should carry the exact native utterance ID.');

$modelCalls = 0;
$prompt = null;
$claimToken = null;
$claimedRegistrationAttempt = null;
$mpRecords = [];
$ackObserverBoundary = null;
$requestLog = new \ChimMindPoisoning\RequestLog(static function (string $json) use (&$mpRecords, &$ackObserverBoundary): void {
    $record = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    $mpRecords[] = $record;
    if ($ackObserverBoundary === null && ($record['source_kind'] ?? null) === 'reflection'
        && in_array($record['event'] ?? null, ['reflection_model_finished', 'persistence_finished', 'persistence_cleanup_failed', 'request_finished'], true)) {
        $request =& pcv_log_request_context();
        $ackObserverBoundary = [
            'config_id' => $request['config_id'],
            'correlation' => $request['correlation'],
        ];
    }
}, false);
$ack = reflectionAck($subtitle);
same('registration_missing', pcv_reflection_evaluate_with_store(reflectionAck($subtitle, 'utt_aaaaaaaaaaaaaaaa'), $store, static function (): never { throw new RuntimeException('unrelated ACK provider call'); }, $directory, static fn(): array => reflectionScopeFixture()), 'An unrelated utterance must not use this registration.');
same('ack_mismatch', pcv_reflection_evaluate_with_store(reflectionAck('Different words.', $id), $store, static function (): never { throw new RuntimeException('mismatched ACK provider call'); }, $directory, static fn(): array => reflectionScopeFixture()), 'A mismatched subtitle must leave the registration unused.');
$paddedDirectory = $testRoot . DIRECTORY_SEPARATOR . 'padded_id';
$paddedStore = reflectionStore($id);
same('registered', reflectionRegister($paddedStore, $paddedDirectory, $wire), 'Register the normalized native utterance ID.');
same('not_applicable', pcv_reflection_evaluate_with_store(reflectionAck($subtitle, $id . "\rX"), $paddedStore, static fn(): never => throw new RuntimeException('malformed interior ID must not call provider'), $paddedDirectory, static fn(): array => reflectionScopeFixture()), 'Trimming must not accept interior ID corruption.');
$paddedModelCalls = 0;
$paddedModel = static function () use (&$paddedModelCalls): string {
    $paddedModelCalls++;
    return validModelResponse([['subject' => 'npc:33', 'delta' => 2, 'reason' => 'The reflection supports a change.', 'evidence' => 'Jarl Balgruuf betrayed me']]);
};
same('committed', pcv_reflection_evaluate_with_store(reflectionAck($subtitle, $id . "\r"), $paddedStore, $paddedModel, $paddedDirectory, static fn(): array => reflectionScopeFixture()), 'Normalize the padded native ACK ID before exact registry matching.');
same('claim_taken', pcv_reflection_evaluate_with_store(reflectionAck($subtitle, $id . "\r"), $paddedStore, $paddedModel, $paddedDirectory, static fn(): array => reflectionScopeFixture()), 'A repeated padded ACK must remain single-use.');
same(1, $paddedModelCalls, 'A padded duplicate must not call the provider twice.');
$expiredDirectory = $testRoot . DIRECTORY_SEPARATOR . 'expired';
same('registered', reflectionRegister(reflectionStore($id), $expiredDirectory, $wire), 'Register expiry fixture.');
$expiredPath = $expiredDirectory . DIRECTORY_SEPARATOR . 'reflection.json';
$expiredRecord = json_decode((string)file_get_contents($expiredPath), true, 16, JSON_THROW_ON_ERROR);
$expiredRecord['created_at'] = time() - PCV_REFLECTION_REGISTRY_TTL - 1;
file_put_contents($expiredPath, json_encode($expiredRecord, JSON_THROW_ON_ERROR));
@chmod($expiredPath, 0600);
$expiredCalls = 0;
same('registration_stale', pcv_reflection_evaluate_with_store($ack, reflectionStore($id), static function () use (&$expiredCalls): never { $expiredCalls++; throw new RuntimeException('expired provider call'); }, $expiredDirectory), 'An expired registration must not be evaluated.');
same(0, $expiredCalls, 'Expired registrations must not call the provider.');
// Each real ACK arrives in a new HTTP request, so prior registration globals cannot anchor the observer tuple.
pcv_log_set_config_id(null);
pcv_log_set_correlation([]);
$status = pcv_reflection_evaluate_with_store($ack, $store, static function (array $messages) use (&$modelCalls, &$prompt, &$claimToken, &$claimedRegistrationAttempt, $directory, $wire): string {
    $modelCalls++;
    $prompt = $messages;
    $claimed = json_decode((string)file_get_contents($directory . DIRECTORY_SEPARATOR . 'reflection.json'), true, 16, JSON_THROW_ON_ERROR);
    $claimToken = $claimed['claim_token'] ?? null;
    $lock = pcv_lock_state($directory, false, LOCK_EX | LOCK_NB);
    check(is_resource($lock), 'The PCV file lock must be released before provider work.');
    pcv_unlock_state($lock);
    $requestContext =& pcv_log_request_context();
    $savedConfigId = $requestContext['config_id'] ?? null;
    $savedCorrelation = $requestContext['correlation'] ?? [];
    $otherId = 'utt_3333333333333333';
    $claimedRegistrationAttempt = reflectionRegister(
        reflectionStore($otherId), $directory, reflectionWire('Aela has another sentence.', $otherId), null,
        null, null, 1, $otherId
    );
    $stillClaimed = json_decode((string)file_get_contents($directory . DIRECTORY_SEPARATOR . 'reflection.json'), true, 16, JSON_THROW_ON_ERROR);
    same('claimed', $stillClaimed['status'] ?? null, 'A distinct output must not overwrite a claimed effect slot.');
    same('utt_1234567890abcdef', $stillClaimed['registration']['utterance_id'] ?? null,
        'A claimed slot must retain its original utterance during provider work.');
    pcv_log_set_config_id(is_string($savedConfigId) ? $savedConfigId : null);
    pcv_log_set_correlation(is_array($savedCorrelation) ? $savedCorrelation : []);
    return validModelResponse([['subject' => 'npc:33', 'delta' => 3, 'reason' => 'The reflection supports a change.', 'evidence' => 'Jarl Balgruuf betrayed me']]);
}, $directory, static fn(): array => reflectionScopeFixture(), $requestLog);
same('committed', $status, 'The exact ACK must reach the real Mind Poisoning reflection API.');
same('claim_taken', $claimedRegistrationAttempt, 'A distinct source cannot reuse a claimed effect slot.');
same('123e4567-e89b-42d3-a456-426614174000', $ackObserverBoundary['config_id'] ?? null,
    'The successful ACK must bind the validated configuration before an observer row is written.');
same('200', $ackObserverBoundary['correlation']['event_id'] ?? null,
    'The successful ACK must bind the validated event ID before an observer row is written.');
same($id, $ackObserverBoundary['correlation']['utterance_id'] ?? null,
    'The successful ACK must bind the validated utterance ID before an observer row is written.');
same(1, $modelCalls, 'Evaluate this ACK once.');
$payload = json_decode($prompt[1]['content'], true, 64, JSON_THROW_ON_ERROR)['untrusted_data'] ?? [];
same($subtitle, $payload['current_reflection'] ?? null, 'Use emitted subtitle even when core source text differs.');
same(28, $store->npcs[11]['extended_data']->relationships->{'Jarl Balgruuf'}->aff, 'Persist the reflecting actor’s opinion.');
same(99, $store->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->aff, 'Do not invent another NPC as listener or owner.');
$summary = null;
foreach (array_reverse($mpRecords) as $entry) {
    if (($entry['event'] ?? null) === 'request_finished') { $summary = $entry; break; }
}
same('reflection', $summary['source_kind'] ?? null, 'MP diagnostics must retain reflection provenance.');
same('11', $summary['opinion_owner_id'] ?? null, 'MP diagnostics must attribute the actor opinion owner.');
same('committed', $summary['outcome'] ?? null, 'MP diagnostics should record the persisted result.');
check(!array_key_exists('listener_id', $summary ?? []), 'MP diagnostics must not invent a listener.');
$unsupportedEntries = array_values(array_filter(reflectionPcvLogEntries(), static fn(array $entry): bool =>
    ($entry['event'] ?? null) === 'reflection.observer_unavailable'
    && ($entry['reason'] ?? null) === 'observer_unsupported'
    && ($entry['context']['correlation']['event_id'] ?? null) === '200'
    && ($entry['context']['correlation']['utterance_id'] ?? null) === $id));
same($mpObserverSupported ? 0 : 3, count($unsupportedEntries),
    'The PCV log should report observer unavailability only when the installed MP RequestLog lacks the optional API.');
if (!$mpObserverSupported && $unsupportedEntries !== []) {
    same('unavailable', $unsupportedEntries[0]['outcome'] ?? null,
        'The PCV log should state that unified MP outcome import is unsupported for a validated ACK.');
}
$importedEntries = array_values(array_filter(reflectionPcvLogEntries(), static fn(array $entry): bool =>
    in_array($entry['event'] ?? null, ['reflection.model_finished', 'reflection.persistence_finished', 'reflection.evaluation_result'], true)));
check($mpObserverSupported ? $importedEntries !== [] : $importedEntries === [],
    'Detailed Mind Poisoning outcomes should be imported only when its optional observer API is available.');
same(3, reflectionPcvEventCount('reflection.evaluation_finished', $id),
    'Each committed adapter return should be recorded once without making playback claims.');
same('claim_taken', pcv_reflection_evaluate_with_store($ack, $store, static function (): never { throw new RuntimeException('duplicate provider call'); }, $directory, static fn(): array => reflectionScopeFixture()), 'Consume duplicate ACKs without evaluation.');
same(1, $modelCalls, 'Duplicate ACKs must not call the provider again.');
$consumedBeforeDuplicate = json_decode((string)file_get_contents($registryPath), true, 16, JSON_THROW_ON_ERROR);
same('claim_taken', reflectionRegister($store, $directory, $wire), 'A duplicate source registration must not reopen a consumed effect.');
$consumedAfterDuplicate = json_decode((string)file_get_contents($registryPath), true, 16, JSON_THROW_ON_ERROR);
same('consumed', $consumedAfterDuplicate['status'] ?? null, 'A duplicate source registration must preserve the consumed status.');
same($consumedBeforeDuplicate['created_at'], $consumedAfterDuplicate['created_at'] ?? null,
    'A duplicate source registration must not refresh a consumed record age.');
same($consumedBeforeDuplicate['claim_token'], $consumedAfterDuplicate['claim_token'] ?? null,
    'A duplicate source registration must not replace the consumed claim token.');
same(1, $modelCalls, 'A duplicate consumed source registration must not retry the provider.');
$nextId = 'utt_2222222222222222';
$nextSubtitle = 'Aela spoke again after the previous reflection finished.';
$nextStore = reflectionStore($nextId);
same('registered', reflectionRegister(
    $nextStore, $directory, reflectionWire($nextSubtitle, $nextId), null, null, null, 1, $nextId
), 'A completed consumed slot must allow the next distinct solo utterance.');
$nextRecord = json_decode((string)file_get_contents($registryPath), true, 16, JSON_THROW_ON_ERROR);
same($nextId, $nextRecord['registration']['utterance_id'] ?? null,
    'The next distinct utterance should replace only the resolved consumed slot.');
$replayEntries = array_values(array_filter(reflectionPcvLogEntries(), static fn(array $entry): bool =>
    ($entry['event'] ?? null) === 'reflection.ack_skipped' && ($entry['reason'] ?? null) === 'claim_taken'));
check($replayEntries !== []
    && ($replayEntries[0]['context']['correlation']['event_id'] ?? null) === '200'
    && ($replayEntries[0]['context']['correlation']['utterance_id'] ?? null) === $id,
    'A replay skip should retain the same exact correlation tuple without making a second provider call.');

$zeroDirectory = $testRoot . DIRECTORY_SEPARATOR . 'zero_change';
$zeroStore = reflectionStore($id);
same('registered', reflectionRegister($zeroStore, $zeroDirectory, $wire), 'Register zero-change fixture.');
$zeroRecords = [];
$zeroLog = new \ChimMindPoisoning\RequestLog(static function (string $json) use (&$zeroRecords): void {
    $zeroRecords[] = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
}, false);
same('committed', pcv_reflection_evaluate_with_store($ack, $zeroStore, static fn(): string => validModelResponse([
    ['subject' => 'npc:33', 'delta' => 0, 'reason' => 'No new evidence supports a change.', 'evidence' => 'Jarl Balgruuf betrayed me'],
]), $zeroDirectory, static fn(): array => reflectionScopeFixture(), $zeroLog), 'The exact reflection may be confirmed with no affinity changes.');
$zeroSummary = null;
foreach (array_reverse($zeroRecords) as $entry) {
    if (($entry['event'] ?? null) === 'request_finished') { $zeroSummary = $entry; break; }
}
same('committed', $zeroSummary['outcome'] ?? null, 'Mind Poisoning must retain the detailed zero-change result.');
same(0, $zeroSummary['changed_count'] ?? null, 'Mind Poisoning must retain the exact zero change count.');
$unsupportedAfterZero = array_values(array_filter(reflectionPcvLogEntries(), static fn(array $entry): bool =>
    ($entry['event'] ?? null) === 'reflection.observer_unavailable'
    && ($entry['context']['correlation']['event_id'] ?? null) === '200'
    && ($entry['context']['correlation']['utterance_id'] ?? null) === $id));
same($mpObserverSupported ? 0 : 4, count($unsupportedAfterZero),
    'Each validated ACK should get an unsupported-import marker only when the optional observer API is absent.');
same(4, reflectionPcvEventCount('reflection.evaluation_finished', $id),
    'Each committed adapter return should be recorded once.');

foreach ([
    reflectionWire($subtitle, $id, 'Aela', 'Player'),
    reflectionWire($subtitle, $id, 'Aela', 'explicit_disable_rechat', 'Player'),
    reflectionWire($subtitle . '/ambiguous', $id),
    reflectionWire($subtitle . '|ambiguous', $id),
    reflectionWire($subtitle, 'utt_aaaaaaaaaaaaaaaa'),
    reflectionWire($subtitle . "\nextra", $id),
    $wire . '/extra',
    'Aela|ScriptQueue|extra|' . substr($wire, strlen('Aela|ScriptQueue|')),
] as $index => $line) {
    same('output_malformed', reflectionRegister(reflectionStore($id), $testRoot . DIRECTORY_SEPARATOR . 'malformed_' . $index, $line), 'Reject wrong sentinel positions, extra native fields, and ambiguous pipes.');
}
same('baseline_stale', reflectionRegister(reflectionStore($id), $testRoot . DIRECTORY_SEPARATOR . 'stale_baseline', $wire, $wire), 'Reject unchanged pre-generation output.');
same('source_aborted', reflectionRegister(reflectionStore($id, 'aborted'), $testRoot . DIRECTORY_SEPARATOR . 'aborted', $wire), 'Reject aborted source events.');
same('sentinel_mismatch', reflectionRegister(reflectionStore($id, 'emitted', 'Aela: I met the steward. (Talking to Lydia)'), $testRoot . DIRECTORY_SEPARATOR . 'source_target_mismatch', $wire), 'The independently parsed source event must also prove the explicit sentinel target.');

$scopeDirectory = $testRoot . DIRECTORY_SEPARATOR . 'scope_changed';
$scopeStore = reflectionStore($id);
same('registered', reflectionRegister($scopeStore, $scopeDirectory, $wire), 'Register before stale-scope check.');
$scopeNow = reflectionScopeFixture();
$scopeReader = static function () use (&$scopeNow): array { return $scopeNow; };
$staleRecords = [];
$staleLog = new \ChimMindPoisoning\RequestLog(static function (string $json) use (&$staleRecords): void { $staleRecords[] = json_decode($json, true, 32, JSON_THROW_ON_ERROR); }, false);
$staleStatus = pcv_reflection_evaluate_with_store($ack, $scopeStore, static function () use (&$scopeNow): string {
    $scopeNow['config_id'] = '223e4567-e89b-42d3-a456-426614174000';
    return validModelResponse([['subject' => 'npc:33', 'delta' => 3, 'reason' => 'The reflection supports a change.', 'evidence' => 'Jarl Balgruuf betrayed me']]);
}, $scopeDirectory, $scopeReader, $staleLog);
same('stale', $staleStatus, 'The transaction must reject a changed active scope.');
same(25, $scopeStore->npcs[11]['extended_data']->relationships->{'Jarl Balgruuf'}->aff, 'A stale transaction must not persist.');
same(4, reflectionPcvEventCount('reflection.evaluation_finished', $id),
    'A stale API result must not be reported as an accepted adapter completion.');

$endedDirectory = $testRoot . DIRECTORY_SEPARATOR . 'ended_before_ack';
$endedStore = reflectionStore($id);
same('registered', reflectionRegister($endedStore, $endedDirectory, $wire), 'Register before explicit END fixture.');
$endedModelCalls = 0;
same('scope_changed', pcv_reflection_evaluate_with_store(
    $ack,
    $endedStore,
    static function () use (&$endedModelCalls): never {
        $endedModelCalls++;
        throw new RuntimeException('ended ACK provider call');
    },
    $endedDirectory,
    static fn(): array => ['status' => 'off']
), 'An exact registered ACK must fail closed after END clears active state.');
same(0, $endedModelCalls, 'An ACK after END must not call the model.');
same(25, $endedStore->npcs[11]['extended_data']->relationships->{'Jarl Balgruuf'}->aff,
    'An ACK after END must not write the actor opinion.');
$endedRecord = json_decode((string)file_get_contents($endedDirectory . DIRECTORY_SEPARATOR . 'reflection.json'), true, 16, JSON_THROW_ON_ERROR);
same('registered', $endedRecord['status'] ?? null, 'An ACK rejected after END must not claim or consume its registration.');

$staleEpochId = 'utt_6666666666666666';
$staleEpochDirectory = $testRoot . DIRECTORY_SEPARATOR . 'stale_ack_epoch';
$staleEpochStore = reflectionStore($staleEpochId);
$staleEpochSpeech = 'Jarl Balgruuf betrayed me after the source request.';
reflectionSetInteraction(4, 4, true);
$staleEpochAck = reflectionAck($staleEpochSpeech, $staleEpochId);
$staleEpochTuple = pcv_reflection_ack_tuple($staleEpochAck);
$capturedAckGeneration = null;
check(pcv_reflection_capture_interaction_generation($capturedAckGeneration) && $capturedAckGeneration === 4,
    'Capture the actual ACK request epoch before native row insertion.');
$staleEpochReceipt = reflectionStoreAckReceipt($staleEpochTuple, reflectionScopeFixture(), $capturedAckGeneration, $staleEpochDirectory);
same('ready', $staleEpochReceipt['kind'] ?? null, 'Capture the actual ACK snapshot from generation G4.');
$staleEpochModelCalls = 0;
same('registered', reflectionRegister(
    $staleEpochStore, $staleEpochDirectory, reflectionWire($staleEpochSpeech, $staleEpochId), null,
    static fn(string $requestedId): array => $requestedId === $staleEpochId
        ? ['kind' => 'row', 'row' => ['utterance_id' => $staleEpochId, 'speaker' => 'Aela', 'listener' => 'Dragonborn', 'speech' => $staleEpochSpeech]]
        : ['kind' => 'missing'],
    static function () use (&$staleEpochModelCalls): never {
        $staleEpochModelCalls++;
        throw new RuntimeException('A captured G4 ACK must not reach the model during G5 source recovery.');
    }, 5, $staleEpochId
), 'Register the exact source output in interaction generation G5.');
same(0, $staleEpochModelCalls, 'A captured G4 ACK must fail when recovery runs with source/current G5.');
same(25, $staleEpochStore->npcs[11]['extended_data']->relationships->{'Jarl Balgruuf'}->aff,
    'A captured G4 ACK must not persist an opinion during G5 recovery.');
$staleEpochRecord = json_decode((string)file_get_contents($staleEpochDirectory . DIRECTORY_SEPARATOR . 'reflection.json'), true, 16, JSON_THROW_ON_ERROR);
same(5, $staleEpochRecord['source_generation'] ?? null, 'Recovery must retain the exact G5 source epoch.');
same(4, $staleEpochReceipt['receipt']['ack_generation'] ?? null, 'The receipt must retain the earlier G4 ACK epoch.');
$staleEpochLog = array_values(array_filter(reflectionPcvLogEntries(), static fn(array $entry): bool =>
    ($entry['event'] ?? null) === 'reflection.ack_skipped'
    && ($entry['reason'] ?? null) === 'interaction_stale'
    && ($entry['context']['correlation']['utterance_id'] ?? null) === $staleEpochId));
same(1, count($staleEpochLog), 'The rejected G4 ACK/G5 source recovery must emit one fixed interaction-stale log event.');

$preModelEpochId = 'utt_7777777777777777';
$preModelEpochDirectory = $testRoot . DIRECTORY_SEPARATOR . 'pre_model_epoch';
$preModelEpochStore = reflectionStore($preModelEpochId);
$preModelEpochAck = reflectionAck($staleEpochSpeech, $preModelEpochId);
same('registered', reflectionRegister(
    $preModelEpochStore, $preModelEpochDirectory, reflectionWire($staleEpochSpeech, $preModelEpochId), null, null, null, 1, $preModelEpochId
), 'Register the pre-model generation check fixture.');
$preModelEpochTuple = pcv_reflection_ack_tuple($preModelEpochAck);
$preModelEpochReceipt = reflectionStoreAckReceipt($preModelEpochTuple, reflectionScopeFixture(), 1, $preModelEpochDirectory);
$preModelClaim = pcv_reflection_registry_probe($preModelEpochDirectory)['record'];
$preModelClaim['status'] = 'claimed';
$preModelClaim['claim_token'] = str_repeat('b', 32);
$preModelClaim['ack_receipt'] = pcv_reflection_receipt_metadata($preModelEpochReceipt['receipt']);
$preModelHandle = pcv_lock_state($preModelEpochDirectory, true, LOCK_EX);
try {
    pcv_reflection_write_locked($preModelEpochDirectory, $preModelClaim);
} finally {
    pcv_unlock_state($preModelHandle);
}
$GLOBALS['runtime_test_interaction_generation'] = 2;
$preModelReason = null;
check(!pcv_reflection_revalidate(
    $preModelClaim['registration'], 'pre_model', $preModelClaim['claim_token'],
    $preModelEpochDirectory, static fn(): array => reflectionScopeFixture(), $preModelReason, $preModelClaim
), 'The actual MP pre_model callback must reject when the captured epochs have ended.');
same('interaction_stale', $preModelReason, 'The pre_model callback must report the fixed interaction-stale reason.');
same(25, $preModelEpochStore->npcs[11]['extended_data']->relationships->{'Jarl Balgruuf'}->aff,
    'A stale pre_model epoch must not persist an opinion.');
$transactionReason = null;
check(!pcv_reflection_revalidate(
    $preModelClaim['registration'], 'transaction', $preModelClaim['claim_token'],
    $preModelEpochDirectory, static fn(): array => reflectionScopeFixture(), $transactionReason, $preModelClaim
), 'The actual MP transaction callback must independently reject the same stale epochs.');
same('interaction_stale', $transactionReason, 'The transaction callback must report the fixed interaction-stale reason.');

$transactionEpochId = 'utt_8888888888888888';
$transactionEpochDirectory = $testRoot . DIRECTORY_SEPARATOR . 'transaction_epoch';
$transactionEpochStore = reflectionStore($transactionEpochId);
$transactionEpochAck = reflectionAck($staleEpochSpeech, $transactionEpochId);
same('registered', reflectionRegister(
    $transactionEpochStore, $transactionEpochDirectory, reflectionWire($staleEpochSpeech, $transactionEpochId), null, null, null, 1, $transactionEpochId
), 'Register the transaction generation check fixture.');
$transactionEpochTuple = pcv_reflection_ack_tuple($transactionEpochAck);
$transactionEpochReceipt = reflectionStoreAckReceipt($transactionEpochTuple, reflectionScopeFixture(), 1, $transactionEpochDirectory);
$transactionModelCalls = 0;
same('stale', pcv_reflection_evaluate_with_store(
    $transactionEpochAck, $transactionEpochStore, static function () use (&$transactionModelCalls): string {
        $transactionModelCalls++;
        $GLOBALS['runtime_test_interaction_generation'] = 2;
        return validModelResponse([['subject' => 'npc:33', 'delta' => 3, 'reason' => 'A changed generation must be rejected.', 'evidence' => 'Jarl Balgruuf betrayed me']]);
    }, $transactionEpochDirectory, static fn(): array => reflectionScopeFixture(), null, $transactionEpochReceipt['receipt']
), 'Recheck the source and ACK epochs at the MP transaction callback.');
same(1, $transactionModelCalls, 'The request may reach the provider before its generation changes.');
same(25, $transactionEpochStore->npcs[11]['extended_data']->relationships->{'Jarl Balgruuf'}->aff,
    'A stale transaction epoch must not persist an opinion.');

$slowDirectory = $testRoot . DIRECTORY_SEPARATOR . 'claimed_receipt_after_ttl';
$slowStore = reflectionStore($id);
same('registered', reflectionRegister($slowStore, $slowDirectory, $wire), 'Register the claimed-receipt TTL fixture.');
$slowTuple = pcv_reflection_ack_tuple($ack);
$slowReceipt = reflectionStoreAckReceipt($slowTuple, reflectionScopeFixture(), 1, $slowDirectory);
$slowModelCalls = 0;
same('committed', pcv_reflection_evaluate_with_store(
    $ack,
    $slowStore,
    static function () use (&$slowModelCalls, $slowDirectory, $id): string {
        $slowModelCalls++;
        $path = $slowDirectory . DIRECTORY_SEPARATOR . PCV_REFLECTION_RECEIPT_FILE;
        $data = json_decode((string)file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
        foreach ($data['receipts'] as &$receipt) {
            if ($receipt['utterance_id'] === $id) {
                $receipt['created_at'] = time() - PCV_REFLECTION_RECEIPT_TTL - 1;
            }
        }
        unset($receipt);
        file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR));
        @chmod($path, 0600);
        return validModelResponse([['subject' => 'npc:33', 'delta' => 2, 'reason' => 'The already-claimed exact ACK remains bound.', 'evidence' => 'Jarl Balgruuf betrayed me']]);
    },
    $slowDirectory,
    static fn(): array => reflectionScopeFixture(),
    null,
    $slowReceipt['receipt']
), 'An exact ACK claimed inside the pending TTL may finish after that lookup window.');
same(1, $slowModelCalls, 'The claimed result should reach the provider once after pending TTL.');
same(27, $slowStore->npcs[11]['extended_data']->relationships->{'Jarl Balgruuf'}->aff,
    'A still-fresh claimed registry may commit after receipt lookup metadata expires.');

$failureDirectory = $testRoot . DIRECTORY_SEPARATOR . 'provider_failure';
$failureStore = reflectionStore($id);
same('registered', reflectionRegister($failureStore, $failureDirectory, $wire), 'Register provider-failure fixture.');
$failureRecords = [];
$failureLog = new \ChimMindPoisoning\RequestLog(static function (string $json) use (&$failureRecords): void { $failureRecords[] = json_decode($json, true, 32, JSON_THROW_ON_ERROR); }, false);
same('failed', pcv_reflection_evaluate_with_store($ack, $failureStore, static function (): never { throw new RuntimeException('private provider failure'); }, $failureDirectory, static fn(): array => reflectionScopeFixture(), $failureLog), 'Provider exceptions should be reported as failures.');
same(25, $failureStore->npcs[11]['extended_data']->relationships->{'Jarl Balgruuf'}->aff, 'Provider failure must not persist opinions.');
$failureSummary = null;
foreach (array_reverse($failureRecords) as $entry) {
    if (($entry['event'] ?? null) === 'request_finished') { $failureSummary = $entry; break; }
}
same('failed', $failureSummary['outcome'] ?? null, 'MP diagnostics should label provider failure as failed.');
same('model_request_failed', $failureSummary['reason'] ?? null, 'MP diagnostics should retain the fixed provider failure code.');
$failureUnavailable = array_values(array_filter(reflectionPcvLogEntries(), static fn(array $entry): bool =>
    ($entry['event'] ?? null) === 'reflection.observer_unavailable'
    && ($entry['context']['correlation']['event_id'] ?? null) === '200'
    && ($entry['context']['correlation']['utterance_id'] ?? null) === $id));
same($mpObserverSupported ? 0 : 6, count($failureUnavailable),
    'A provider-failure ACK should report observer unavailability only when the optional observer API is absent.');
$providerError = array_values(array_filter(reflectionPcvLogEntries(), static fn(array $entry): bool =>
    ($entry['event'] ?? null) === 'reflection.ack_error'
    && ($entry['reason'] ?? null) === 'evaluation_failed'
    && ($entry['context']['correlation']['event_id'] ?? null) === '200'
    && ($entry['context']['correlation']['utterance_id'] ?? null) === $id));
same(1, count($providerError), 'A returned provider failure should have one fixed PCV error with the exact ACK correlation.');
same('error', $providerError[0]['severity'] ?? null, 'A returned provider failure must not be informational.');
same(5, reflectionPcvEventCount('reflection.evaluation_finished', $id),
    'A failed provider return must not increment accepted adapter completions.');

$corruptDirectory = $testRoot . DIRECTORY_SEPARATOR . 'corrupt';
same('registered', reflectionRegister(reflectionStore($id), $corruptDirectory, $wire), 'Register corruption fixture.');
file_put_contents($corruptDirectory . DIRECTORY_SEPARATOR . 'reflection.json', '{}');
@chmod($corruptDirectory . DIRECTORY_SEPARATOR . 'reflection.json', 0600);
same('registry_corrupt', pcv_reflection_evaluate_with_store($ack, reflectionStore($id), static fn(): string => '', $corruptDirectory), 'Corrupt registry data must fail closed.');
$receiptCorruptDirectory = $testRoot . DIRECTORY_SEPARATOR . 'receipt_corrupt';
$receiptCorruptStore = reflectionStore($id);
same('registered', reflectionRegister($receiptCorruptStore, $receiptCorruptDirectory, $wire), 'Register the corrupt-receipt diagnostic fixture.');
file_put_contents($receiptCorruptDirectory . DIRECTORY_SEPARATOR . PCV_REFLECTION_RECEIPT_FILE, '{');
@chmod($receiptCorruptDirectory . DIRECTORY_SEPARATOR . PCV_REFLECTION_RECEIPT_FILE, 0600);
$receiptCorruptRecord = pcv_reflection_registry_probe($receiptCorruptDirectory)['record'];
same('receipt_corrupt', pcv_reflection_reconcile_registration(
    $receiptCorruptRecord, $receiptCorruptStore, $receiptCorruptDirectory, static fn(): array => reflectionScopeFixture()
), 'A malformed receipt ledger must fail closed with its fixed ACK error.');
$receiptCorruptLog = array_values(array_filter(reflectionPcvLogEntries(), static fn(array $entry): bool =>
    ($entry['event'] ?? null) === 'reflection.ack_error'
    && ($entry['reason'] ?? null) === 'receipt_corrupt'
    && ($entry['context']['correlation']['utterance_id'] ?? null) === $id));
same(1, count($receiptCorruptLog), 'A corrupt receipt ledger must emit one fixed ACK error record.');

$directLeaseDirectory = $testRoot . DIRECTORY_SEPARATOR . 'direct_ack_after_supersession_lease';
same('registered', reflectionRegister(reflectionStore($id), $directLeaseDirectory, $wire),
    'Register a direct-ACK compatibility fixture.');
$directLeasePath = $directLeaseDirectory . DIRECTORY_SEPARATOR . 'reflection.json';
$directLeaseRecord = json_decode((string)file_get_contents($directLeasePath), true, 16, JSON_THROW_ON_ERROR);
$directLeaseRecord['created_at'] = time() - 60;
file_put_contents($directLeasePath, json_encode($directLeaseRecord, JSON_THROW_ON_ERROR));
@chmod($directLeasePath, 0600);
$directLeaseCalls = 0;
same('committed', pcv_reflection_evaluate_with_store(
    reflectionAck($subtitle, $id), reflectionStore($id), static function () use (&$directLeaseCalls): string {
        $directLeaseCalls++;
        return validModelResponse([['subject' => 'npc:33', 'delta' => 2, 'reason' => 'The exact ACK remains eligible.', 'evidence' => 'Jarl Balgruuf betrayed me']]);
    }, $directLeaseDirectory, static fn(): array => reflectionScopeFixture()
), 'A direct exact ACK may still use the existing registry lifetime before a replacement arrives.');
same(1, $directLeaseCalls, 'A direct ACK at the supersession age must evaluate exactly once.');

$pcvPath = pcv_log_path();
$pcvLogs = is_string($pcvPath) && is_file($pcvPath) ? (string)file_get_contents($pcvPath) : '';
$mpLogs = json_encode(array_merge($mpRecords, $failureRecords), JSON_THROW_ON_ERROR);
$pcvEntries = array_map(static fn(string $line): array => json_decode($line, true, 32, JSON_THROW_ON_ERROR), array_filter(explode("\n", $pcvLogs), static fn(string $line): bool => $line !== ''));
check(!str_contains($pcvLogs . $mpLogs, $subtitle), 'PCV and MP logs must not contain raw speech.');
check(!str_contains($pcvLogs . $mpLogs, hash('sha256', $subtitle)), 'PCV and MP logs must not contain the private speech hash.');
check(is_string($claimToken) && preg_match('/\A[a-f0-9]{32}\z/D', $claimToken) === 1 && !str_contains($pcvLogs . $mpLogs, $claimToken), 'PCV and MP logs must not expose registry claim tokens.');
check(str_contains($pcvLogs, 'reflection.evaluation_finished')
    && ($mpObserverSupported ? !str_contains($pcvLogs, 'reflection.observer_unavailable') : str_contains($pcvLogs, 'reflection.observer_unavailable'))
    && str_contains($pcvLogs, 'reflection.ack_error'),
    'PCV logs should report adapter returns, optional observer availability accurately, and registry failures.');
check(!str_contains($pcvLogs, 'invalid_event'),
    'The focused registry flow must not produce a shared logger invalid_event warning.');
$moduleSource = (string)file_get_contents(__DIR__ . '/../server/reflection.php');
check(str_contains($moduleSource, "dirname(__DIR__, 2) . '/ext/mind_poisoning/reflection.php'"), 'The flat installed extension layout should resolve Mind Poisoning from the engine root.');

echo "PCV reflection registry checks passed.\n";
