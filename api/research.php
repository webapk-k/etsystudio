<?php
/* research.php — POST { keyword?, market? } -> best-selling Etsy categories / niches (AI) */

define('ETSY_STUDIO', 1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require __DIR__ . '/lib/Anthropic.php';
require __DIR__ . '/lib/Gemini.php';
require __DIR__ . '/lib/OpenRouter.php';

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
        if ($key === '' || stripos($key, 'PASTE') !== false) { echo json_encode(['error' => 'Add your OpenRouter API key in config.php (openrouter.ai).']); exit; }
        $client = new OpenRouter($key, $config['openrouter_model'] ?? 'google/gemini-2.0-flash-exp:free');
    } else {
        $key = trim($config['anthropic_api_key'] ?? '');
        if ($key === '' || stripos($key, 'REPLACE') !== false || !str_starts_with($key, 'sk-ant-')) { echo json_encode(['error' => 'Add your Anthropic API key in config.php.']); exit; }
        $client = new Anthropic($key, $config['anthropic_model'] ?? 'claude-opus-4-8');
    }

    $input   = json_decode(file_get_contents('php://input'), true) ?: [];
    $keyword = trim((string) ($input['keyword'] ?? ''));
    $market  = in_array($input['market'] ?? '', ['uk', 'us', 'both'], true) ? $input['market'] : 'both';
    $cur     = $market === 'uk' ? 'GBP (£)' : ($market === 'us' ? 'USD ($)' : 'GBP and USD');

    $system = <<<'SYS'
You are an Etsy market-research analyst with current knowledge of what sells well on Etsy.
Given an optional focus keyword, return 6-8 best-selling / high-demand Etsy categories or
niches that a small seller could realistically enter. For EACH niche give: a specific
category/niche name, a demand level, a typical retail price range in the requested currency,
one concise reason it sells, 3-4 SPECIFIC product ideas a seller could make or source, and
3-5 buyer search tags. Prefer a mix of evergreen and currently trending niches (personalised
gifts, minimalist jewellery, custom name items, digital prints & planners, stickers, home &
kitchen decor, pet products, wedding & bridesmaid, baby & nursery, etc.). Be specific and
realistic. Do NOT invent fake sales statistics or numbers. Output ONLY the JSON object.
SYS;

    $user = "MARKET: {$market}\nPRICE CURRENCY: {$cur}\n"
          . "FOCUS: " . ($keyword !== '' ? $keyword : '(overall top-selling Etsy categories)') . "\n\n"
          . "Return ONLY a JSON object of this shape:\n"
          . '{ "categories": [ { "name": string, "demand": one of ["very high","high","medium"], '
          . '"price_range": string (in the currency above), "why": string, "ideas": array of strings, "tags": array of strings } ], '
          . '"suggestions": array of 3-4 strings (specific, actionable strategy tips for a NEW small Etsy seller entering these niches — how to pick, differentiate and price) }';

    $schema = [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['categories', 'suggestions'],
        'properties' => [
            'suggestions' => ['type' => 'array', 'items' => ['type' => 'string']],
            'categories' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['name', 'demand', 'price_range', 'why', 'ideas', 'tags'],
                    'properties' => [
                        'name'        => ['type' => 'string'],
                        'demand'      => ['type' => 'string', 'enum' => ['very high', 'high', 'medium']],
                        'price_range' => ['type' => 'string'],
                        'why'         => ['type' => 'string'],
                        'ideas'       => ['type' => 'array', 'items' => ['type' => 'string']],
                        'tags'        => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                ],
            ],
        ],
    ];

    $raw  = $client->message($system, $user, ['max_tokens' => 6000, 'json_schema' => $schema]);
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['categories'])) { throw new RuntimeException('The model returned an unexpected format. Please try again.'); }

    echo json_encode(['ok' => true, 'market' => $market, 'research' => $data], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
