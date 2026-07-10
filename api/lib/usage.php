<?php
/* usage.php — tiny file-based counter for the freemium limit (MVP).
   Later this is replaced by per-account counters in a database. */

if (!defined('ETSY_STUDIO')) { http_response_code(403); exit('Forbidden'); }

function els_usage_path(): string {
    return dirname(__DIR__, 2) . '/data/usage.json';
}
function els_usage_get(): int {
    $f = els_usage_path();
    if (!is_file($f)) return 0;
    $j = json_decode((string) @file_get_contents($f), true);
    return (int) ($j['count'] ?? 0);
}
function els_usage_inc(): int {
    $f = els_usage_path();
    $dir = dirname($f);
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    $c = els_usage_get() + 1;
    @file_put_contents($f, json_encode(['count' => $c]), LOCK_EX);
    return $c;
}
