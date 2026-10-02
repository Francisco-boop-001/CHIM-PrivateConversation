<?php
declare(strict_types=1);

// Live finding (2026-10-02): solo reflections ran to 10-13 ScriptQueue lines, above Mind Poisoning's
// 8-line reply cap, so full-reply registration fell back to the final line. And every earlier line's ACK
// was parked as a pending receipt, filling the 8-slot map (receipt_busy).

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

function replyLengthCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

// 1. Solo guidance asks for a reflection short enough to fit one evaluable reply.
$solo = ['enabled' => true, 'scene_mode' => 'solo', 'actor_a' => 'Lidia', 'actor_b' => null,
    'exclude_player' => true, 'bystander_mode' => 'exclude'];
$context = pcvBuildScopeContext($solo, 'Lidia', '');
echo "solo_context=$context\n";
replyLengthCheck(preg_match('/at most (five|5) sentences/i', $context) === 1,
    'Solo guidance must ask for a short reflection (at most five sentences).');
$pair = ['enabled' => true, 'scene_mode' => 'pair', 'actor_a' => 'Lidia', 'actor_b' => 'Aela',
    'exclude_player' => true, 'bystander_mode' => 'exclude'];
replyLengthCheck(preg_match('/sentences/i', pcvBuildScopeContext($pair, 'Lidia', 'Aela')) !== 1,
    'Pair guidance is unchanged.');

// 2. An ACK for a line older than the registered final line belongs to that reply (or an earlier one).
$store = new \MemoryStoreDb();
foreach ([[110, 'utt_earlier_line_0001'], [120, 'utt_final_line_000001'], [130, 'utt_newer_line_000001']] as [$id, $utt]) {
    $store->events[] = ['event_id' => $id, 'utterance_id' => $utt, 'delivery_state' => 'emitted',
        'source_data' => 'Lidia: text (talking to explicit_disable_rechat)', 'gamets' => 1, 'type' => 'chat'];
}
$record = ['registration' => ['event_id' => 120, 'utterance_id' => 'utt_final_line_000001']];
$older = pcv_reflection_ack_predates_registration($store, $record, 'utt_earlier_line_0001');
$newer = pcv_reflection_ack_predates_registration($store, $record, 'utt_newer_line_000001');
$unknown = pcv_reflection_ack_predates_registration($store, $record, 'utt_unknown_line_0001');
echo 'older=' . var_export($older, true) . ' newer=' . var_export($newer, true) . ' unknown=' . var_export($unknown, true) . "\n";
replyLengthCheck($older === true, 'An earlier line of the registered reply must be recognised as older.');
replyLengthCheck($newer === false, 'A newer line may be an early ACK for the next reply and must keep the receipt path.');
replyLengthCheck($unknown === false, 'An unknown utterance must keep the existing path.');

echo "PASS solo replies are asked to stay short and older-line ACKs are recognised\n";
