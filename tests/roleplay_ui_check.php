<?php
declare(strict_types=1);

// 0.1.14 page: scene card textarea, turn length select (not for solo), free-scene size select (free only).

define('PCV_UI_TEST', true);
define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/index.php';

function ruiCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $known = ['404' => 'Stalleo', '505' => 'Guard One', '606' => 'Guard Two'];
    $base = ['csrf' => 'tok', 'action' => 'arm', 'bystander_mode' => 'exclude', 'exclude_player' => '1'];
    $card = 'Late night at the Bannered Mare.';

    $pair = pcv_form_desired_state($base + ['actor_a' => '404', 'actor_b' => '505', 'opener' => 'auto', 'card' => $card, 'pace' => 'short'], 'tok', $known);
    ruiCheck(($pair['card'] ?? null) === $card && ($pair['pace'] ?? null) === 'short', 'The pair form passes card and pace.');
    $free = pcv_form_desired_state($base + ['scene_mode' => 'free', 'free_cap' => '3', 'card' => $card, 'pace' => 'long'], 'tok', $known);
    ruiCheck(($free['free_cap'] ?? null) === '3' && ($free['card'] ?? null) === $card && ($free['pace'] ?? null) === 'long', 'The free form passes cap, card and pace.');
    $solo = pcv_form_desired_state($base + ['scene_mode' => 'solo', 'actor_a' => '404', 'card' => $card, 'pace' => 'long'], 'tok', $known);
    ruiCheck(($solo['card'] ?? null) === $card && !array_key_exists('pace', $solo), 'Solo keeps the card and ignores the pace.');
    $rejected = false;
    try {
        pcv_form_desired_state($base + ['actor_a' => '404', 'actor_b' => '505', 'opener' => 'auto', 'card' => ['x']], 'tok', $known);
    } catch (PcvUiFormRejection) {
        $rejected = true;
    }
    ruiCheck($rejected, 'A non-text card is rejected.');

    $html = pcv_render_page('tok', ['status' => 'off', 'pending' => false], $known);
    ruiCheck(str_contains($html, 'id="scene-card"') && str_contains($html, 'name="card"') && str_contains($html, 'maxlength="300"'), 'The page offers the scene card.');
    ruiCheck(str_contains($html, 'id="pace"') && str_contains($html, '<option value="normal" selected>'), 'The page offers turn length, Normal by default.');
    ruiCheck(str_contains($html, 'id="free-cap"') && (bool)preg_match('/id="free-cap"[^>]*disabled/', $html), 'Free size is offered and disabled until Free scene is ticked.');

    $active = ['status' => 'active', 'pending' => false, 'config_id' => null,
        'scope' => ['enabled' => true, 'scene_mode' => 'pair', 'actor_a' => '404', 'actor_b' => '505', 'exclude_player' => true,
            'bystander_mode' => 'exclude', 'card' => $card, 'pace' => 'long']];
    $activeHtml = pcv_render_page('tok', $active, $known);
    ruiCheck(str_contains($activeHtml, '>' . $card . '</textarea>') && str_contains($activeHtml, '<option value="long" selected>'), 'The form keeps the current card and pace.');
    ruiCheck(str_contains($activeHtml, 'Scene card: ' . $card), 'The status shows the scene card.');

    echo "PASS the page offers scene card, turn length and free size, and the form passes them through\n";
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
}
