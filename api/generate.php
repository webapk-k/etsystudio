<?php
/**
 * generate.php — POST { url?, html?, market?, category? }  ->  JSON Etsy listing
 */

define('ETSY_STUDIO', 1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require __DIR__ . '/lib/Scraper.php';
require __DIR__ . '/lib/Anthropic.php';
require __DIR__ . '/lib/Gemini.php';
require __DIR__ . '/lib/OpenRouter.php';
require __DIR__ . '/lib/usage.php';
require __DIR__ . '/lib/store.php';

/* ---- config ---- */
$configPath = dirname(__DIR__) . '/config.php';
if (!file_exists($configPath)) {
    echo json_encode(['error' => 'Setup needed: copy config.sample.php to config.php and add your Anthropic API key.']);
    exit;
}
$config   = require $configPath;
$provider = strtolower($config['provider'] ?? 'anthropic');

try {
    $input    = json_decode(file_get_contents('php://input'), true) ?: [];
    $url      = trim((string) ($input['url'] ?? ''));
    $html     = trim((string) ($input['html'] ?? ''));
    $market   = in_array($input['market'] ?? '', ['uk', 'us', 'both'], true) ? $input['market'] : ($config['default_market'] ?? 'both');
    $category = trim((string) ($input['category'] ?? ''));
    $maker    = in_array($input['maker'] ?? '', ['i_make', 'shop_member', 'reseller'], true) ? $input['maker'] : 'i_make';
    $premium  = !empty($input['premium']);
    $images   = els_read_images($input['image'] ?? ($input['images'] ?? null));
    $hasImage = !empty($images);

    // ---- freemium gate (per-user when logged in, else global counter) ----
    $accessCode = trim((string) ($input['access_code'] ?? ''));
    $freeLimit  = (int) ($config['free_limit'] ?? 5);
    $codes      = is_array($config['access_codes'] ?? null) ? $config['access_codes'] : [];
    $authUser   = els_user_by_token(trim((string) ($input['token'] ?? '')));
    if ($authUser) {
        $unlimited = els_user_is_unlimited($authUser);
        $used      = (int) ($authUser['free_used'] ?? 0);
    } else {
        $unlimited = (($config['plan'] ?? '') === 'pro') || ($accessCode !== '' && in_array($accessCode, $codes, true));
        $used      = els_usage_get();
    }
    if (!$unlimited && $used >= $freeLimit) {
        echo json_encode(['paywall' => true, 'used' => $used, 'limit' => $freeLimit]);
        exit;
    }

    // ---- pick the writer model (Premium => Claude if a key exists; else the configured provider) ----
    $useProvider = $provider;
    $premiumNote = null;
    if ($premium) {
        [$pc, $pe] = els_make_client('anthropic', $config);
        if ($pe === null) { $useProvider = 'anthropic'; }
        else { $premiumNote = 'Premium (Claude) needs an Anthropic key in config.php — used the free model for now.'; }
    }
    [$client, $clientErr] = els_make_client($useProvider, $config);
    if ($clientErr !== null) { echo json_encode(['error' => $clientErr]); exit; }

    // Photos are always read by Gemini Vision (free), whatever the writer model is.
    $vision = null;
    if ($hasImage) {
        [$vc, $ve] = els_make_client('gemini', $config);
        if ($ve !== null) { echo json_encode(['error' => 'Photo listings need a free Gemini key in config.php (aistudio.google.com).']); exit; }
        $vision = $vc;
    }

    if ($url === '' && $html === '' && !$hasImage) {
        throw new RuntimeException('Paste a product link, upload a product photo, or paste the page content.');
    }

    /* ---- scrape (only when a link or pasted content was given) ---- */
    $signals = null;
    if ($url !== '' || $html !== '') {
        $scraper = new Scraper(trim((string) ($config['scraper_api_key'] ?? '')));
        $signals = $scraper->scrape($url, $html !== '' ? $html : null);
        if (!empty($signals['_needsPaste'])) {
            // If a photo was ALSO uploaded, don't fail — fall through and use the photo instead.
            if (!$hasImage) { echo json_encode(['error' => $signals['_message'], 'needs_paste' => true]); exit; }
            $signals = null;
        }
    }
    if ($signals === null) {
        $signals = ['name' => '', 'description' => '', 'text' => '', 'images' => [], 'specs' => [],
                    'variations' => [], 'price' => '', 'currency' => '', 'sourceUrl' => $url,
                    '_source' => $hasImage ? 'photo' : 'none'];
    }

    /* ---- vision: read the uploaded photo(s) into a product description ---- */
    if ($hasImage && $vision instanceof Gemini) {
        $desc = $vision->describeProduct($images);   // throws on failure -> caught below
        if (empty($signals['name'])) {
            $first = preg_split('~(?<=[.!?])\s+~', $desc)[0] ?? $desc;
            $signals['name'] = trim(mb_substr($first, 0, 120));
        }
        $signals['text']  = trim($desc . "\n\n" . (string) ($signals['text'] ?? ''));
        $signals['_source'] = in_array($signals['_source'], ['photo', 'none'], true) ? 'photo' : $signals['_source'] . '+photo';
    }

    // Require a REAL product identity before spending a model call — otherwise the model would
    // invent a product out of navigation/boilerplate text. A photo counts on its own.
    $prodName = trim((string) ($signals['name'] ?? ''));
    if (els_is_junk_name($prodName)) { $prodName = ''; $signals['name'] = ''; }
    $hasDesc  = mb_strlen((string) ($signals['description'] ?? '')) >= 30;
    $hasName  = mb_strlen($prodName) >= 8;
    if (!$hasImage && !$hasName && !$hasDesc && empty($signals['specs'])) {
        echo json_encode([
            'error'       => "Couldn't read the actual product from that page (the site blocked us or showed only menus). "
                           . "Open the product page, press Ctrl+A then Ctrl+C, and use \"Paste page content instead\" — or upload a photo.",
            'needs_paste' => true,
        ]);
        exit;
    }

    /* ---- build the signals block for the model ---- */
    $signalsText = build_signals_text($signals, $url);

    $marketLabel = ['uk' => 'UK', 'us' => 'US', 'both' => 'UK+US'][$market];
    $makerRule = [
        'i_make'      => 'MAKER DECLARATION: The seller MAKES or assembles this item themselves. Set attributes.who_made = "i_did" and attributes.what_is_it = "finished_product". Set attributes.when_made = "made_to_order" if it is personalised / custom / made on demand, otherwise "2020_2026".',
        'shop_member' => 'MAKER DECLARATION: A member of the seller\'s shop makes this item. Set attributes.who_made = "collective" and attributes.what_is_it = "finished_product". Set attributes.when_made = "made_to_order" if personalised, otherwise "2020_2026".',
        'reseller'    => 'MAKER DECLARATION: The seller RESELLS this item — another company or supplier makes it (dropship, wholesale, or print-on-demand). Set attributes.who_made = "someone_else", attributes.what_is_it = "finished_product", attributes.when_made = "2020_2026".',
    ][$maker];
    $photoRule = $hasImage
        ? "PHOTO SOURCE: This product was identified from an uploaded PHOTO; the description under SCRAPED "
        . "SIGNALS is what the photo shows — treat it as ground truth for shape/colour/style. But DO NOT "
        . "state a specific material, exact size, or feature the photo cannot prove (e.g. do not claim "
        . "\"sterling silver\" from a silver-coloured photo) — use hedged wording and put uncertain materials "
        . "only in the description, not as hard claims. Quote any visible brand/logo/character for the trademark scan.\n"
        : '';
    $userContent = "TARGET_MARKET: {$marketLabel}\n"
                 . "CATEGORY_HINT: " . ($category !== '' ? $category : '(none)') . "\n"
                 . $makerRule . "\n"
                 . $photoRule . "\n"
                 . "Generate one Etsy-compliant listing for the TARGET_MARKET above using ONLY the scraped signals below. "
                 . "Use en-GB spelling if the market is UK or UK+US, en-US if US only. Infer any missing attribute from the "
                 . "category and remaining signals — never leave a field empty and never use placeholders. If CATEGORY_HINT is "
                 . "provided, treat it as a strong prior for taxonomy and primary keywords, but override it if the signals clearly "
                 . "contradict it. Price a real margin over the source price for BOTH GBP and USD. Assess policy compliance honestly "
                 . "for a possibly-dropshipped good. Return ONLY the JSON object matching the schema.\n\n"
                 . "===== SCRAPED SIGNALS =====\n{$signalsText}\n===== END SIGNALS =====";

    // Gemini / OpenRouter have no strict schema enforcement — spell out the exact JSON shape.
    if ($useProvider === 'gemini' || $useProvider === 'openrouter') {
        $userContent .= "\n\n" . gemini_shape_hint();
    }

    /* ---- generate ---- */
    $raw = $client->message(system_prompt(), $userContent, [
        'max_tokens'  => 6000,
        'json_schema' => etsy_schema(),
    ]);

    $listing = json_decode($raw, true);
    if (!is_array($listing)) {
        throw new RuntimeException('The model returned an unexpected format. Please try again.');
    }

    // Anti-hallucination: if the page gave us a real product name/description, the generated title
    // must actually relate to it — otherwise the model invented an unrelated product.
    if (!$hasImage) {
        $src = trim($prodName . ' ' . (string) ($signals['description'] ?? '') . ' ' . mb_substr((string) ($signals['text'] ?? ''), 0, 1500));
        if (!els_title_matches((string) ($listing['title'] ?? ''), $src)) {
            echo json_encode([
                'error'       => "We couldn't read that product properly, so the result didn't match it. "
                               . "Open the product page, press Ctrl+A then Ctrl+C, and use \"Paste page content instead\" — or upload a photo.",
                'needs_paste' => true,
            ]);
            exit;
        }
    }

    /* ---- server-side validation against Etsy hard limits ---- */
    $warnings = validate_listing($listing);

    // count this successful listing (per-user if logged in) + log to history
    if ($authUser) {
        if (!$unlimited) { $authUser['free_used'] = $used + 1; els_user_update($authUser); $used = $authUser['free_used']; }
        els_history_add($authUser['email'], $authUser['name'] ?? '', 'listing', (string) ($listing['title'] ?? ($signals['name'] ?? $url)));
    } elseif (!$unlimited) {
        $used = els_usage_inc();
    }

    echo json_encode([
        'ok'       => true,
        'market'   => $market,
        'listing'  => $listing,
        'warnings' => $warnings,
        'engine'   => $useProvider,
        'premium'  => ($useProvider === 'anthropic'),
        'note'     => $premiumNote,
        'usage'    => ['used' => $used, 'limit' => $freeLimit, 'unlimited' => $unlimited, 'remaining' => $unlimited ? null : max(0, $freeLimit - $used)],
        'source'   => [
            'url'          => $signals['sourceUrl'] ?? $url,
            'name'         => $signals['name'] ?? '',
            'price'        => $signals['price'] ?? '',
            'currency'     => $signals['currency'] ?? '',
            'images'       => array_slice($signals['images'] ?? [], 0, 10),
            'via'          => $signals['_source'] ?? 'fetched',
            'variations'   => $signals['variations'] ?? [],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

/* ================================================================== *
 *  Helpers                                                           *
 * ================================================================== */

/** True when a scraped "product name" is really site boilerplate (menus, captcha, cookie text). */
function els_is_junk_name(string $n): bool {
    $n = trim($n);
    if ($n === '' || mb_strlen($n) < 8) { return true; }
    $l = mb_strtolower($n);
    foreach (['aliexpress', 'sign in', 'log in', 'captcha', 'robot check', 'are you a human',
              'page not found', 'not found', 'no longer available', 'does not exist', "doesn't exist", 'access denied', 'just a moment', 'enable javascript',
              'shopping cart', 'search results', 'verify you are human'] as $bad) {
        if (str_contains($l, $bad)) { return true; }
    }
    if (preg_match('~\b(404|410)\b|^\s*(oops|error)\b~', $l)) { return true; }   // "404", "Oops!", "Error 500"
    // A real product name has at least two meaningful words.
    $w = preg_split('~\s+~', trim(preg_replace('~[^\p{L}\p{N}\s]+~u', ' ', $n)), -1, PREG_SPLIT_NO_EMPTY);
    return count($w) < 2;
}

/** Significant (>=4 char, non-stopword) lowercase words of a string. */
function els_words(string $s): array {
    $s = mb_strtolower((string) preg_replace('~[^\p{L}\p{N}\s]+~u', ' ', $s));
    $stop = ['the','and','for','with','from','your','you','this','that','new','set','pcs','cute','gift',
             'free','shipping','size','color','colour','style','item','best','sale','shop','store','online','buy'];
    $out = [];
    foreach (preg_split('~\s+~', $s, -1, PREG_SPLIT_NO_EMPTY) as $x) {
        if (mb_strlen($x) >= 4 && !in_array($x, $stop, true)) { $out[$x] = true; }
    }
    return array_keys($out);
}

/**
 * Anti-hallucination check: the generated title must share at least one real word with the source
 * product. Stops the model inventing an unrelated product when the page text was unusable.
 */
function els_title_matches(string $title, string $source): bool {
    $b = els_words($source);
    if (count($b) < 3) { return true; }            // too little source to judge — allow
    $a = els_words($title);
    if (!$a) { return true; }
    return count(array_intersect($a, $b)) > 0;
}

/** Build an AI client for a given provider. Returns [clientOrNull, errorStringOrNull]. */
function els_make_client(string $provider, array $config): array
{
    if ($provider === 'gemini') {
        $key = trim($config['gemini_api_key'] ?? '');
        if ($key === '' || stripos($key, 'PASTE') !== false) {
            return [null, 'Add your free Gemini API key in config.php (get one at aistudio.google.com).'];
        }
        return [new Gemini($key, $config['gemini_model'] ?? 'gemini-2.5-flash'), null];
    }
    if ($provider === 'openrouter') {
        $key = trim($config['openrouter_api_key'] ?? '');
        if ($key === '' || stripos($key, 'PASTE') !== false) {
            return [null, 'Add your OpenRouter API key in config.php (get one free at openrouter.ai).'];
        }
        return [new OpenRouter($key, $config['openrouter_model'] ?? 'google/gemini-2.0-flash-exp:free'), null];
    }
    $key = trim($config['anthropic_api_key'] ?? '');
    if ($key === '' || stripos($key, 'REPLACE') !== false || !str_starts_with($key, 'sk-ant-')) {
        return [null, 'Add your Anthropic API key in config.php (it should start with "sk-ant-").'];
    }
    return [new Anthropic($key, $config['anthropic_model'] ?? $config['model'] ?? 'claude-opus-4-8'), null];
}

/**
 * Normalise uploaded image input into [ ['mime'=>, 'data'=>base64], ... ].
 * Accepts a single data-URL string, a raw base64 string, or an array of either. Caps at 4 images
 * and ~8MB decoded each.
 */
function els_read_images($raw): array
{
    if ($raw === null || $raw === '') { return []; }
    $items = is_array($raw) ? $raw : [$raw];
    $out = [];
    foreach ($items as $one) {
        $one = trim((string) $one);
        if ($one === '') { continue; }
        if (preg_match('~^data:(image/[a-zA-Z0-9.+-]+);base64,(.+)$~s', $one, $m)) {
            $mime = strtolower($m[1]);
            $data = $m[2];
        } else {
            $mime = 'image/jpeg';
            $data = $one;
        }
        $data = preg_replace('~\s+~', '', $data);
        $decoded = base64_decode($data, true);
        if ($decoded === false || strlen($decoded) < 100 || strlen($decoded) > 8 * 1024 * 1024) { continue; }
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/heic'], true)) {
            $mime = 'image/jpeg';
        }
        $out[] = ['mime' => $mime, 'data' => $data];
        if (count($out) >= 4) { break; }
    }
    return $out;
}

function build_signals_text(array $s, string $url): string
{
    $lines = [];
    $lines[] = 'product_name: '   . ($s['name'] ?: '(unknown)');
    $lines[] = 'source_url: '     . ($s['sourceUrl'] ?: $url);
    if (!empty($s['brand']))    $lines[] = 'brand: ' . $s['brand'];
    if (!empty($s['price']))    $lines[] = 'source_price: ' . $s['price'];
    if (!empty($s['currency'])) $lines[] = 'source_currency: ' . $s['currency'];
    $lines[] = 'images: ' . count($s['images'] ?? []);

    if (!empty($s['variations'])) {
        foreach ($s['variations'] as $v) {
            $vals = implode(', ', array_slice($v['values'] ?? [], 0, 25));
            $lines[] = 'variation[' . ($v['name'] ?? 'option') . ']: ' . $vals;
        }
    }
    if (!empty($s['specs'])) {
        $lines[] = 'specs:';
        foreach (array_slice($s['specs'], 0, 40) as $sp) {
            $lines[] = '  - ' . $sp['key'] . ': ' . $sp['value'];
        }
    }
    if (!empty($s['description'])) {
        $lines[] = 'description: ' . mb_substr($s['description'], 0, 3500);
    }
    // Include cleaned page/pasted text so the model has content even without structured data.
    if (!empty($s['text']) && mb_strlen((string) ($s['description'] ?? '')) < 500) {
        $lines[] = 'page_text: ' . mb_substr($s['text'], 0, 4000);
    }
    return implode("\n", $lines);
}

function gemini_shape_hint(): string
{
    return "Return ONLY a single JSON object (no markdown, no code fences) with EXACTLY these keys and types:\n"
        . "{\n"
        . '  "title": string (<=140 chars),' . "\n"
        . '  "tags": array of EXACTLY 13 lowercase strings (each <=20 chars),' . "\n"
        . '  "description": string,' . "\n"
        . '  "materials": array of strings (<=13),' . "\n"
        . '  "taxonomy_path": string (breadcrumb joined by " > "),' . "\n"
        . '  "attributes": { "who_made": one of ["i_did","someone_else","collective"], "what_is_it": one of ["finished_product","supply_or_tool"], "when_made": one of ["made_to_order","2020_2026","2010_2019","2007_2009","2000_2006","1990s","1980s","1970s","1960s","1950s","1940s","1930s","1920s","1910s","1900s","1800s","1700s","before_1700"], "primary_colour": string, "secondary_colour": string or "none", "occasion": string or "none", "holiday": string or "none", "style": string or "none" },' . "\n"
        . '  "pricing": { "source_price": number, "source_currency": string, "retail_gbp": number, "retail_usd": number, "assumed_fx_gbp_usd": number, "margin_pct": number, "margin_note": string },' . "\n"
        . '  "variation_axes": array of strings,' . "\n"
        . '  "variations": array of { "sku": string, "option_label": string, "price_gbp": number, "price_usd": number, "default_quantity": integer },' . "\n"
        . '  "policy_check": { "status": one of ["ok","caution","likely_violation"], "reasons": array of strings, "flags": array of strings },' . "\n"
        . '  "suggestions": { "alt_titles": array of strings, "title_tips": array of strings (Etsy-style specific title tips), "extra_keywords": array of strings, "tips": array of strings },' . "\n"
        . '  "category_attributes": array of { "name": string, "value": string } — the category-specific attributes Etsy asks for this product (e.g. Chain Style, Materials, Gemstone, Font for jewellery; Capacity, Care for mugs; Scent, Wax Type for candles) with valid values,' . "\n"
        . '  "search_query": string (best 2-4 word buyer search phrase to look this product up on Etsy),' . "\n"
        . '  "competition": { "level": one of ["low","medium","high","saturated"], "estimate": string (plain-language estimate of how many similar listings exist on Etsy — an estimate, not an exact number), "price_range": string (typical Etsy selling price range for this item), "demand": one of ["low","medium","high"], "stand_out": array of 2-4 strings (specific ways to beat the competition) }' . "\n"
        . "}";
}

function validate_listing(array $l): array
{
    $w = [];
    $title = (string) ($l['title'] ?? '');
    if (mb_strlen($title) > 140) {
        $w[] = 'Title is ' . mb_strlen($title) . ' characters — Etsy allows a maximum of 140. Trim it before publishing.';
    }
    $tags = is_array($l['tags'] ?? null) ? $l['tags'] : [];
    if (count($tags) !== 13) {
        $w[] = 'Generated ' . count($tags) . ' tags — Etsy allows up to 13. Aim to fill all 13.';
    }
    $overLong = array_filter($tags, fn($t) => mb_strlen((string) $t) > 20);
    if ($overLong) {
        $w[] = count($overLong) . ' tag(s) exceed 20 characters (Etsy\'s limit): "' . implode('", "', $overLong) . '".';
    }
    $mats = is_array($l['materials'] ?? null) ? $l['materials'] : [];
    if (count($mats) > 13) {
        $w[] = count($mats) . ' materials — Etsy allows up to 13.';
    }
    return $w;
}

function system_prompt(): string
{
    return <<<'SYS'
You are EtsyListingArchitect, a senior Etsy SEO strategist and marketplace-compliance
officer. You convert raw, scraped product signals into ONE fully Etsy-compliant listing
that a UK+US seller can upload without edits. You output ONLY data that conforms to the
provided JSON schema — never commentary, never markdown, never placeholders.

===========================================================================
RULE ZERO — NEVER INVENT THE PRODUCT (most important rule)
===========================================================================
The listing MUST describe the EXACT product named in `product_name` / the signals below.
- `product_name` is the ground truth. Read it literally and identify the real item
  (e.g. "…Cotton Doll Plush Werewolf … Keychain" = a small plush toy keychain — NOT a
  print, NOT a digital download, NOT anything else).
- NEVER substitute a different product, category or format. If the signals describe a
  plush toy, the listing is for that plush toy — never "digital art", "wall print",
  "template" or any other item you were not given.
- Use ONLY facts present in the signals. Do not import ideas from other listings.
- If the signals are thin, still stay strictly within what `product_name` says and infer
  only the obvious (material, colour, use) — never change what the item IS.

===========================================================================
NON-NEGOTIABLE OPERATING RULES
===========================================================================
1. Every field must be SPECIFIC to THIS product and grounded in the scraped signals.
   Never emit generic filler ("beautiful item", "great gift", "high quality product"),
   lorem text, "N/A", "TBD", "example", or repeated boilerplate across listings.
2. If a signal is missing, INFER the most probable real-world value from the product
   category and remaining signals. Do not leave a field empty. Never invent a brand,
   trademark, certification, or origin claim that is not supported by the signals.
3. Write for a real buyer typing into Etsy search. Prefer the exact phrases shoppers
   type over clever marketing language.
4. Output must satisfy EVERY Etsy limit below. Self-check silently before finalising.

===========================================================================
TITLE  (field: title)
===========================================================================
- Maximum 140 characters INCLUDING spaces. Aim for 110-140 to maximise keyword surface.
- FRONT-LOAD the 2-4 highest-value buyer-search keywords in the first ~40 characters,
  because Etsy weights early words and mobile truncates the rest.
- Human-readable phrase order, not a comma-dump of keywords. A little natural chunking
  with "," or " | " is fine; do not stuff.
- FORBIDDEN: ALL CAPS words, keyword stuffing (same word 3+ times), promotional claims
  ("SALE", "BEST", "#1", "FREE SHIPPING", "100% guaranteed", "cheapest"), emoji, and any
  trademark you are not authorised to use (no "Disney", "Nike", "Stanley", team names,
  character names, etc. — for dropshipped goods assume NOT authorised).
- Include at least one occasion/recipient or use-case keyword when natural.

===========================================================================
TAGS  (field: tags) — EXACTLY 13
===========================================================================
- Output EXACTLY 13 tags. Not 12, not 14.
- Each tag <= 20 characters INCLUDING spaces. Count spaces.
- lowercase only.
- Prefer MULTI-WORD long-tail phrases (2-3 words) that mirror real search queries.
- Do not duplicate a whole tag, and avoid repeating the SAME word across many tags.
  Spread distinct concepts: product type, materials, style, colour, recipient, occasion,
  room/use, benefit. No punctuation except spaces and hyphens. No "#". No trademarks.

===========================================================================
DESCRIPTION  (field: description)
===========================================================================
- FIRST SENTENCE is an SEO anchor: it must naturally contain the primary product keyword
  phrase plus 1-2 secondary search terms, readable as a real sentence.
- Then 2-5 short scannable paragraphs/lines covering: what it is, key materials &
  dimensions, who it is for / occasions, care or usage notes, and the variation options
  available. en-GB spelling by default (en-US if market = US only).
- Honest, benefit-led. No fake scarcity, no external links, no request to contact
  off-Etsy, no other-marketplace names, no trademark misuse.

===========================================================================
MATERIALS  (field: materials) — up to 13
===========================================================================
- 1 to 13 REAL, standard material names ONLY — e.g. "sterling silver", "18k gold plated",
  "stainless steel", "cotton", "linen", "ceramic", "soy wax", "glass", "leather", "brass".
  lowercase. NEVER brand names, invented words, colours, styles, or product types — Etsy's
  material field expects genuine materials. If unsure, use the most likely real material for
  this product type.

===========================================================================
TAXONOMY  (field: taxonomy_path)
===========================================================================
- Best-fit Etsy category as a breadcrumb path joined by " > ", e.g.
  "Home & Living > Kitchen & Dining > Drink & Barware > Tumblers & Water Glasses".
  Choose the single most accurate leaf a buyer would browse.

===========================================================================
LISTING ATTRIBUTES
===========================================================================
- who_made / what_is_it / when_made: FOLLOW the MAKER DECLARATION in the user message EXACTLY
  — it states who actually makes the item. Never claim handmade for resold goods, and never
  claim resold for genuinely handmade goods; an inaccurate declaration can get the shop
  suspended. Use "made_to_order" for personalised / custom / on-demand items, "2020_2026" for
  ready-made current stock, and a vintage bucket only if the item is genuinely 20+ years old.
- what_is_it: "finished_product" for a ready-to-sell item; "supply_or_tool" only for craft supplies.
- primary_colour / secondary_colour: from the provided enum. Pick the dominant colour as
  primary; secondary may be "none".
- occasion, holiday, style: best-fit enum value each; use "none" when genuinely not
  applicable rather than forcing one.

===========================================================================
CATEGORY ATTRIBUTES  (field: category_attributes)
===========================================================================
- List the category-specific attributes Etsy actually asks for THIS product's category, each
  with a recommended, VALID value inferred from the product. Only include attributes that
  genuinely apply (aim for 3-8). Use standard Etsy attribute names and realistic values.
  Examples by category:
  * Necklaces / jewellery: Chain Style, Pendant Style, Materials, Gemstone, Necklace Length, Font.
  * Rings: Ring Size, Band Colour, Stone, Metal Purity.
  * Candles: Scent, Wax Type, Burn Time, Container.
  * Mugs / drinkware: Capacity, Material, Care Instructions, Dishwasher Safe, Microwave Safe.
  * Clothing: Garment Type, Neckline, Sleeve Length, Fit, Size.
  * Home decor / prints: Orientation, Frame, Print Type, Room.
- Never invent brand names or fake specifications. If a value is genuinely unknown, use a
  sensible common value or "Not specified".

===========================================================================
PRICING  (fields: pricing.*)
===========================================================================
- You are given source_price and source_currency (the dropship/cost price) when available.
- Set a RETAIL price in BOTH GBP and USD that yields a healthy margin AFTER Etsy costs.
  Model Etsy costs as: transaction fee 6.5% + payment processing ~4% + listing fee ~0.20,
  targeting a net margin of ~35-45% over landed source cost. Floor: retail >= source_price
  x 2.5 unless absurd for the category. Round to charming price points (e.g. 24.99).
- Price each market to its own expectation (do not just FX-convert). State assumed FX and a
  short margin_note. Populate margin_pct with the approximate net margin % after fees.
- If no source price was detected, price to the category's typical Etsy range and say so in
  margin_note; set source_price to 0.

===========================================================================
VARIATIONS  (fields: variation_axes, variations)
===========================================================================
- variation_axes: the 1-2 axes that actually vary for THIS product. If none, use a single
  "style" axis with one option.
- variations: a ready-to-upload row for EVERY combination. Each row: sku (unique,
  human-decodable e.g. "TUMB-30OZ-BLK"), option_label (what the buyer picks e.g.
  "30oz / Black"), price_gbp, price_usd (larger/premium options cost more), and a sensible
  default_quantity (e.g. 25). SKUs unique across rows.

===========================================================================
POLICY COMPLIANCE  (field: policy_check)
===========================================================================
- status: "ok" | "caution" | "likely_violation".
  * likely_violation: counterfeit/trademarked/branded-character goods, prohibited items
    (weapons, medical/ingestible claims, recalled/hazardous goods), or reselling that
    misrepresents handmade where forbidden.
  * caution: dropshipped/mass-produced goods listed by a reseller, generic goods that could
    brush against IP, fragile trademark adjacency.
  * ok: clearly permissible generic/own-design finished product.
- reasons: 1-4 concrete, product-specific reasons.
- TRADEMARK SCAN: check the title, tags and description YOU wrote for ANY brand, company,
  trademark, celebrity, band, sports team, university or licensed character (e.g. Disney,
  Marvel, Nike, Adidas, Stanley, Taylor Swift, Bluey, Pokemon, NFL). If any appears, set
  status = "likely_violation", add flag "possible_trademark", and name the exact offending
  term in reasons — trademark misuse is the #1 cause of Etsy shop suspensions.
- HANDMADE HONESTY: if the MAKER declaration is "i_did"/"collective" but the source clearly
  looks like a mass-produced supplier/dropship product, add a caution reason reminding the
  seller the handmade declaration must be truthful (Etsy removes resold goods misrepresented
  as handmade). Never invent facts — only reflect what the signals show.
- ORIGINAL-DESIGN RULE (Etsy, effective Aug 2026): listings must derive from the seller's OWN
  original design. If the item looks like a generic template, mass clipart bundle, unbranded
  print-on-demand blank, or an exact copy of another maker's design, add a caution reason:
  the seller should add a genuine original element (custom art, personalisation, unique combo)
  to stay compliant. Add flag "original_design_risk" when this applies.
- AI-IMAGE / MISREPRESENTATION: if a product photo was analysed, the copy must match what the
  item ACTUALLY is — never claim materials, sizes or features the photo cannot confirm. Etsy's
  automated checks now suspend for misleading listings BEFORE human review.
- flags: short slugs (e.g. possible_trademark, dropshipped, mass_produced, original_design_risk);
  escalate to "likely_violation" if branded or prohibited signals appear.

===========================================================================
AI SUGGESTIONS  (field: suggestions)
===========================================================================
- alt_titles: 2-3 alternative titles (each <=140 chars) with a DIFFERENT keyword angle or
  order than the main title, so the seller can A/B test.
- title_tips: 2-4 SHORT, SPECIFIC, actionable tips to improve THE TITLE YOU WROTE, in Etsy's
  own coaching style. Examples of the tone: "Consider using 14 words or fewer",
  "Front-load your strongest keyword in the first 40 characters", "Add a colour or material
  word buyers search for", "Avoid repeating the same word twice". Tailor each tip to the
  actual title (reference its real word count / wording); do not give generic advice.
- extra_keywords: 6-10 additional long-tail buyer phrases (beyond the 13 tags) the seller
  can rotate into tags or weave into the description.
- tips: 3-5 short, specific tips to improve this listing's ranking or conversions (photos,
  pricing, occasion/holiday timing, shop sections). Product-specific, never generic filler.

===========================================================================
ETSY COMPETITION  (fields: search_query, competition)
===========================================================================
Give the seller an honest read of the Etsy marketplace for THIS product, using your knowledge
of Etsy. Do NOT fabricate exact live numbers or name specific real shops/listings.
- search_query: the single best 2-4 word phrase a UK/US buyer would type into Etsy search to
  find this exact type of product (e.g. "personalised name necklace", "custom pet portrait").
- competition.level: how saturated this niche is on Etsy — "low" | "medium" | "high" |
  "saturated". Base it on how common the item is.
- competition.estimate: a plain-language estimate of how many similar listings exist
  (e.g. "tens of thousands of similar listings" / "a few hundred listings"). Make clear it is
  an estimate, never a precise count.
- competition.demand: buyer demand for this item on Etsy — "low" | "medium" | "high".
- competition.price_range: the typical Etsy selling price range for this type of item, in the
  seller's market currency (£ and/or $), e.g. "£8-£25 (most around £14)".
- competition.stand_out: 2-4 SPECIFIC, actionable ways to beat the competition for THIS product
  (a unique personalisation, a bundle, an under-served keyword, better photography angle, a gift
  framing). Tailor to the actual product; no generic filler.

===========================================================================
FINAL SELF-VERIFICATION (do silently, then emit JSON only)
===========================================================================
title <= 140, front-loaded, no caps/promo/TM · exactly 13 tags each <= 20 chars lowercase
multi-word no dup words · description first sentence carries primary+secondary keywords ·
<= 13 materials · taxonomy leaf chosen · attributes valid enums · who/what/when correct for
a dropshipped finished product · GBP+USD retail with real margin · every variation row
complete · policy_check reflects reality · every field specific, non-placeholder, unique.
Output ONLY the JSON object.
SYS;
}

function etsy_schema(): array
{
    $colours = ['beige','black','blue','bronze','brown','clear','copper','gold','grey','green','orange','pink','purple','rainbow','red','rose_gold','silver','white','yellow','multicolour'];
    return [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['title','tags','description','materials','taxonomy_path','attributes','category_attributes','pricing','variation_axes','variations','policy_check','suggestions','competition','search_query'],
        'properties' => [
            'title'       => ['type' => 'string'],
            'tags'        => ['type' => 'array', 'items' => ['type' => 'string']],
            'description' => ['type' => 'string'],
            'materials'   => ['type' => 'array', 'items' => ['type' => 'string']],
            'taxonomy_path' => ['type' => 'string'],
            'attributes'  => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['who_made','what_is_it','when_made','primary_colour','secondary_colour','occasion','holiday','style'],
                'properties' => [
                    'who_made'   => ['type' => 'string', 'enum' => ['i_did','someone_else','collective']],
                    'what_is_it' => ['type' => 'string', 'enum' => ['finished_product','supply_or_tool']],
                    'when_made'  => ['type' => 'string', 'enum' => ['made_to_order','2020_2026','2010_2019','2007_2009','2000_2006','1990s','1980s','1970s','1960s','1950s','1940s','1930s','1920s','1910s','1900s','1800s','1700s','before_1700']],
                    'primary_colour'   => ['type' => 'string', 'enum' => $colours],
                    'secondary_colour' => ['type' => 'string', 'enum' => array_merge(['none'], $colours)],
                    'occasion' => ['type' => 'string', 'enum' => ['none','anniversary','baptism','birthday','engagement','graduation','housewarming','new_baby','prom','retirement','sympathy','wedding','get_well','moving','everyday']],
                    'holiday'  => ['type' => 'string', 'enum' => ['none','christmas','halloween','valentines','mothers_day','fathers_day','easter','thanksgiving','new_years','hanukkah','diwali','st_patricks_day','fourth_of_july','eid']],
                    'style'    => ['type' => 'string', 'enum' => ['none','minimalist','modern','boho','farmhouse','rustic','vintage','cottagecore','scandinavian','industrial','coastal','art_deco','kawaii','gothic','preppy','mid_century','luxury','traditional','contemporary']],
                ],
            ],
            'pricing' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['source_price','source_currency','retail_gbp','retail_usd','assumed_fx_gbp_usd','margin_pct','margin_note'],
                'properties' => [
                    'source_price'       => ['type' => 'number'],
                    'source_currency'    => ['type' => 'string'],
                    'retail_gbp'         => ['type' => 'number'],
                    'retail_usd'         => ['type' => 'number'],
                    'assumed_fx_gbp_usd' => ['type' => 'number'],
                    'margin_pct'         => ['type' => 'number'],
                    'margin_note'        => ['type' => 'string'],
                ],
            ],
            'variation_axes' => [
                'type' => 'array',
                'items' => ['type' => 'string', 'enum' => ['size','colour','material','style','scent','bundle','personalisation','finish','length','capacity']],
            ],
            'variations' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['sku','option_label','price_gbp','price_usd','default_quantity'],
                    'properties' => [
                        'sku'              => ['type' => 'string'],
                        'option_label'     => ['type' => 'string'],
                        'price_gbp'        => ['type' => 'number'],
                        'price_usd'        => ['type' => 'number'],
                        'default_quantity' => ['type' => 'integer'],
                    ],
                ],
            ],
            'policy_check' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['status','reasons','flags'],
                'properties' => [
                    'status'  => ['type' => 'string', 'enum' => ['ok','caution','likely_violation']],
                    'reasons' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'flags'   => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['dropshipped','mass_produced','possible_trademark','ip_adjacent','prohibited_category','handmade_misrepresentation','safety_or_medical_claim','none']]],
                ],
            ],
            'suggestions' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['alt_titles','title_tips','extra_keywords','tips'],
                'properties' => [
                    'alt_titles'     => ['type' => 'array', 'items' => ['type' => 'string']],
                    'title_tips'     => ['type' => 'array', 'items' => ['type' => 'string']],
                    'extra_keywords' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'tips'           => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
            ],
            'category_attributes' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['name', 'value'],
                    'properties' => [
                        'name'  => ['type' => 'string'],
                        'value' => ['type' => 'string'],
                    ],
                ],
            ],
            'search_query' => ['type' => 'string'],
            'competition' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['level','estimate','price_range','demand','stand_out'],
                'properties' => [
                    'level'       => ['type' => 'string', 'enum' => ['low','medium','high','saturated']],
                    'estimate'    => ['type' => 'string'],
                    'price_range' => ['type' => 'string'],
                    'demand'      => ['type' => 'string', 'enum' => ['low','medium','high']],
                    'stand_out'   => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
            ],
        ],
    ];
}
