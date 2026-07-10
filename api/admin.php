<?php
/* admin.php — super-admin only. POST { token, action, ... }
 * actions: overview (default), grant { email, grant } */

define('ETSY_STUDIO', 1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require __DIR__ . '/lib/store.php';

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$me = els_user_by_token((string) ($in['token'] ?? ''));
if (!$me || empty($me['is_admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required.']);
    exit;
}

try {
    $action = (string) ($in['action'] ?? 'overview');
    $users  = els_users();

    if ($action === 'grant') {
        $email = strtolower(trim((string) ($in['email'] ?? '')));
        $grant = !empty($in['grant']);
        if (isset($users[$email]) && empty($users[$email]['is_admin'])) {
            $users[$email]['granted'] = $grant;
            $users[$email]['plan']    = $grant ? 'pro' : 'free';
            els_users_save($users);
        }
    } elseif ($action === 'reset_free') {
        $email = strtolower(trim((string) ($in['email'] ?? '')));
        if (isset($users[$email])) { $users[$email]['free_used'] = 0; els_users_save($users); }
    } elseif ($action === 'add') {
        $email = strtolower(trim((string) ($in['email'] ?? '')));
        $name  = trim((string) ($in['name'] ?? ''));
        $pass  = (string) ($in['password'] ?? '');
        $plan  = (($in['plan'] ?? 'free') === 'pro') ? 'pro' : 'free';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { throw new RuntimeException('Enter a valid email address.'); }
        if (isset($users[$email])) { throw new RuntimeException('A user with that email already exists.'); }
        $users[$email] = [
            'id' => $email, 'name' => $name !== '' ? $name : (explode('@', $email)[0] ?? 'User'),
            'email' => $email, 'pass' => $pass !== '' ? password_hash($pass, PASSWORD_DEFAULT) : '',
            'google' => false, 'plan' => $plan, 'free_used' => 0, 'granted' => ($plan === 'pro'),
            'is_admin' => false, 'created' => time(), 'last' => 0, 'token' => els_new_token(),
        ];
        els_users_save($users);
    } elseif ($action === 'update') {
        $email = strtolower(trim((string) ($in['email'] ?? '')));
        if (!isset($users[$email])) { throw new RuntimeException('User not found.'); }
        if (!empty($users[$email]['is_admin'])) { throw new RuntimeException('The admin account cannot be edited here.'); }
        if (isset($in['name']) && trim((string) $in['name']) !== '') { $users[$email]['name'] = trim((string) $in['name']); }
        if (isset($in['plan'])) { $p = ($in['plan'] === 'pro') ? 'pro' : 'free'; $users[$email]['plan'] = $p; $users[$email]['granted'] = ($p === 'pro'); }
        if (!empty($in['password'])) { $users[$email]['pass'] = password_hash((string) $in['password'], PASSWORD_DEFAULT); }
        if (!empty($in['reset_free'])) { $users[$email]['free_used'] = 0; }
        els_users_save($users);
    } elseif ($action === 'delete') {
        $email = strtolower(trim((string) ($in['email'] ?? '')));
        if (isset($users[$email])) {
            if (!empty($users[$email]['is_admin'])) { throw new RuntimeException('The admin account cannot be deleted.'); }
            unset($users[$email]);
            els_users_save($users);
        }
    }

    $list = []; $pro = 0;
    foreach ($users as $x) {
        $unl = els_user_is_unlimited($x);
        if ($unl && empty($x['is_admin'])) { $pro++; }
        $list[] = [
            'name' => $x['name'] ?? '', 'email' => $x['email'] ?? '',
            'plan' => $x['plan'] ?? 'free', 'unlimited' => $unl,
            'granted' => !empty($x['granted']), 'is_admin' => !empty($x['is_admin']),
            'google' => !empty($x['google']), 'free_used' => (int) ($x['free_used'] ?? 0),
            'created' => (int) ($x['created'] ?? 0), 'last' => (int) ($x['last'] ?? 0),
        ];
    }
    usort($list, fn($a, $b) => ($b['created'] ?? 0) <=> ($a['created'] ?? 0));

    $hist = els_store_read('history.json')['items'] ?? [];

    echo json_encode([
        'ok'    => true,
        'stats' => ['users' => count($list), 'pro' => $pro, 'searches' => count($hist)],
        'users' => $list,
        'history' => array_slice($hist, 0, 250),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
