<?php
declare(strict_types=1);

// 0.1.12 free mode: a pending free scene has no members (chosen at activation); an active free scene is a group of
// up to six members carrying free: true; the player is always excluded.

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/state.php';

function cfgCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $pending = ['enabled' => true, 'scene_mode' => 'pair', 'free' => true, 'exclude_player' => true, 'bystander_mode' => 'exclude'];
    cfgCheck(pcv_valid_config($pending, true) && pcv_valid_config($pending, false), 'A pending free scene has no members and is valid.');
    cfgCheck(!pcv_valid_config(array_replace($pending, ['exclude_player' => false]), true), 'A free scene always excludes the player.');
    cfgCheck(!pcv_valid_config(array_replace($pending, ['free' => 'yes']), true), 'free must be boolean true.');
    cfgCheck(!pcv_valid_config(array_replace($pending, ['scene_mode' => 'solo']), true), 'Solo is never free.');
    cfgCheck(pcv_config_actor_ids($pending) === [], 'A pending free scene has no members yet.');
    cfgCheck(!pcv_valid_config($pending + ['actor_a' => '1'], false), 'A half-filled free scene is invalid.');

    $ids = ['1', '2', '3', '4', '5', '6'];
    $active = ['enabled' => true, 'scene_mode' => 'pair', 'free' => true, 'actor_a' => '1', 'actor_b' => '2', 'actor_ids' => $ids,
        'opener' => 'auto', 'exclude_player' => true, 'bystander_mode' => 'silent'];
    cfgCheck(pcv_valid_config($active, false), 'An active free scene may hold six members.');
    cfgCheck(pcv_config_actor_ids($active) === $ids, 'Active free members are the actor_ids.');
    cfgCheck(!pcv_valid_config(array_replace($active, ['actor_ids' => [...$ids, '7']]), false), 'Seven members is too many.');
    $plain = $active;
    unset($plain['free']);
    cfgCheck(!pcv_valid_config($plain, false), 'Without free, groups stay capped at four.');

    $normalized = pcv_normalize_config(['enabled' => true, 'scene_mode' => 'pair', 'free' => true, 'actor_a' => '1',
        'exclude_player' => false, 'bystander_mode' => 'silent'], ['1' => 'Aela']);
    echo json_encode($normalized) . "\n";
    cfgCheck($normalized === ['enabled' => true, 'scene_mode' => 'pair', 'free' => true, 'exclude_player' => true, 'bystander_mode' => 'silent'],
        'Staging a free scene ignores pickers and forces the player out.');

    $state = ['version' => PCV_STATE_VERSION, 'key' => hash('sha256', 'k'), 'pending' => null, 'active' => [
        'config' => $active, 'config_id' => null, 'activated_at' => 100, 'expires_at' => 200,
        'dropped' => [['id' => '7', 'reason' => 'not_eligible_at_start']],
    ]];
    cfgCheck(pcv_valid_stored_state($state), 'An active free scene with a dropped entry is a valid stored state.');
    echo "PASS free configs: pending without members, active up to six, player always excluded\n";
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
}
