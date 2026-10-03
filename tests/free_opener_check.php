<?php
declare(strict_types=1);

// 0.1.12 free mode opener: the member named earliest in the direction, else the player's direct target when it is a
// member, else the nearest member. Hand-picked groups keep named -> picker -> first.

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/state.php';
require_once dirname(__DIR__) . '/server/scope.php';

function openCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $members = ['Lidia Sobieska', 'Aela the Huntress', 'Bruce Wayne'];
    $cases = [
        'named wins over target' => [['Bruce, answer her.', $members, null, 'Aela the Huntress', true], ['Bruce Wayne', 'named']],
        'target member' => [['Nobody named.', $members, null, 'aela the huntress', true], ['Aela the Huntress', 'target']],
        'target outside' => [['Nobody named.', $members, null, 'Somebody Else', true], ['Lidia Sobieska', 'nearest']],
        'no target' => [['Nobody named.', $members, null, null, true], ['Lidia Sobieska', 'nearest']],
        'group picker' => [['Nobody named.', $members, 'Bruce Wayne', null, false], ['Bruce Wayne', 'picker']],
        'group ignores target' => [['Nobody named.', $members, null, 'Aela the Huntress', false], ['Lidia Sobieska', 'first']],
    ];
    foreach ($cases as $label => [$args, [$name, $source]]) {
        $got = pcvGroupPickOpener(...$args);
        echo $label . ': ' . json_encode($got) . "\n";
        openCheck($got === ['name' => $name, 'source' => $source], "Opener: $label.");
    }

    // The direct target comes from CHIM's decoded snapshot only when target_mode is direct.
    openCheck(pcvSnapshotDirectTarget(['listener' => 'Aela the Huntress', 'target_mode' => 'direct']) === 'Aela the Huntress', 'Direct target read.');
    openCheck(pcvSnapshotDirectTarget(['listener' => 'Aela the Huntress', 'target_mode' => 'automatic']) === null, 'Automatic is no target.');
    openCheck(pcvSnapshotDirectTarget(['target_mode' => 'direct']) === null, 'Missing listener is no target.');

    // Resolved free scopes carry free: true and allow six members; the context counts them in words.
    $known = ['1' => 'Ana', '2' => 'Bo', '3' => 'Cy', '4' => 'Di', '5' => 'Ed', '6' => 'Flo'];
    $stored = ['enabled' => true, 'scene_mode' => 'pair', 'free' => true, 'actor_a' => '1', 'actor_b' => '2',
        'actor_ids' => ['1', '2', '3', '4', '5', '6'], 'opener' => 'auto', 'exclude_player' => true, 'bystander_mode' => 'exclude'];
    $resolved = pcvResolveScopeNames($stored, $known, 'Hero');
    openCheck(is_array($resolved) && ($resolved['free'] ?? null) === true && count($resolved['members']) === 6, 'A free scope resolves six members.');
    $plain = $stored;
    unset($plain['free']);
    openCheck(pcvResolveScopeNames($plain, $known, 'Hero') === null, 'A hand-picked group of six does not resolve.');
    $context = pcvBuildScopeContext($resolved, 'Cy', '');
    echo $context . "\n";
    openCheck(str_contains($context, 'Only these six selected NPCs may take speaking turns'), 'The context counts six members in words.');

    echo "PASS free opener: named, else the direct target member, else nearest; groups unchanged\n";
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
}
