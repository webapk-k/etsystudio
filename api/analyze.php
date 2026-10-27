<?php
/* analyze.php — "Look up any Etsy listing or shop": scrape a URL + AI SEO analysis.
 * POST { url, token } -> { result } */

define('ETSY_STUDIO', 1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require __DIR__ . '/lib/Scraper.php';
require __DIR__ . '/lib/Anthropic.php';
require __DIR__ . '/lib/Gemini.php';
require __DIR__ . '/lib/OpenRouter.php';
require __DIR__ . '/lib/store.php';

$configPath = dirname(__DIR__) . '/config.php';
if (!file_exists($configPath)) { echo json_encode(['error' => 'Setup needed: add your API key in config.php.']); exit; }
$config   = require $configPath;
$provider = strtolower($config['provider'] ?? 'anthropic');

try {
    if ($provider === 'gemini') {
        $key = trim($config['gemini_api_key'] ?? '');
        if ($key === '' || stripos($key, 'PASTE') !== false) { echo json_encode(['error' => 'Add your free Gemini API key in config.php (aistudio.google.com).']); exit; }
        $client = new Gemini($key, $config['gemini_model'] ?? 'gemini-2.5-flash');
    } elseif ($provider === 'openrouter') {
        $key = trim($config['openrouter_api_key'] ?? '');
        if ($key === '' || stripos($key, 'PASTE') !== false) { echo json_encode(['error' => 'Add your OpenRouter API key in config.php.']); exit; }
        $client = new OpenRouter($key, $config['openrouter_model'] ?? 'google/gemini-2.0-flash-exp:free');
    } else {
        $key = trim($config['anthropic_api_key'] ?? '');
        if ($key === '' || stripos($key, 'REPLACE') !== false || !str_starts_with($key, 'sk-ant-')) { echo json_encode(['error' => 'Add your Anthropic API key in config.php.']); exit; }
        $client = new Anthropic($key, $config['anthropic_model'] ?? 'claude-opus-4-8');
    }

    $in  = json_decode(file_get_contents('php://input'), true) ?: [];
    $url   = trim((string) ($in['url'] ?? ''));
    $paste = trim((string) ($in['paste'] ?? ''));
    if ($url === '' && $paste === '') { throw new RuntimeException('Paste an Etsy listing URL, or your listing details, to analyse.'); }

    $name = ''; $fromTitle = false; $isShop = false; $hasFull = false;

    if ($paste !== '') {
        // FULL audit from pasted listing details (title / tags / description).
        $hasFull = true;
        $block = "The seller pasted their Etsy listing below. Extract its title, its tags (comma or line "
               . "separated) and its description, then audit all of them in depth.\n\nPASTED LISTING:\n"
               . mb_substr($paste, 0, 6000);
    } else {
        if (!preg_match('~^https?://~i', $url)) { $url = 'https://' . $url; }
        $host   = strtolower((string) parse_url($url, PHP_URL_HOST));
        $isEtsy = str_contains($host, 'etsy.com');
        $isShop = (bool) preg_match('~/([a-z]{2}/)?shop/~i', (string) parse_url($url, PHP_URL_PATH));
        $text = '';
        if ($isEtsy) {
            $name = els_name_from_url($url);
            if ($name === '') { throw new RuntimeException('Paste a full Etsy listing URL (…/listing/ID/product-name) — or use "Paste listing details" for a full audit.'); }
            $fromTitle = true;
        } else {
            $scraper = new Scraper(trim((string) ($config['scraper_api_key'] ?? '')), (string) ($config['jina_api_key'] ?? ''));
            $signals = $scraper->scrape($url);
            if (empty($signals['_needsPaste'])) { $name = trim((string) ($signals['name'] ?? '')); $text = trim((string) ($signals['text'] ?? '')); }
            if ($name === '' && mb_strlen($text) < 40) {
                $name = els_name_from_url($url);
                if ($name === '') { throw new RuntimeException('Could not read that page. Use "Paste listing details" for a full audit.'); }
                $fromTitle = true; $text = '';
            }
        }
        $block = "URL: {$url}\nTYPE: " . ($isShop ? 'shop' : 'listing') . "\nLISTING TITLE: {$name}\n"
               . ($fromTitle
                   ? "NOTE: the live page cannot be read (Etsy blocks bots), so ONLY the title is available. Audit the title in depth, generate the ideal tags & description, and mark tag/description/attribute checks as reminders. Do NOT claim to know its current tags, price or images."
                   : ('CONTENT: ' . mb_substr($text, 0, 4000)));
    }

    $system = <<<'SYS'
You are a meticulous Etsy listing auditor (like eRank's Listing Audit). You receive either a full
listing (title + tags + description) or only a title. Produce a deep, practical, SECTIONED audit.

Every "issues" entry has a "severity" ("error" = must fix, "warn" = should improve, "good" = passes)
and a "section" — one of: "title", "tags", "description", "media", "attributes". Cover each section:
- title: length (ideal 70-140 chars), primary keyword in the first 40 chars, repeated words.
- tags: exactly 13, none over 20 characters, NO single-word tags (name each single-word tag), multi-
  word buyer phrases, low duplication with the title. Trademark/brand risk (Disney, Jellycat, Stanley,
  Pokemon, a character name) → "error" naming it.
- description: first ~160 chars carry the main keywords; readability (flag long run-on sentences);
  mentions materials / size / use / care.
- media: you CANNOT see the photos, so give 4-6 IMAGE/PHOTO best-practice recommendations tailored to
  THIS product — e.g. use all 10 photo slots, add a short video ("error"), write keyword-rich ALT TEXT
  for each image ("error"), include a scale/dimensions shot, a lifestyle in-use shot, a plain white
  background hero, and close-up detail shots.
- attributes: remind to fill every relevant Etsy attribute (colour, occasion, material…) and pick the
  most specific category.
Give 12-18 issues total spread across the sections. When only the title is known, still audit the
title fully and mark other sections as "warn" reminders. Do not invent live view/sales numbers.
Output ONLY the JSON object.
SYS;

    $user = $block . "\n\nReturn ONLY this JSON:\n"
          . '{"name":string,"summary":string (2-3 lines: what it is + verdict),'
          . '"detected":{"title":string,"tags":[string (the listing\'s own tags, or [] if unknown)],"description":string},'
          . '"seo_score":0-100,'
          . '"issues":[{"severity":"error|warn|good","section":"title|tags|description|media|attributes","text":string} 12-18 items spread across ALL sections],'
          . '"strengths":[string],"improvements":[string (specific, actionable)],'
          . '"detected_keywords":[string (phrases this listing targets)],'
          . '"better_title":string (<=140 chars, keyword-first),'
          . '"better_tags":[13 strings (<=20 chars each, multi-word)],'
          . '"better_description":string (a rewritten, keyword-rich, readable Etsy description)}';
    if ($provider === 'gemini' || $provider === 'openrouter') { $user .= "\n\nReturn ONLY the raw JSON, no code fences."; }

    $schema = [
        'type' => 'object', 'additionalProperties' => false,
        'required' => ['name','summary','detected','seo_score','issues','strengths','improvements','detected_keywords','better_title','better_tags','better_description'],
        'properties' => [
            'name' => ['type'=>'string'], 'summary' => ['type'=>'string'],
            'detected' => ['type'=>'object','additionalProperties'=>false,'required'=>['title','tags','description'],
                'properties'=>['title'=>['type'=>'string'],'tags'=>['type'=>'array','items'=>['type'=>'string']],'description'=>['type'=>'string']]],
            'seo_score' => ['type'=>'integer'],
            'issues' => ['type'=>'array','items'=>['type'=>'object','additionalProperties'=>false,'required'=>['severity','section','text'],
                'properties'=>['severity'=>['type'=>'string','enum'=>['error','warn','good']],'section'=>['type'=>'string','enum'=>['title','tags','description','media','attributes']],'text'=>['type'=>'string']]]],
            'strengths' => ['type'=>'array','items'=>['type'=>'string']],
            'improvements' => ['type'=>'array','items'=>['type'=>'string']],
            'detected_keywords' => ['type'=>'array','items'=>['type'=>'string']],
            'better_title' => ['type'=>'string'],
            'better_tags' => ['type'=>'array','items'=>['type'=>'string']],
            'better_description' => ['type'=>'string'],
        ],
    ];

    $raw  = $client->message($system, $user, ['max_tokens' => 7500, 'json_schema' => $schema]);
    $data = json_decode($raw, true);
    if (!is_array($data)) { throw new RuntimeException('The model returned an unexpected format. Please try again.'); }
    $data['seo_score'] = max(0, min(100, (int) ($data['seo_score'] ?? 0)));

    $label = $data['name'] ?? ($name !== '' ? $name : ($url !== '' ? $url : 'pasted listing'));
    $authUser = els_user_by_token(trim((string) ($in['token'] ?? '')));
    if ($authUser) { els_history_add($authUser['email'], $authUser['name'] ?? '', 'analyze', (string) $label); }

    echo json_encode([
        'ok' => true, 'url' => $url, 'from_title' => $fromTitle, 'has_full' => $hasFull,
        'source' => ['via' => $hasFull ? 'pasted' : ($fromTitle ? 'title' : ($signals['_source'] ?? 'fetched'))],
        'result' => $data,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

/** Derive a readable product / shop name from an Etsy (or generic) URL slug. */
function els_name_from_url(string $url): string {
    $path = (string) parse_url($url, PHP_URL_PATH);
    if (preg_match('~/listing/\d+/([^/?#]+)~i', $path, $m)) { return els_slug_words($m[1]); }
    if (preg_match('~/shop/([^/?#]+)~i', $path, $m))        { return els_slug_words($m[1]) . ' (Etsy shop)'; }
    if (preg_match('~/([a-z0-9]+(?:-[a-z0-9]+){2,})/?$~i', $path, $m)) { return els_slug_words($m[1]); }
    return '';
}
function els_slug_words(string $s): string {
    $s = preg_replace('~\.(html?|php|aspx?)$~i', '', $s);
    $s = str_replace(['-', '_', '+'], ' ', $s);
    $s = trim(preg_replace('~\s+~', ' ', $s));
    return ucwords(mb_substr($s, 0, 120));
}
