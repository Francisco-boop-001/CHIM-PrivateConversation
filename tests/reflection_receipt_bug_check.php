<?php
declare(strict_types=1);

$GLOBALS['receiptBugCurrentGeneration'] = 5;
function chimInteractionState(): array
{
    return ['enabled' => true, 'generation' => $GLOBALS['receiptBugCurrentGeneration']];
}

function receiptBugCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function receiptBugRemoveTree(string $path): void
{
    $root = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (!str_starts_with($path, $root) || !is_dir($path) || is_link($path)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isLink()) {
            @unlink($item->getPathname());
        } elseif ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }
    @rmdir($path);
}

function receiptBugWriteActiveState(string $directory, array $scope): void
{
    $handle = pcv_lock_state($directory, true, LOCK_EX);
    receiptBugCheck(is_resource($handle), 'Could not lock the active-state fixture.');
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

function receiptBugScopePruneCase(string $root, string $label, array $currentScope, array $incomingScope): array
{
    $directory = $root . DIRECTORY_SEPARATOR . $label;
    receiptBugWriteActiveState($directory, $currentScope);
    $currentTuple = [
        'utterance_id' => 'utt_current_' . $label . '1234',
        'speaker' => $currentScope['scope']['actor_a'],
        'listener' => 'Dragonborn',
        'speech' => 'current valid ' . $label . ' receipt',
    ];
    $incomingTuple = [
        'utterance_id' => 'utt_old_' . $label . '123456',
        'speaker' => $incomingScope['scope']['actor_a'],
        'listener' => 'Dragonborn',
        'speech' => 'delayed stale ' . $label . ' receipt',
    ];
    $current = pcv_reflection_store_ack_receipt($currentTuple, $currentScope, 5, $directory);
    $receiptPath = $directory . DIRECTORY_SEPARATOR . PCV_REFLECTION_RECEIPT_FILE;
    $before = (string)file_get_contents($receiptPath);
    $incoming = pcv_reflection_store_ack_receipt($incomingTuple, $incomingScope, 5, $directory);
    $after = (string)file_get_contents($receiptPath);
    $handle = pcv_lock_state($directory, false, LOCK_SH);
    receiptBugCheck(is_resource($handle), 'Could not inspect the scope-pruning fixture.');
    $currentCount = 0;
    try {
        $loaded = pcv_reflection_read_receipts_locked($directory);
        receiptBugCheck(($loaded['kind'] ?? null) === 'ready', 'Scope-pruning receipt state became unreadable.');
        foreach ($loaded['receipts'] as $receipt) {
            if ($receipt['utterance_id'] === $currentTuple['utterance_id']
                && pcv_reflection_receipt_fresh($receipt)) {
                $currentCount++;
            }
        }
    } finally {
        pcv_unlock_state($handle);
    }
    return [
        'current_kind' => $current['kind'] ?? 'missing',
        'incoming_kind' => $incoming['kind'] ?? 'missing',
        'current_count' => $currentCount,
        'ledger_unchanged' => hash_equals($before, $after),
    ];
}

require_once dirname(__DIR__) . '/server/reflection.php';

$directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pcv_receipt_generation_race_' . bin2hex(random_bytes(8));
receiptBugCheck(mkdir($directory, 0700), 'Could not create isolated receipt directory.');
register_shutdown_function(static function () use ($directory): void {
    receiptBugRemoveTree($directory);
    echo (!file_exists($directory) && !is_link($directory)) ? "cleanup=PASS\n" : "cleanup=FAIL\n";
});

$scope = [
    'pcv_key' => str_repeat('a', 64),
    'config_id' => '123e4567-e89b-42d3-a456-426614174000',
    'actor_a_id' => '11',
    'scope' => ['actor_a' => 'Aela'],
];
receiptBugWriteActiveState($directory, $scope);
$generationFiveTuple = [
    'utterance_id' => 'utt_generation5_123456',
    'speaker' => 'Aela',
    'listener' => 'Dragonborn',
    'speech' => 'fresh generation five evidence',
];
$capturedGenerationFourTuple = [
    'utterance_id' => 'utt_generation4_123456',
    'speaker' => 'Aela',
    'listener' => 'Dragonborn',
    'speech' => 'previously captured generation four evidence',
];

receiptBugCheck(pcv_reflection_interaction_epochs_match(5, 5), 'Generation five should be current before the interleaving.');
$newReceipt = pcv_reflection_store_ack_receipt($generationFiveTuple, $scope, 5, $directory);
receiptBugCheck(($newReceipt['kind'] ?? null) === 'ready', 'Could not store the current generation five receipt.');

// Model a request that captured G4 before it paused, then resumes after G5 is current.
$GLOBALS['chim_interaction_generation'] = 4;
receiptBugCheck(!pcv_reflection_interaction_epochs_match(5, $GLOBALS['chim_interaction_generation']),
    'The delayed G4 request must be stale against current generation five.');
$receiptPath = $directory . DIRECTORY_SEPARATOR . PCV_REFLECTION_RECEIPT_FILE;
$generationFiveBytes = (string)file_get_contents($receiptPath);
$oldReceipt = pcv_reflection_store_ack_receipt($capturedGenerationFourTuple, $scope, 4, $directory);
$generationBytesUnchanged = hash_equals($generationFiveBytes, (string)file_get_contents($receiptPath));

$handle = pcv_lock_state($directory, false, LOCK_SH);
receiptBugCheck(is_resource($handle), 'Could not inspect production receipt state.');
$generationFiveCount = 0;
$generationFourCount = 0;
$generationFiveFresh = false;
try {
    $loaded = pcv_reflection_read_receipts_locked($directory);
    receiptBugCheck(($loaded['kind'] ?? null) === 'ready', 'Production receipt state became unreadable.');
    foreach ($loaded['receipts'] as $receipt) {
        if ($receipt['utterance_id'] === $generationFiveTuple['utterance_id'] && $receipt['ack_generation'] === 5) {
            $generationFiveCount++;
            $generationFiveFresh = pcv_reflection_receipt_fresh($receipt);
        }
        if ($receipt['utterance_id'] === $capturedGenerationFourTuple['utterance_id'] && $receipt['ack_generation'] === 4) {
            $generationFourCount++;
        }
    }
} finally {
    pcv_unlock_state($handle);
}

receiptBugCheck(pcv_reflection_interaction_epochs_match(5, 5), 'Current generation five must remain active after the delayed G4 write.');
echo 'stale_write_kind=' . ($oldReceipt['kind'] ?? 'missing') . "\n";
echo "current_generation=5\n";
echo 'current_G5_receipt_count=' . $generationFiveCount . "\n";
echo 'captured_G4_receipt_count=' . $generationFourCount . "\n";
$generationPreserved = ($oldReceipt['kind'] ?? null) === 'interaction_stale'
    && $generationFiveCount === 1 && $generationFiveFresh && $generationBytesUnchanged;
echo 'generation_ledger_unchanged=' . ($generationBytesUnchanged ? 'true' : 'false') . "\n";
$scopeCasesPassed = true;
foreach ([
    'config' => array_replace_recursive($scope, ['config_id' => '123e4567-e89b-42d3-a456-426614174001']),
    'key' => array_replace($scope, ['pcv_key' => str_repeat('b', 64)]),
    'actor' => array_replace_recursive($scope, ['scope' => ['actor_a' => 'Serana']]),
] as $label => $staleScope) {
    $scopeResult = receiptBugScopePruneCase($directory, $label, $scope, $staleScope);
    echo $label . '_scope_stale_kind=' . $scopeResult['incoming_kind']
        . ' current_receipt_count=' . $scopeResult['current_count']
        . ' ledger_unchanged=' . ($scopeResult['ledger_unchanged'] ? 'true' : 'false') . "\n";
    $scopeCasesPassed = $scopeCasesPassed && $scopeResult['current_kind'] === 'ready'
        && $scopeResult['incoming_kind'] === 'scope_changed'
        && $scopeResult['current_count'] === 1 && $scopeResult['ledger_unchanged'];
}

$actorIdDirectory = $directory . DIRECTORY_SEPARATOR . 'actor_id_remap';
$actorIdScope = $scope;
receiptBugWriteActiveState($actorIdDirectory, $scope);
$priorActorIdReceipt = pcv_reflection_store_ack_receipt([
    'utterance_id' => 'utt_prior_actorid_123456', 'speaker' => 'Aela',
    'listener' => 'Dragonborn', 'speech' => 'prior actor id receipt',
], $scope, 5, $actorIdDirectory);
$actorIdScope['actor_a_id'] = '12';
$newActorIdReceipt = pcv_reflection_store_ack_receipt([
    'utterance_id' => 'utt_new_actorid_123456', 'speaker' => 'Aela',
    'listener' => 'Dragonborn', 'speech' => 'new actor id receipt',
], $actorIdScope, 5, $actorIdDirectory);
$actorIdLedger = json_decode((string)file_get_contents($actorIdDirectory . DIRECTORY_SEPARATOR . PCV_REFLECTION_RECEIPT_FILE), true, 16, JSON_THROW_ON_ERROR);
$priorActorIdCount = count(array_filter($actorIdLedger['receipts'], static fn(array $receipt): bool =>
    $receipt['utterance_id'] === 'utt_prior_actorid_123456'));
echo 'actor_id_remap_new_kind=' . ($newActorIdReceipt['kind'] ?? 'missing')
    . ' prior_receipt_count=' . $priorActorIdCount . "\n";
$scopeCasesPassed = $scopeCasesPassed
    && ($priorActorIdReceipt['kind'] ?? null) === 'ready'
    && ($newActorIdReceipt['kind'] ?? null) === 'ready'
    && $priorActorIdCount === 1;

foreach (['missing', 'corrupt'] as $stateFailure) {
    $failureDirectory = $directory . DIRECTORY_SEPARATOR . 'state_' . $stateFailure;
    receiptBugWriteActiveState($failureDirectory, $scope);
    $baselineReceipt = pcv_reflection_store_ack_receipt([
        'utterance_id' => 'utt_baseline_' . $stateFailure . '1234', 'speaker' => 'Aela',
        'listener' => 'Dragonborn', 'speech' => 'baseline state failure receipt',
    ], $scope, 5, $failureDirectory);
    $statePath = $failureDirectory . DIRECTORY_SEPARATOR . 'state.json';
    $stateHandle = pcv_lock_state($failureDirectory, false, LOCK_EX);
    receiptBugCheck(is_resource($stateHandle), 'Could not lock the state-failure fixture.');
    try {
        if ($stateFailure === 'missing') {
            unlink($statePath);
        } else {
            file_put_contents($statePath, '{}');
            chmod($statePath, 0600);
        }
    } finally {
        pcv_unlock_state($stateHandle);
    }
    $failureLedgerPath = $failureDirectory . DIRECTORY_SEPARATOR . PCV_REFLECTION_RECEIPT_FILE;
    $failureBytesBefore = (string)file_get_contents($failureLedgerPath);
    $rejectedReceipt = pcv_reflection_store_ack_receipt([
        'utterance_id' => 'utt_rejected_' . $stateFailure . '1234', 'speaker' => 'Aela',
        'listener' => 'Dragonborn', 'speech' => 'must not write on state failure',
    ], $scope, 5, $failureDirectory);
    $failureBytesUnchanged = hash_equals($failureBytesBefore, (string)file_get_contents($failureLedgerPath));
    $expectedKind = $stateFailure === 'missing' ? 'scope_changed' : 'unavailable';
    echo 'state_' . $stateFailure . '_kind=' . ($rejectedReceipt['kind'] ?? 'missing')
        . ' ledger_unchanged=' . ($failureBytesUnchanged ? 'true' : 'false') . "\n";
    $scopeCasesPassed = $scopeCasesPassed
        && ($baselineReceipt['kind'] ?? null) === 'ready'
        && ($rejectedReceipt['kind'] ?? null) === $expectedKind && $failureBytesUnchanged;
}
receiptBugCheck($generationPreserved && $scopeCasesPassed,
    'A delayed stale request must not remove fresh receipts for the current interaction/scope.');
echo "PASS current G5 receipt survives delayed captured G4 write\n";
