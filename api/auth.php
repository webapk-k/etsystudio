<?php
/* auth.php — real server accounts (file-based). POST { action, ... } -> { token, user } */

define('ETSY_STUDIO', 1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require __DIR__ . '/lib/store.php';

$cfgPath = dirname(__DIR__) . '/config.php';
$cfg     = file_exists($cfgPath) ? (require $cfgPath) : [];
$freeLimit  = (int) ($cfg['free_limit'] ?? 5);
$adminEmail = strtolower(trim((string) ($cfg['admin_email'] ?? '')));
$adminPass  = (string) ($cfg['admin_password'] ?? '');

$in     = json_decode(file_get_contents('php://input'), true) ?: [];
$action = (string) ($in['action'] ?? '');

function els_public_user(array $u, int $freeLimit): array {
    $unl = els_user_is_unlimited($u);
    return [
        'name'      => $u['name'] ?? '',
        'email'     => $u['email'] ?? '',
        'plan'      => $u['plan'] ?? 'free',
        'is_admin'  => !empty($u['is_admin']),
        'unlimited' => $unl,
        'used'      => (int) ($u['free_used'] ?? 0),
        'limit'     => $freeLimit,
        'remaining' => $unl ? null : max(0, $freeLimit - (int) ($u['free_used'] ?? 0)),
    ];
}
function els_respond(array $u, int $freeLimit): void {
    echo json_encode(['ok' => true, 'token' => $u['token'], 'user' => els_public_user($u, $freeLimit)]);
    exit;
}

try {
    $users = els_users();
    $email = strtolower(trim((string) ($in['email'] ?? '')));
    $name  = trim((string) ($in['name'] ?? ''));
    $pass  = (string) ($in['password'] ?? '');
    $isGoogle = !empty($in['google']);

    if ($action === 'me') {
        $u = els_user_by_token((string) ($in['token'] ?? ''));
        if (!$u) { throw new RuntimeException('Not logged in.'); }
        els_respond($u, $freeLimit);
    }

    if (!in_array($action, ['signup', 'login', 'google'], true)) {
        throw new RuntimeException('Unknown action.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { throw new RuntimeException('Please enter a valid email address.'); }

    $isAdminEmail = ($adminEmail !== '' && $email === $adminEmail);

    /* ---- ADMIN login (email + password must match config) ---- */
    if ($isAdminEmail && $action !== 'google') {
        if ($adminPass === '' ) { throw new RuntimeException('Admin password is not set in config.php.'); }
        if ($pass !== $adminPass) { throw new RuntimeException('Wrong admin password.'); }
        $u = $users[$email] ?? [
            'id' => $email, 'name' => 'Super Admin', 'email' => $email, 'pass' => '',
            'google' => false, 'plan' => 'pro', 'free_used' => 0, 'granted' => true,
            'is_admin' => true, 'created' => time(),
        ];
        $u['is_admin'] = true; $u['plan'] = 'pro'; $u['granted'] = true; $u['last'] = time();
        if (empty($u['token'])) { $u['token'] = els_new_token(); }
        if ($u['name'] === '' || empty($u['name'])) { $u['name'] = 'Super Admin'; }
        $users[$email] = $u; els_users_save($users);
        els_respond($u, $freeLimit);
    }

    /* ---- normal LOGIN ---- */
    if ($action === 'login') {
        if (!isset($users[$email])) { throw new RuntimeException('No account with that email — please sign up first.'); }
        $u = $users[$email];
        if (!empty($u['pass']) && !password_verify($pass, $u['pass'])) { throw new RuntimeException('Wrong password.'); }
        $u['last'] = time(); if (empty($u['token'])) { $u['token'] = els_new_token(); }
        $users[$email] = $u; els_users_save($users);
        els_respond($u, $freeLimit);
    }

    /* ---- SIGNUP or GOOGLE (create or update) ---- */
    if (isset($users[$email])) {
        $u = $users[$email];                       // already exists -> log them in
        if ($isGoogle) { $u['google'] = true; }
    } else {
        $u = [
            'id' => $email, 'name' => $name ?: (explode('@', $email)[0] ?? 'User'),
            'email' => $email, 'pass' => '', 'google' => $isGoogle,
            'plan' => $isAdminEmail ? 'pro' : 'free', 'free_used' => 0,
            'granted' => $isAdminEmail, 'is_admin' => $isAdminEmail,
            'created' => time(),
        ];
    }
    if ($name !== '') { $u['name'] = $name; }
    if ($action === 'signup' && $pass !== '') { $u['pass'] = password_hash($pass, PASSWORD_DEFAULT); }
    $u['last'] = time();
    if (empty($u['token'])) { $u['token'] = els_new_token(); }
    $users[$email] = $u; els_users_save($users);
    els_respond($u, $freeLimit);

} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
