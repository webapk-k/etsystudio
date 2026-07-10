<?php
/* usage.php — GET current freemium usage. Optional ?code=<access code>. */
define('ETSY_STUDIO', 1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$configPath = dirname(__DIR__) . '/config.php';
$config = file_exists($configPath) ? require $configPath : [];

require __DIR__ . '/lib/usage.php';

$limit     = (int) ($config['free_limit'] ?? 5);
$codes     = is_array($config['access_codes'] ?? null) ? $config['access_codes'] : [];
$code      = trim((string) ($_GET['code'] ?? ''));
$unlimited = (($config['plan'] ?? '') === 'pro') || ($code !== '' && in_array($code, $codes, true));
$used      = els_usage_get();

echo json_encode([
    'used'      => $used,
    'limit'     => $limit,
    'unlimited' => $unlimited,
    'remaining' => $unlimited ? null : max(0, $limit - $used),
    'code_ok'   => ($code !== '' ? ($unlimited ? true : false) : null),
], JSON_UNESCAPED_SLASHES);
