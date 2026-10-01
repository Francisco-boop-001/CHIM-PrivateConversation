<?php
declare(strict_types=1);

define('PCV_LOG_TESTING', true);
define('CHIM_MIND_POISONING_TEST_FIXTURES_ONLY', true);
$projectRoot = dirname(__DIR__);
$mindPoisoningRoot = dirname(__DIR__, 2) . '/CHIM-MindPoisoning';
if (!is_file($mindPoisoningRoot . '/server/reflection.php')) {
    throw new RuntimeException('Sibling Mind Poisoning source was not found.');
}
require_once $mindPoisoningRoot . '/tests/runtime_test.php';
require_once $mindPoisoningRoot . '/server/reflection.php';
require_once $projectRoot . '/server/reflection.php';

function ackBugRemoveTree(string $path): void
{
    if (!is_dir($path) || is_link($path)) { return; }
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) {
        if ($item->isDir() && !$item->isLink()) { @rmdir($item->getPathname()); }
        else { @unlink($item->getPathname()); }
    }
    @rmdir($path);
}

function ackBugWriteActiveState(string $directory, array $scope): void
{
    $handle = pcv_lock_state($directory, true, LOCK_EX);
    if (!is_resource($handle)) {
        throw new RuntimeException('Could not lock the active-state fixture.');
    }
    try {
        $now = time();
        $state = pcv_empty_store($scope['pcv_key']);
        $state['active'] = [
            'config' => [
                'enabled' => true,
                'scene_mode' => 'solo',
                'actor_a' => $scope['scope']['actor_a'],
                'actor_b' => null,
                'exclude_player' => true,
                'bystander_mode' => 'exclude',
            ],
            'config_id' => $scope['config_id'],
            'activated_at' => $now,
            'expires_at' => $now + PCV_ACTIVE_TTL,
        ];
        pcv_write_store($directory, $state);
    } finally {
        pcv_unlock_state($handle);
    }
}

$fixtureRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pcv-ack-boundary-' . bin2hex(random_bytes(6));
$stateDirectory = $fixtureRoot . DIRECTORY_SEPARATOR . 'state';
$logDirectory = $fixtureRoot . DIRECTORY_SEPARATOR . 'logs';
$engineRoot = $fixtureRoot . DIRECTORY_SEPARATOR . 'engine';
mkdir($fixtureRoot, 0700, true);
mkdir($logDirectory, 0700);
mkdir($engineRoot . DIRECTORY_SEPARATOR . 'data', 0700, true);
file_put_contents($engineRoot . DIRECTORY_SEPARATOR . 'main.php', "<?php\n");
if (!pcv_log_set_test_directory($logDirectory)) {
    throw new RuntimeException('Could not isolate PCV diagnostics.');
}

$configId = '123e4567-e89b-42d3-a456-426614174000';
$utteranceId = 'utt_abcdef0123456789';
$speech = 'Jarl Balgruuf betrayed me.';
$wire = 'Aela|ScriptQueue|' . $speech . '/neutral/explicit_disable_rechat/none/phonetic/1/explicit_disable_rechat/' . $utteranceId . "\r\n";
$scope = [
    'status' => 'active', 'pcv_key' => str_repeat('a', 64), 'config_id' => $configId, 'actor_a_id' => '11',
    'scope' => ['scene_mode' => 'solo', 'actor_a' => 'Aela', 'actor_b' => null, 'exclude_player' => true],
];
$requestScope = [
    'status' => 'active', 'config_id' => $configId, 'actor_a_id' => '11',
    'scope' => ['scene_mode' => 'solo', 'actor_a' => 'Aela', 'actor_b' => null, 'exclude_player' => true],
    'origin_request_type' => 'inputtext', 'origin_mode' => 'STANDARD', 'origin_dialogue' => 'What do you think?',
    'route' => 'solo_reflection', 'baseline_utterance_id' => 'utt_baseline12345678',
    'baseline_output_log' => 'Aela|ScriptQueue|previous/neutral/explicit_disable_rechat/none/phonetic/1/explicit_disable_rechat/utt_baseline12345678' . "\r\n",
];
$fixture = baseFixture();
$store = $fixture[3];
$store->events[200] = [
    'event_id' => 200, 'utterance_id' => $utteranceId, 'delivery_state' => 'emitted',
    'source_data' => 'Aela: ' . $speech . ' (Talking to explicit_disable_rechat)', 'gamets' => 30.0,
];
$store->npcs = [
    11 => ['id' => 11, 'npc_name' => 'Aela', 'extended_data' => (object)['relationships' => new stdClass()], 'plugin_extended_data' => new stdClass()],
    33 => ['id' => 33, 'npc_name' => 'Jarl Balgruuf', 'extended_data' => (object)['relationships' => new stdClass()], 'plugin_extended_data' => new stdClass()],
];
$GLOBALS['ENGINE_PATH'] = $engineRoot . DIRECTORY_SEPARATOR;
$GLOBALS['CHIM_EXECUTION_MODE'] = 'STANDARD';
$GLOBALS['PLAYER_RESPEECH'] = true;
$GLOBALS['HERIKA_NAME'] = 'Aela';
$GLOBALS['SCRIPTLINE_UTTERANCE_ID'] = $utteranceId;
$GLOBALS['DEBUG_DATA'] = ['OUTPUT_LOG' => $wire];
$GLOBALS['RELLLM_CONNECTOR'] = 7;
$GLOBALS['NEVER_CLEAR_RELATIONSHIP_DATA'] = false;
resetInteractionTestRequest(1, 1, true, false);
ackBugWriteActiveState($stateDirectory, $scope);

