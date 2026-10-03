<?php
declare(strict_types=1);

// 0.1.12 free mode page: a Free scene checkbox posts scene_mode=free; the form ignores the pickers and always
// excludes the player; status shows the free scene's members, or "free scene (nearest six)" while pending.

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

try {
    $known = ['404' => 'Stalleo', '505' => 'Guard One', '606' => 'Guard Two'];
    $desired = pcv_form_desired_state(['csrf' => 'tok', 'action' => 'arm', 'scene_mode' => 'free', 'bystander_mode' => 'silent',
        'actor_a' => '404', 'actor_c' => '606', 'opener' => '404'], 'tok', $known);
    echo json_encode($desired) . "\n";
    uiCheck($desired === ['enabled' => true, 'scene_mode' => 'pair', 'free' => true, 'exclude_player' => true, 'bystander_mode' => 'silent'],
        'The free form ignores pickers and always excludes the player.');
    $rejected = false;
    try {
        pcv_form_desired_state(['csrf' => 'tok', 'action' => 'arm', 'scene_mode' => 'free', 'bystander_mode' => 'loud'], 'tok', $known);
    } catch (PcvUiFormRejection) {
        $rejected = true;
    }
    uiCheck($rejected, 'An invalid other-NPCs option is rejected for free scenes.');

    $html = pcv_render_page('tok', ['status' => 'off', 'pending' => false], $known);
    uiCheck(str_contains($html, 'id="free-mode"') && str_contains($html, 'name="scene_mode" value="free"'), 'The page offers the Free scene checkbox.');
    uiCheck(!preg_match('/id="free-mode"[^>]*checked/', $html), 'Free is unchecked by default.');

    $pending = ['status' => 'pending', 'pending' => true, 'config_id' => null,
        'pending_scope' => ['enabled' => true, 'scene_mode' => 'pair', 'free' => true, 'exclude_player' => true, 'bystander_mode' => 'exclude']];
    $pendingHtml = pcv_render_page('tok', $pending, $known);
    uiCheck(str_contains($pendingHtml, 'Next: free scene (nearest six).'), 'A pending free scene is described.');
    uiCheck((bool)preg_match('/id="free-mode"[^>]*checked/', $pendingHtml), 'A pending free scene keeps the checkbox ticked.');
    uiCheck((bool)preg_match('/id="actor-a"[^>]*disabled/', $pendingHtml) && (bool)preg_match('/id="opener"[^>]*disabled/', $pendingHtml),
        'Pickers are disabled while free is ticked.');

    $active = ['status' => 'active', 'pending' => false, 'config_id' => null,
        'scope' => ['enabled' => true, 'scene_mode' => 'pair', 'free' => true, 'actor_a' => '404', 'actor_b' => '505',
            'actor_ids' => ['404', '505', '606'], 'opener' => 'auto', 'exclude_player' => true, 'bystander_mode' => 'exclude']];
    $activeHtml = pcv_render_page('tok', $active, $known);
    uiCheck(str_contains($activeHtml, 'Current free scene: Stalleo, Guard One and Guard Two.'), 'An active free scene lists its members.');
    $pairFree = $active;
    $pairFree['scope']['actor_ids'] = ['404', '505'];
    uiCheck(str_contains(pcv_render_page('tok', $pairFree, $known), 'Current free scene: Stalleo and Guard One.'), 'Two-member free wording.');
    echo "PASS free page: checkbox, form ignores pickers, pending and active free status\n";
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
}
