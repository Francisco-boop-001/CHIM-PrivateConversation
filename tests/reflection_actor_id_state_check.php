<?php
declare(strict_types=1);

// Regression: production state.json stores the selected actor's catalog ID ("11"), not its
// display name. ACK prechecks and receipt binding must accept the resolved name for that ID.

$GLOBALS['actorIdStateGeneration'] = 5;
function chimInteractionState(): array
{
    return ['enabled' => true, 'generation' => $GLOBALS['actorIdStateGeneration']];
}

function actorIdStateCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function actorIdStateRemoveTree(string $path): void
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
        $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($path);
}

require_once dirname(__DIR__) . '/server/reflection.php';

$directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pcv_actor_id_state_' . bin2hex(random_bytes(8));
actorIdStateCheck(mkdir($directory, 0700), 'Could not create isolated state directory.');
register_shutdown_function(static function () use ($directory): void {
    actorIdStateRemoveTree($directory);
    echo (!file_exists($directory) && !is_link($directory)) ? "cleanup=PASS\n" : "cleanup=FAIL\n";
});

$key = str_repeat('a', 64);
$knownNpcs = ['11' => 'Aela', '33' => 'Jarl Balgruuf'];

// Arm and activate exactly as the page and an eligible ordinary input do.
$staged = pcv_stage($key, [
    'enabled' => true, 'scene_mode' => 'solo', 'actor_a' => '11', 'actor_b' => null,
    'exclude_player' => true, 'bystander_mode' => 'exclude',
], $knownNpcs, $directory);
actorIdStateCheck(($staged['status'] ?? null) === 'pending', 'Solo scene was not staged.');
$active = pcv_begin_request($key, true, $directory, $knownNpcs);
actorIdStateCheck(($active['status'] ?? null) === 'active', 'Solo scene was not activated.');

$stored = json_decode((string)file_get_contents($directory . '/state.json'), true, 16, JSON_THROW_ON_ERROR);
$storedActor = $stored['active']['config']['actor_a'] ?? null;
echo 'stored_actor_a=' . var_export($storedActor, true) . "\n";
actorIdStateCheck($storedActor === '11', 'Production state must store the catalog ID.');

// The fresh, resolved scope that production builds for this state (name resolved from ID 11).
$resolvedScope = [
    'status' => 'active', 'pcv_key' => $key, 'config_id' => $active['config_id'],
    'actor_a_id' => '11',
    'scope' => ['enabled' => true, 'scene_mode' => 'solo', 'actor_a' => 'Aela', 'actor_b' => null,
        'exclude_player' => true, 'bystander_mode' => 'exclude'],
];
$ack = ['_speech', '', '', json_encode([
    'speaker' => 'Aela', 'listener' => 'Dragonborn',
    'speech' => 'I judged him too quickly.', 'utterance_id' => 'utt_actorid_state_1234',
])];

// 1. The early-ACK receipt must bind to the same ID-selected actor.
$receipt = pcv_reflection_store_ack_receipt(pcv_reflection_ack_tuple($ack), $resolvedScope, 5, $directory);
echo 'receipt_kind=' . ($receipt['kind'] ?? 'missing') . "\n";
actorIdStateCheck(($receipt['kind'] ?? null) === 'ready', 'An early ACK from the ID-selected solo actor was not stored.');

// 2. The cheap precheck reports the stored catalog ID; it must not pretend the ID is a name.
$precheck = pcv_reflection_active_solo_precheck($directory);
echo 'precheck=' . json_encode($precheck) . "\n";
actorIdStateCheck(is_array($precheck) && ($precheck['actor_id'] ?? null) === '11'
    && !array_key_exists('actor_name', $precheck),
    'The precheck must expose the stored catalog ID, never a name derived from it.');

// 2b. Stored state must still contain no display names (names are resolved from the live catalog).
actorIdStateCheck(!str_contains((string)file_get_contents($directory . '/state.json'), 'Aela'),
    'State must not persist display names.');

// 3. A different actor (ID 33) must still be rejected by the receipt binding.
$otherScope = array_replace_recursive($resolvedScope, ['actor_a_id' => '33', 'scope' => ['actor_a' => 'Jarl Balgruuf']]);
$otherAck = ['_speech', '', '', json_encode([
    'speaker' => 'Jarl Balgruuf', 'listener' => 'Dragonborn',
    'speech' => 'Not the reflecting actor.', 'utterance_id' => 'utt_actorid_other_1234',
])];
$other = pcv_reflection_store_ack_receipt(pcv_reflection_ack_tuple($otherAck), $otherScope, 5, $directory);
echo 'other_actor_kind=' . ($other['kind'] ?? 'missing') . "\n";
actorIdStateCheck(($other['kind'] ?? null) === 'scope_changed', 'A different actor must not bind to the active solo scene.');

// 4. A speaker that is not the resolved actor must be rejected even with the right scope.
$wrongSpeakerAck = ['_speech', '', '', json_encode([
    'speaker' => 'Jarl Balgruuf', 'listener' => 'Dragonborn',
    'speech' => 'Wrong speaker for this scope.', 'utterance_id' => 'utt_actorid_wrong_1234',
])];
$wrong = pcv_reflection_store_ack_receipt(pcv_reflection_ack_tuple($wrongSpeakerAck), $resolvedScope, 5, $directory);
echo 'wrong_speaker_kind=' . ($wrong['kind'] ?? 'missing') . "\n";
actorIdStateCheck(($wrong['kind'] ?? null) === 'scope_changed', 'A non-actor speaker must not bind to the active solo scene.');

echo "PASS ID-stored solo scene accepts its resolved actor and rejects others\n";
