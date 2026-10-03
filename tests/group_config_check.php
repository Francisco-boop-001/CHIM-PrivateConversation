<?php
declare(strict_types=1);

// 0.1.11 group mode: non-solo configs carry 2-4 ordered members (actor_ids) and an opener; actor_a/actor_b mirror
// the first two members; legacy pairs and solo stay valid.

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/state.php';

function cfgCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$known = ['101' => 'Aela', '202' => 'Lydia', '303' => 'Bruce Wayne', '404' => 'Stalleo', '505' => 'Treva Guard'];
try {
    $legacy = ['enabled' => true, 'scene_mode' => 'pair', 'actor_a' => '101', 'actor_b' => '202', 'exclude_player' => true, 'bystander_mode' => 'exclude'];
    cfgCheck(pcv_valid_config($legacy, false), 'A legacy pair stays valid.');
    cfgCheck(pcv_config_actor_ids($legacy) === ['101', '202'], 'Legacy members are A and B.');

    $group = pcv_normalize_config(['enabled' => true, 'scene_mode' => 'pair', 'actor_ids' => ['404', '505', '303'],
        'opener' => 'auto', 'exclude_player' => true, 'bystander_mode' => 'exclude'], $known);
    echo json_encode($group) . "\n";
    cfgCheck(($group['actor_ids'] ?? null) === ['404', '505', '303'] && $group['actor_a'] === '404' && $group['actor_b'] === '505'
        && ($group['opener'] ?? null) === 'auto', 'A group keeps member order and mirrors the first two as A and B.');
    cfgCheck(pcv_valid_config($group, false) && pcv_config_actor_ids($group) === ['404', '505', '303'], 'A group validates and exposes all members.');

    $picked = pcv_normalize_config(['enabled' => true, 'scene_mode' => 'pair', 'actor_ids' => ['404', '505'], 'opener' => '505',
        'exclude_player' => false, 'bystander_mode' => 'silent'], $known);
    cfgCheck(($picked['opener'] ?? null) === '505', 'A picked opener is kept when it is a member.');

    foreach ([
        'one member' => ['404'],
        'five members' => ['101', '202', '303', '404', '505'],
        'duplicate' => ['404', '404', '303'],
        'unknown' => ['404', '999'],
    ] as $label => $ids) {
        $rejected = false;
        try {
            pcv_normalize_config(['enabled' => true, 'scene_mode' => 'pair', 'actor_ids' => $ids, 'opener' => 'auto',
                'exclude_player' => true, 'bystander_mode' => 'exclude'], $known);
        } catch (InvalidArgumentException) {
            $rejected = true;
        }
        cfgCheck($rejected, "Group stage must reject: $label.");
    }
    $rejected = false;
    try {
        pcv_normalize_config(['enabled' => true, 'scene_mode' => 'pair', 'actor_ids' => ['404', '505'], 'opener' => '303',
            'exclude_player' => true, 'bystander_mode' => 'exclude'], $known);
    } catch (InvalidArgumentException) {
        $rejected = true;
    }
    cfgCheck($rejected, 'An opener outside the members is rejected.');

    $bad = $group;
    $bad['actor_a'] = '505';
    cfgCheck(!pcv_valid_config($bad, false), 'actor_a must mirror actor_ids[0].');
    $badOpener = $group;
    $badOpener['opener'] = '101';
    cfgCheck(!pcv_valid_config($badOpener, false), 'A stored opener must be a member or auto.');
    $openerOnly = $legacy + ['opener' => 'auto'];
    cfgCheck(!pcv_valid_config($openerOnly, false), 'An opener without actor_ids is invalid.');
    $solo = ['enabled' => true, 'scene_mode' => 'solo', 'actor_a' => '101', 'actor_b' => null, 'exclude_player' => true, 'bystander_mode' => 'exclude'];
    cfgCheck(pcv_valid_config($solo, false) && pcv_config_actor_ids($solo) === ['101'], 'Solo is unchanged.');
    cfgCheck(!pcv_valid_config($solo + ['actor_ids' => ['101', '202']], false), 'Solo never carries actor_ids.');
    echo "PASS group configs validate, mirror A/B, and keep legacy pairs and solo valid\n";
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
}
