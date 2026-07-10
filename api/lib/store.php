<?php
/**
 * store.php — tiny file-based JSON store for users + history (no database needed).
 * Files live in  <project>/data/  : users.json, history.json
 */

if (!defined('ETSY_STUDIO')) { http_response_code(403); exit('Forbidden'); }

function els_data_dir(): string {
    $d = dirname(__DIR__, 2) . '/data';   // api/lib -> project root -> /data
    if (!is_dir($d)) { @mkdir($d, 0777, true); }
    return $d;
}
function els_store_read(string $file): array {
    $p = els_data_dir() . '/' . $file;
    if (!is_file($p)) { return []; }
    $j = json_decode((string) file_get_contents($p), true);
    return is_array($j) ? $j : [];
}
function els_store_write(string $file, array $data): void {
    $p  = els_data_dir() . '/' . $file;
    $fp = @fopen($p, 'c+');
    if ($fp) {
        flock($fp, LOCK_EX); ftruncate($fp, 0); rewind($fp);
        fwrite($fp, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        fflush($fp); flock($fp, LOCK_UN); fclose($fp);
    } else {
        file_put_contents($p, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}

/* ---- users (keyed by lowercased email) ---- */
function els_users(): array {
    $d = els_store_read('users.json');
    return (isset($d['users']) && is_array($d['users'])) ? $d['users'] : [];
}
function els_users_save(array $users): void { els_store_write('users.json', ['users' => $users]); }
function els_user_update(array $u): void {
    if (empty($u['email'])) { return; }
    $users = els_users(); $users[strtolower($u['email'])] = $u; els_users_save($users);
}
function els_user_by_token(string $token): ?array {
    if ($token === '') { return null; }
    foreach (els_users() as $u) { if (($u['token'] ?? '') === $token) { return $u; } }
    return null;
}
function els_new_token(): string {
    try { return bin2hex(random_bytes(16)); }
    catch (Throwable $e) { return md5(uniqid((string) mt_rand(), true)); }
}
function els_user_is_unlimited(array $u): bool {
    return (($u['plan'] ?? 'free') === 'pro') || !empty($u['granted']) || !empty($u['is_admin']);
}

/* ---- history (newest first, capped) ---- */
function els_history_add(string $email, string $name, string $type, string $query): void {
    $d = els_store_read('history.json');
    $items = (isset($d['items']) && is_array($d['items'])) ? $d['items'] : [];
    array_unshift($items, [
        'email' => $email, 'name' => $name, 'type' => $type,
        'query' => mb_substr($query, 0, 120), 'ts' => time(),
    ]);
    els_store_write('history.json', ['items' => array_slice($items, 0, 1000)]);
}
