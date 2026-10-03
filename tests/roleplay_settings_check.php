<?php
declare(strict_types=1);

// 0.1.14 G4 scene card, G5 turn length, G7 free-scene size: stored, validated, resolved and put into every scene
// turn's context. Guidance always adds to, and never replaces, an NPC's own condition (SHARMAT compatibility).

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/state.php';
require_once dirname(__DIR__) . '/server/scope.php';

function rsCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$stateDirectory = sys_get_temp_dir() . '/pcv-roleplay-settings-' . bin2hex(random_bytes(8));
$logDirectory = sys_get_temp_dir() . '/pcv-roleplay-settings-log-' . bin2hex(random_bytes(8));
$exitCode = 0;
try {
    rsCheck(mkdir($logDirectory, 0700) && pcv_log_set_test_directory($logDirectory), 'logger fixture');
    $known = ['11' => 'A', '12' => 'B', '13' => 'C', '14' => 'D'];
    $pair = ['enabled' => true, 'scene_mode' => 'pair', 'actor_a' => '11', 'actor_b' => '12', 'exclude_player' => true, 'bystander_mode' => 'exclude'];
    $card = 'Late night at the Bannered Mare. Tense. The treaty is on the table.';

    // Validation.
    rsCheck(pcv_valid_config($pair + ['card' => $card, 'pace' => 'short'], false), 'A card and a pace are valid.');
    rsCheck(!pcv_valid_config($pair + ['card' => str_repeat('x', 301)], false), 'Cards are capped at 300 characters.');
    rsCheck(!pcv_valid_config($pair + ['card' => "two\nlines"], false), 'Cards carry no control characters.');
    rsCheck(!pcv_valid_config($pair + ['card' => ''], false), 'An empty card is omitted, not stored.');
    rsCheck(!pcv_valid_config($pair + ['pace' => 'fast'], false), 'Unknown paces are rejected.');
    rsCheck(!pcv_valid_config($pair + ['pace' => 'normal'], false), 'Normal pace is the absence of the field.');
    $free = ['enabled' => true, 'scene_mode' => 'pair', 'free' => true, 'exclude_player' => true, 'bystander_mode' => 'exclude'];
    rsCheck(pcv_valid_config($free + ['free_cap' => 3], true), 'A free cap of 3 is valid.');
    rsCheck(!pcv_valid_config($free + ['free_cap' => 7], true) && !pcv_valid_config($free + ['free_cap' => 1], true), 'Free caps run 2-6.');
    rsCheck(!pcv_valid_config($pair + ['free_cap' => 3], false), 'Only free scenes carry a cap.');

    // Normalization from the page's desired state.
    $n = pcv_normalize_config($pair + ['card' => "  {$card}  ", 'pace' => 'long'], $known);
    rsCheck(($n['card'] ?? null) === $card && ($n['pace'] ?? null) === 'long', 'Normalization trims the card and keeps the pace.');
    $n = pcv_normalize_config($pair + ['card' => '   ', 'pace' => 'normal'], $known);
    rsCheck(!array_key_exists('card', $n) && !array_key_exists('pace', $n), 'Blank card and normal pace are omitted.');
    $n = pcv_normalize_config(['enabled' => true, 'scene_mode' => 'pair', 'free' => true, 'free_cap' => 3, 'card' => $card,
        'exclude_player' => true, 'bystander_mode' => 'exclude'], $known);
    rsCheck(($n['free_cap'] ?? null) === 3 && ($n['card'] ?? null) === $card, 'Free scenes keep their cap and card.');

    // A free scene honors its cap at activation.
    $key = hash('sha256', 'roleplay settings');
    pcv_stage($key, $n, $known, $stateDirectory);
    pcv_begin_request($key, true, $stateDirectory, $known, null, null, ['14', '13', '12', '11']);
    $stored = json_decode((string)file_get_contents($stateDirectory . '/state.json'), true);
    rsCheck(($stored['active']['config']['actor_ids'] ?? null) === ['14', '13', '12'], 'The nearest three join a free scene capped at 3.');

    // Resolution and context.
    $resolved = pcvResolveScopeNames($pair + ['card' => $card, 'pace' => 'short'], $known, 'Hero');
    rsCheck(($resolved['card'] ?? null) === $card && ($resolved['pace'] ?? null) === 'short', 'Resolved scopes carry card and pace.');
    $context = pcvBuildScopeContext($resolved, 'A', 'B');
    echo $context . "\n";
    rsCheck(str_contains($context, 'Scene: ' . $card), 'The card is in the context.');
    rsCheck(str_contains($context, 'Keep each reply to one or two sentences.'), 'Short pace is in the context.');
    rsCheck(str_contains($context, 'This adds to, and never replaces, your own condition and way of speaking.'), 'The SHARMAT ground rule is stated.');
    $plain = pcvBuildScopeContext(pcvResolveScopeNames($pair, $known, 'Hero'), 'A', 'B');
    rsCheck(!str_contains($plain, 'Scene:') && !str_contains($plain, 'never replaces'), 'No card, no pace: the context is unchanged.');
    $long = pcvBuildScopeContext(pcvResolveScopeNames($pair + ['pace' => 'long'], $known, 'Hero'), 'A', 'B');
    rsCheck(str_contains($long, 'You may speak at length, up to six sentences.'), 'Long pace is in the context.');
    $solo = ['enabled' => true, 'scene_mode' => 'solo', 'actor_a' => '11', 'actor_b' => null, 'exclude_player' => true,
        'bystander_mode' => 'exclude', 'card' => $card];
    rsCheck(pcv_valid_config($solo, false), 'Solo may carry a card.');
    rsCheck(!pcv_valid_config($solo + ['pace' => 'long'], false), 'Solo never carries a pace.');
    $soloContext = pcvBuildScopeContext(pcvResolveScopeNames($solo, $known, 'Hero'), 'A', '');
    rsCheck(str_contains($soloContext, 'Scene: ' . $card) && str_contains($soloContext, 'at most five sentences'), 'Solo frames the reflection and keeps its length rule.');

    echo "PASS scene card, turn length and free size are stored, validated and added to every turn without overriding conditions\n";
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
