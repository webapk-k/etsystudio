<?php
/* policy.php — "Policy Violation" scanner.
 *   POST { action:"fetch", shop, token }      -> { shop, listings:[...] }   (Etsy Open API v3, needs etsy_api_key)
 *   POST { action:"ai", listings:[...], token } -> { results:[{id,issues:[...]}] }  (AI review vs Etsy policies) */

define('ETSY_STUDIO', 1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require __DIR__ . '/lib/Anthropic.php';
require __DIR__ . '/lib/Gemini.php';
require __DIR__ . '/lib/OpenRouter.php';
require __DIR__ . '/lib/store.php';

$configPath = dirname(__DIR__) . '/config.php';
if (!file_exists($configPath)) { echo json_encode(['error' => 'Setup needed: add your API key in config.php.']); exit; }
$config   = require $configPath;
$provider = strtolower($config['provider'] ?? 'anthropic');

const ELS_POLICY_MAX = 300;   // listings fetched per scan

try {
    $in     = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = (string) ($in['action'] ?? '');
    $authUser = els_user_by_token(trim((string) ($in['token'] ?? '')));

    if ($action === 'fetch') {
        $key  = trim((string) ($config['etsy_api_key'] ?? ''));
        $shop = els_shop_name((string) ($in['shop'] ?? ''));
        if ($shop === '') { throw new RuntimeException('Paste your Etsy shop URL — e.g. https://www.etsy.com/shop/YourShopName'); }
        if ($key === '') {
            echo json_encode(['error' => 'Shop scanning needs an Etsy API key (not set up yet). For now, upload your listings CSV below — it checks every listing the same way.', 'need_csv' => true]);
            exit;
        }
        $found = els_etsy($key, '/application/shops?shop_name=' . rawurlencode($shop) . '&limit=5');
        $shopRow = null;
        foreach (($found['results'] ?? []) as $s) {
            if (strcasecmp((string) ($s['shop_name'] ?? ''), $shop) === 0) { $shopRow = $s; break; }
        }
        if (!$shopRow) { throw new RuntimeException("Couldn’t find an Etsy shop named “{$shop}”. Check the URL."); }
        $shopId = (int) $shopRow['shop_id'];

        $listings = [];
        for ($offset = 0; $offset < ELS_POLICY_MAX; $offset += 100) {
            $page = els_etsy($key, "/application/shops/{$shopId}/listings/active?limit=100&offset={$offset}&includes=Images");
            foreach (($page['results'] ?? []) as $l) {
                $price = $l['price'] ?? [];
                $listings[] = [
                    'id'          => (string) $l['listing_id'],
                    'title'       => html_entity_decode((string) ($l['title'] ?? ''), ENT_QUOTES, 'UTF-8'),
                    'description' => html_entity_decode((string) ($l['description'] ?? ''), ENT_QUOTES, 'UTF-8'),
                    'tags'        => array_values((array) ($l['tags'] ?? [])),
                    'materials'   => array_values((array) ($l['materials'] ?? [])),
                    'url'         => (string) ($l['url'] ?? ''),
                    'image'       => (string) ($l['images'][0]['url_170x135'] ?? ''),
                    'price'       => isset($price['amount'], $price['divisor']) && $price['divisor']
                                       ? number_format($price['amount'] / $price['divisor'], 2) . ' ' . ($price['currency_code'] ?? '') : '',
                ];
            }
            if (count($page['results'] ?? []) < 100) { break; }
        }
        if ($authUser) { els_history_add($authUser['email'], $authUser['name'] ?? '', 'policy', $shop); }
        echo json_encode(['ok' => true, 'shop' => $shopRow['shop_name'], 'total' => (int) ($shopRow['listing_active_count'] ?? count($listings)), 'listings' => $listings],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action !== 'ai') { throw new RuntimeException('Unknown action.'); }

    if ($provider === 'gemini') {
        $key = trim($config['gemini_api_key'] ?? '');
        if ($key === '' || stripos($key, 'PASTE') !== false) { echo json_encode(['error' => 'Add your free Gemini API key in config.php (aistudio.google.com).']); exit; }
        $client = new Gemini($key, $config['gemini_model'] ?? 'gemini-2.5-flash', 90);
    } elseif ($provider === 'openrouter') {
        $key = trim($config['openrouter_api_key'] ?? '');
        if ($key === '' || stripos($key, 'PASTE') !== false) { echo json_encode(['error' => 'Add your OpenRouter API key in config.php.']); exit; }
        $client = new OpenRouter($key, $config['openrouter_model'] ?? 'google/gemini-2.0-flash-exp:free', 90);
    } else {
        $key = trim($config['anthropic_api_key'] ?? '');
        if ($key === '' || stripos($key, 'REPLACE') !== false || !str_starts_with($key, 'sk-ant-')) { echo json_encode(['error' => 'Add your Anthropic API key in config.php.']); exit; }
        $client = new Anthropic($key, $config['anthropic_model'] ?? 'claude-opus-4-8');
    }

    $batch = array_slice((array) ($in['listings'] ?? []), 0, 20);
    if (!$batch) { throw new RuntimeException('No listings to check.'); }
    $lines = [];
    foreach ($batch as $l) {
        $lines[] = json_encode([
            'id'          => (string) ($l['id'] ?? ''),
            'title'       => mb_substr((string) ($l['title'] ?? ''), 0, 200),
            'tags'        => array_slice((array) ($l['tags'] ?? []), 0, 13),
            'materials'   => array_slice((array) ($l['materials'] ?? []), 0, 13),
            'description' => mb_substr((string) ($l['description'] ?? ''), 0, 1200),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $system = <<<'SYS'
You are an Etsy Trust & Safety policy reviewer. For EACH listing, find anything that breaks Etsy's
policies or is likely to get the listing removed or the shop suspended. Check against:
- Intellectual Property Policy: trademarks / brand names / logos (Disney, Marvel, Nike, LV, Stanley,
  sports teams, bands…), copyrighted characters, celebrity names or likeness (right of publicity),
  "inspired by <brand>", "dupe", "replica", "not affiliated with…" (a disclaimer does NOT make it OK).
- Prohibited Items Policy: alcohol, tobacco & vapes, drugs / drug paraphernalia, CBD/THC/kratom,
  medical drugs, medical or health claims (cures / treats / heals / prevents a disease, FDA approved,
  clinically proven), animal products from endangered species (ivory, tortoiseshell…), human remains,
  weapons / firearm parts / ammunition / knuckles, hazardous materials & recalled items, infant teething
  jewellery and unsafe infant sleep products, hate items & symbols, violent content, pornography
  (sexual content must be marked mature), illegal items & internationally regulated items.
- Creativity Standards: items must be made / designed / handpicked (vintage 20+ yrs) / sourced by the
  seller. Reselling factory items as handmade, dropshipping (AliExpress, Temu, "wholesale"), or
  AI-generated designs that are not disclosed in the description are violations.
- Fees & Payments / House Rules: contact info, emails, phone numbers, external links, social handles,
  "message me on WhatsApp", PayPal/Venmo/CashApp/Zelle off-site payment, sending buyers off Etsy,
  giveaways / raffles / lotteries, misleading or bait-and-switch info, keyword stuffing / search
  manipulation (unrelated or repeated keywords), discriminatory or harassing content.
Severity: "red" = clear violation (removal / suspension risk). "amber" = risky, should be fixed.
Do NOT flag normal, compliant listings — return an empty issues array for them. Never invent problems
that are not in the text. Quote the exact offending word or phrase in "match".
SYS;

    $user = "LISTINGS (one JSON per line):\n" . implode("\n", $lines) . "\n\nReturn ONLY this JSON:\n"
          . '{"results":[{"id":string (the listing id),"issues":[{"severity":"red|amber","policy":string (e.g. "Intellectual Property", "Prohibited Items", "Creativity Standards", "Fees & Payments", "House Rules"),'
          . '"match":string (exact offending word/phrase),"text":string (what is wrong, 1 sentence),"fix":string (how to fix, 1 sentence)}]}]} — one entry per listing, same ids.';
    if ($provider === 'gemini' || $provider === 'openrouter') { $user .= "\n\nReturn ONLY the raw JSON, no code fences."; }

    $issue = ['type'=>'object','additionalProperties'=>false,'required'=>['severity','policy','match','text','fix'],
        'properties'=>['severity'=>['type'=>'string','enum'=>['red','amber']],'policy'=>['type'=>'string'],'match'=>['type'=>'string'],'text'=>['type'=>'string'],'fix'=>['type'=>'string']]];
    $schema = ['type'=>'object','additionalProperties'=>false,'required'=>['results'],'properties'=>['results'=>['type'=>'array','items'=>[
        'type'=>'object','additionalProperties'=>false,'required'=>['id','issues'],'properties'=>['id'=>['type'=>'string'],'issues'=>['type'=>'array','items'=>$issue]]]]]];

    $raw  = $client->message($system, $user, ['max_tokens' => 8000, 'json_schema' => $schema]);
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['results'])) { throw new RuntimeException('The AI returned an unexpected format. Please try again.'); }
    echo json_encode(['ok' => true, 'results' => $data['results']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    // any Etsy-API failure → steer the user to the CSV upload, which needs no key
    echo json_encode(['error' => $e->getMessage(), 'need_csv' => str_starts_with($e->getMessage(), 'Etsy')]);
}

/** Shop name from a URL (etsy.com/shop/Name, name.etsy.com) or a bare name. */
function els_shop_name(string $s): string {
    $s = trim($s);
    if (preg_match('~/shop/([A-Za-z0-9]+)~', $s, $m)) { return $m[1]; }
    if (preg_match('~^(?:https?://)?([A-Za-z0-9]+)\.etsy\.com~i', $s, $m) && strtolower($m[1]) !== 'www') { return $m[1]; }
    return preg_match('~^[A-Za-z0-9]{3,40}$~', $s) ? $s : '';
}

/** GET an Etsy Open API v3 endpoint. $key is "keystring:shared_secret". */
function els_etsy(string $key, string $path): array {
    $ch = curl_init('https://openapi.etsy.com/v3' . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => ['x-api-key: ' . $key, 'Accept: application/json'],
    ]);
    $body   = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);
    if ($body === false) { throw new RuntimeException('Could not reach Etsy: ' . $err); }
    $j = json_decode((string) $body, true);
    if ($status === 401 || $status === 403) { throw new RuntimeException('Etsy shop scanning isn’t active yet (the Etsy API key is pending approval or invalid). Meanwhile, upload your listings CSV below — it checks every listing the same way.'); }
    if ($status === 429) { throw new RuntimeException('Etsy rate limit hit — wait a minute and try again.'); }
    if ($status < 200 || $status >= 300) { throw new RuntimeException('Etsy API error ' . $status . ': ' . ($j['error'] ?? 'unknown')); }
    return is_array($j) ? $j : [];
}
