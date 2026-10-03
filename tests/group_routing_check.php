<?php
declare(strict_types=1);

// 0.1.11 group mode: audience, rechat speakers, allowed speakers, listener choices and scene guidance cover every
// member and nobody else. Two-member scenes keep their pair wording.

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/state.php';
require_once dirname(__DIR__) . '/server/scope.php';

function rtCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $trio = ['enabled' => true, 'scene_mode' => 'pair', 'actor_a' => 'Stalleo', 'actor_b' => 'Guard One',
        'members' => ['Stalleo', 'Guard One', 'Guard Two'], 'opener' => null, 'exclude_player' => true, 'bystander_mode' => 'exclude'];
    $snapshot = pcvScopeRoutingSnapshot(['audience' => '|x|', 'present_actors' => [1]], $trio, 'Hawke');
    echo 'audience=' . $snapshot['audience'] . "\n";
    rtCheck($snapshot['audience'] === '|Stalleo|Guard One|Guard Two|' && $snapshot['present_actors'] === [],
        'The audience is exactly the members.');
    $withPlayer = pcvScopeRoutingSnapshot([], array_replace($trio, ['exclude_player' => false]), 'Hawke');
    rtCheck($withPlayer['audience'] === '|Hawke|Stalleo|Guard One|Guard Two|', 'An included player joins the member audience.');

    $reason = null;
    $clamped = pcvClampRechatActiveAgents(json_encode(['speaker' => 'Guard One', 'active_agents' => ['Stalleo', 'Hawke', 'Bystander']]),
        $trio, 'Guard One', $reason);
    $decoded = json_decode((string)$clamped, true);
    echo 'clamped=' . json_encode($decoded['active_agents'] ?? null) . "\n";
    rtCheck(($decoded['active_agents'] ?? null) === ['Stalleo', 'Guard One', 'Guard Two'], 'Rechat speakers are clamped to the members.');
    $reason = null;
    rtCheck(pcvClampRechatActiveAgents(json_encode(['speaker' => 'Bystander']), $trio, null, $reason) === null
        && $reason === 'rechat_speaker_outside_scene', 'A rechat speaker outside the members is refused.');

    rtCheck(pcvScopeSpeakerAllowed('guard two', $trio) && !pcvScopeSpeakerAllowed('Bystander', $trio), 'Only members may speak.');
    rtCheck(pcvGroupListeners('Guard One', $trio) === ['Stalleo', 'Guard Two'], 'Listeners are the other members, in order.');

    $context = pcvBuildScopeContext($trio, 'Guard One', '');
    echo 'context=' . $context . "\n";
    rtCheck(str_contains($context, 'Stalleo, Guard One and Guard Two') && str_contains($context, 'Only these three selected NPCs may take speaking turns')
        && str_contains($context, 'Do not address, include, quote, or narrate the player'), 'Group guidance names all members.');

    $pair = ['enabled' => true, 'scene_mode' => 'pair', 'actor_a' => 'Lidia', 'actor_b' => 'Bruce', 'members' => ['Lidia', 'Bruce'],
        'opener' => 'Lidia', 'exclude_player' => true, 'bystander_mode' => 'exclude'];
    rtCheck(str_contains(pcvBuildScopeContext($pair, 'Lidia', 'Bruce'), 'Lidia is speaking with Bruce. Only these two selected NPCs'),
        'Two-member scenes keep the pair wording.');
    rtCheck(pcvGroupListeners('Lidia', $pair) === ['Bruce'], 'A pair has exactly one listener.');
    $legacyResolved = ['enabled' => true, 'scene_mode' => 'pair', 'actor_a' => 'Lidia', 'actor_b' => 'Bruce', 'exclude_player' => true, 'bystander_mode' => 'exclude'];
    rtCheck(pcvScopeRoutingSnapshot([], $legacyResolved, 'Hawke')['audience'] === '|Lidia|Bruce|', 'A scope resolved without members keeps A/B routing.');
    echo "PASS group routing covers every member and nobody else; pairs keep their wording\n";
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
}
