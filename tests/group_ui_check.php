<?php
declare(strict_types=1);

// 0.1.11 group mode page: NPC A and B plus optional C and D and an opener picker; the form builds a 2-4 member
// group; status shows the group and anyone dropped. A post without the opener field keeps the legacy pair shape.

define('PCV_UI_TEST', true);
define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/index.php';

function uiCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function uiRejects(array $post, array $known): bool
{
    try {
        pcv_form_desired_state($post, 'tok', $known);
    } catch (PcvUiFormRejection) {
        return true;
    }
    return false;
}

try {
    $known = ['404' => 'Stalleo', '505' => 'Guard One', '606' => 'Guard Two', '707' => 'Guard Three', '808' => 'Innkeeper'];
    $base = ['csrf' => 'tok', 'action' => 'arm', 'actor_a' => '404', 'actor_b' => '505', 'bystander_mode' => 'exclude', 'exclude_player' => '1'];

    $group = pcv_form_desired_state($base + ['actor_c' => '606', 'actor_d' => '', 'opener' => 'auto'], 'tok', $known);
    echo json_encode($group) . "\n";
    uiCheck(($group['actor_ids'] ?? null) === ['404', '505', '606'] && ($group['opener'] ?? null) === 'auto'
        && !array_key_exists('actor_a', $group), 'The group form builds ordered members and an opener.');
    $four = pcv_form_desired_state($base + ['actor_c' => '606', 'actor_d' => '707', 'opener' => '707'], 'tok', $known);
    uiCheck(($four['actor_ids'] ?? null) === ['404', '505', '606', '707'] && $four['opener'] === '707', 'Four members with a picked opener.');
    $pairNew = pcv_form_desired_state($base + ['actor_c' => '', 'actor_d' => '', 'opener' => 'auto'], 'tok', $known);
    uiCheck(($pairNew['actor_ids'] ?? null) === ['404', '505'], 'Two members from the new form are a two-member group (auto opener).');
    $legacy = pcv_form_desired_state($base, 'tok', $known);
    uiCheck(($legacy['actor_a'] ?? null) === '404' && ($legacy['actor_b'] ?? null) === '505' && !array_key_exists('actor_ids', $legacy),
        'A post without the opener field keeps the legacy pair shape.');
    uiCheck(uiRejects($base + ['actor_c' => '404', 'opener' => 'auto'], $known), 'A duplicate member is rejected.');
    uiCheck(uiRejects($base + ['actor_c' => '999', 'opener' => 'auto'], $known), 'An unknown member is rejected.');
    uiCheck(uiRejects($base + ['actor_c' => '606', 'opener' => '808'], $known), 'An opener outside the members is rejected.');

    $html = pcv_render_page('tok', ['status' => 'off', 'pending' => false], $known);
    uiCheck(str_contains($html, 'name="actor_c"') && str_contains($html, 'name="actor_d"') && str_contains($html, 'name="opener"')
        && str_contains($html, 'Auto (named in the direction, else NPC A)'), 'The page offers NPC C, NPC D and the opener picker.');

    $active = ['status' => 'active', 'pending' => false, 'config_id' => null,
        'scope' => ['enabled' => true, 'scene_mode' => 'pair', 'actor_a' => '404', 'actor_b' => '505', 'actor_ids' => ['404', '505', '606'],
            'opener' => 'auto', 'exclude_player' => true, 'bystander_mode' => 'exclude'],
        'dropped' => [['id' => '707', 'reason' => 'not_eligible_at_start'], ['id' => '808', 'reason' => 'left_scene']]];
    $activeHtml = pcv_render_page('tok', $active, $known);
    uiCheck(str_contains($activeHtml, 'Current group: Stalleo, Guard One and Guard Two.'), 'An active group lists its members.');
    uiCheck(str_contains($activeHtml, 'Started without Guard Three (not nearby).') && str_contains($activeHtml, 'Innkeeper left the scene.'),
        'Dropped members are shown with their reason.');
    $pairHtml = pcv_render_page('tok', ['status' => 'active', 'pending' => false, 'config_id' => null,
        'scope' => ['enabled' => true, 'scene_mode' => 'pair', 'actor_a' => '404', 'actor_b' => '505', 'exclude_player' => true, 'bystander_mode' => 'exclude']], $known);
    uiCheck(str_contains($pairHtml, 'Current pair: Stalleo and Guard One.'), 'A two-member scene keeps the pair wording.');
    echo "PASS group page: optional members, opener picker, group status and dropped members; legacy pair form intact\n";
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
}