$registered = pcv_reflection_register_with_store(
    $requestScope, $store, $stateDirectory, static fn(): array => $scope
);
if ($registered !== 'registered') {
    throw new RuntimeException('Source registration fixture failed: ' . $registered);
}
$ack = ['_speech', '', '', json_encode([
    'speaker' => 'Aela', 'listener' => 'Dragonborn', 'speech' => $speech, 'utterance_id' => $utteranceId,
], JSON_THROW_ON_ERROR)];
$tuple = pcv_reflection_ack_tuple($ack);
if (!is_array($tuple)) {
    throw new RuntimeException('The ACK tuple fixture was rejected.');
}
$receiptResult = pcv_reflection_store_ack_receipt($tuple, $scope, 1, $stateDirectory);
if (($receiptResult['kind'] ?? null) !== 'ready') {
    throw new RuntimeException('Receipt setup failed: ' . ($receiptResult['kind'] ?? 'missing result'));
}
$receiptPath = $stateDirectory . DIRECTORY_SEPARATOR . PCV_REFLECTION_RECEIPT_FILE;
$receiptFile = json_decode((string)file_get_contents($receiptPath), true, 16, JSON_THROW_ON_ERROR);
$receiptFile['receipts'][0]['created_at'] = time() - PCV_REFLECTION_RECEIPT_TTL + 1;
file_put_contents($receiptPath, json_encode($receiptFile, JSON_THROW_ON_ERROR));
@chmod($receiptPath, 0600);
$receipt = $receiptFile['receipts'][0];
$ageBefore = time() - $receipt['created_at'];
$freshCalls = 0;
$slowScopeReader = static function () use (&$freshCalls, $scope): array {
    if ($freshCalls++ === 0) { usleep(2_100_000); }
    return $scope;
};
$modelCalls = 0;
$requestModel = static function (array $messages) use (&$modelCalls): never {
    $modelCalls++;
    throw new RuntimeException('Stop after recording the model boundary.');
};
$requestLog = new \ChimMindPoisoning\RequestLog(static function (string $json, string $level): void {}, false);

$result = pcv_reflection_evaluate_with_store(
    $ack, $store, $requestModel, $stateDirectory, $slowScopeReader, $requestLog, $receipt
);
$ageAfter = time() - $receipt['created_at'];
$registry = pcv_reflection_registry_probe($stateDirectory);
$status = $registry['record']['status'] ?? 'unavailable';
printf("ACK_BUG_REPRO start_age=%d end_age=%d ttl=%d model_calls=%d result=%s registry_status=%s\n",
    $ageBefore, $ageAfter, PCV_REFLECTION_RECEIPT_TTL, $modelCalls, $result, $status);

$confirmed = $ageBefore <= PCV_REFLECTION_RECEIPT_TTL
    && $ageAfter > PCV_REFLECTION_RECEIPT_TTL
    && $modelCalls === 0
    && $status === 'registered';
echo $confirmed ? "Expired unclaimed receipt was rejected before model work.\n" : "Expired unclaimed receipt check failed.\n";

$replacementStored = pcv_reflection_store_ack_receipt($tuple, $scope, 1, $stateDirectory);
$replacementReceipt = $replacementStored['receipt'] ?? null;
$replacementCalls = 0;
$replacedTimestamp = false;
$replacementScopeReader = static function () use (&$replacedTimestamp, $receiptPath, $utteranceId, $scope): array {
    if (!$replacedTimestamp) {
        $data = json_decode((string)file_get_contents($receiptPath), true, 16, JSON_THROW_ON_ERROR);
        foreach ($data['receipts'] as &$stored) {
            if ($stored['utterance_id'] === $utteranceId) {
                $stored['created_at'] = max(1, $stored['created_at'] - 1);
            }
        }
        unset($stored);
        file_put_contents($receiptPath, json_encode($data, JSON_THROW_ON_ERROR));
        @chmod($receiptPath, 0600);
        $replacedTimestamp = true;
    }
    return $scope;
};
$replacementModel = static function (array $messages) use (&$replacementCalls): never {
    $replacementCalls++;
    throw new RuntimeException('A replaced receipt timestamp must stop before model work.');
};
$replacementResult = is_array($replacementReceipt)
    ? pcv_reflection_evaluate_with_store(
        $ack, $store, $replacementModel, $stateDirectory, $replacementScopeReader, $requestLog, $replacementReceipt
    )
    : 'receipt_setup_failed';
$replacementRegistry = pcv_reflection_registry_probe($stateDirectory);
$replacementStatus = $replacementRegistry['record']['status'] ?? 'unavailable';
printf("ACK_TIMESTAMP_REPRO stored=%s changed=%s model_calls=%d result=%s registry_status=%s\n",
    $replacementStored['kind'] ?? 'missing', $replacedTimestamp ? 'true' : 'false',
    $replacementCalls, $replacementResult, $replacementStatus);
$timestampBound = ($replacementStored['kind'] ?? null) === 'ready'
    && $replacedTimestamp && $replacementResult === 'registration_stale'
    && $replacementCalls === 0 && $replacementStatus === 'registered';
echo $timestampBound ? "Replaced pre-claim receipt timestamp was rejected.\n" : "Receipt timestamp binding check failed.\n";
ackBugRemoveTree($fixtureRoot);
if (!$confirmed || !$timestampBound) {
    exit(1);
}
