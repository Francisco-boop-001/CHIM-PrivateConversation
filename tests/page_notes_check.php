<?php
declare(strict_types=1);

// 0.1.13 E5: when a scene ends itself because its NPCs were gone, the page says so.

define('PCV_UI_TEST', true);
define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/index.php';

try {
    $known = ['404' => 'Stalleo', '505' => 'Guard One'];
    $html = pcv_render_page('tok', ['status' => 'off', 'pending' => false, 'last_end' => ['reason' => 'members_gone', 'at' => time() - 60]], $known);
    if (!str_contains($html, 'The scene ended: its NPCs were gone for five minutes.')) {
        throw new RuntimeException('The page explains an automatic end.');
    }
    if (str_contains(pcv_render_page('tok', ['status' => 'off', 'pending' => false], $known), 'were gone for five minutes')) {
        throw new RuntimeException('No note without an automatic end.');
    }
    echo "PASS the page explains a scene that ended because its NPCs were gone\n";
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
}
