<?php
/* keywords.php — POST { seed, market } -> eRank-style keyword research (AI estimates).
 * NOTE: Etsy does not expose real search-volume for free, so demand/competition are AI
 * ESTIMATES (relative), paired with real "See on Etsy" verification links in the UI. */

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

    $input  = json_decode(file_get_contents('php://input'), true) ?: [];
    $seed   = trim((string) ($input['seed'] ?? ''));
    $market = in_array($input['market'] ?? '', ['uk', 'us', 'both'], true) ? $input['market'] : 'both';
    if ($seed === '') { throw new RuntimeException('Type a keyword or product to research.'); }
    if (mb_strlen($seed) > 80) { $seed = mb_substr($seed, 0, 80); }
    $mkLabel = ['uk' => 'UK (Etsy.com UK buyers, en-GB)', 'us' => 'US (Etsy.com US buyers, en-US)', 'both' => 'UK + US'][$market];

    $system = <<<'SYS'
You are an expert Etsy SEO keyword analyst with current knowledge of how buyers search on Etsy.
For a seed keyword you produce a keyword-research report shaped like eRank's Keyword Tool.

You do NOT have Etsy's private search-volume database, so every number is a careful RELATIVE
ESTIMATE, not a real figure:
- demand  = how much buyers search this on Etsy, "low" | "medium" | "high".
- competition = how many sellers already target it, "low" | "medium" | "high".
- opportunity = 0-100 score for a SMALL/new seller = high demand + low competition + specific
  long-tail intent. Long-tail, specific buyer phrases should score higher than broad head terms.
- trending = true only if the phrase is seasonally rising or currently hot.

Rules:
- Return 26-34 RELATED keywords: a mix of head terms and (mostly) long-tail buyer phrases that
  real shoppers type — include gift angles, occasions, styles, materials, "for her/him", "personalised".
- Keep each keyword lowercase, natural, 2-6 words, no brand/trademark names.
- top_tags = the 13 BEST ready-to-use Etsy tags (each <=20 characters, multi-word, low duplication).
- ideas = 4-6 specific product ideas a seller could make/source for this niche.
- Never invent exact search counts. Output ONLY the JSON object.
SYS;

    $user = "SEED KEYWORD: {$seed}\nTARGET MARKET: {$mkLabel}\n\n"
          . "Return ONLY a JSON object of this exact shape (no markdown):\n"
          . '{'
          . '"stats":{"demand":"low|medium|high","competition":"low|medium|high","opportunity":0-100,'
          . '"trend":"rising|steady|seasonal|declining","trend_note":string,"best_months":array of month names,'
          . '"summary":string (1-2 lines, honest take for a small seller)},'
          . '"keywords":[{"keyword":string,"demand":"low|medium|high","competition":"low|medium|high",'
          . '"opportunity":0-100,"trending":boolean} ...26-34 items],'
          . '"top_tags":array of 13 strings (<=20 chars each),'
          . '"ideas":array of 4-6 strings}';

    $schema = [
        'type' => 'object', 'additionalProperties' => false,
        'required' => ['stats', 'keywords', 'top_tags', 'ideas'],
        'properties' => [
            'stats' => [
                'type' => 'object', 'additionalProperties' => false,
                'required' => ['demand', 'competition', 'opportunity', 'trend', 'trend_note', 'best_months', 'summary'],
                'properties' => [
                    'demand'      => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                    'competition' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                    'opportunity' => ['type' => 'integer'],
                    'trend'       => ['type' => 'string', 'enum' => ['rising', 'steady', 'seasonal', 'declining']],
                    'trend_note'  => ['type' => 'string'],
                    'best_months' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'summary'     => ['type' => 'string'],
                ],
            ],
            'keywords' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object', 'additionalProperties' => false,
                    'required' => ['keyword', 'demand', 'competition', 'opportunity', 'trending'],
                    'properties' => [
                        'keyword'     => ['type' => 'string'],
                        'demand'      => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                        'competition' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                        'opportunity' => ['type' => 'integer'],
                        'trending'    => ['type' => 'boolean'],
                    ],
                ],
            ],
            'top_tags' => ['type' => 'array', 'items' => ['type' => 'string']],
            'ideas'    => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ];

    $raw  = $client->message($system, $user, ['max_tokens' => 5000, 'json_schema' => $schema]);
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['keywords'])) { throw new RuntimeException('The model returned an unexpected format. Please try again.'); }

    // Compute accurate word/char counts server-side; clamp opportunity; de-dupe keywords.
    $seen = [];
    $rows = [];
    foreach ($data['keywords'] as $k) {
        $kw = trim((string) ($k['keyword'] ?? ''));
        if ($kw === '') { continue; }
        $lc = mb_strtolower($kw);
        if (isset($seen[$lc])) { continue; }
        $seen[$lc] = true;
        $rows[] = [
            'keyword'     => $kw,
            'words'       => count(preg_split('~\s+~', trim($kw), -1, PREG_SPLIT_NO_EMPTY)),
            'chars'       => mb_strlen($kw),
            'demand'      => in_array($k['demand'] ?? '', ['low', 'medium', 'high'], true) ? $k['demand'] : 'medium',
            'competition' => in_array($k['competition'] ?? '', ['low', 'medium', 'high'], true) ? $k['competition'] : 'medium',
            'opportunity' => max(0, min(100, (int) ($k['opportunity'] ?? 50))),
            'trending'    => !empty($k['trending']),
        ];
    }
    $data['keywords'] = $rows;
    if (isset($data['stats']['opportunity'])) {
        $data['stats']['opportunity'] = max(0, min(100, (int) $data['stats']['opportunity']));
    }

    $authUser = els_user_by_token(trim((string) ($input['token'] ?? '')));
    if ($authUser) { els_history_add($authUser['email'], $authUser['name'] ?? '', 'keyword', $seed); }

    echo json_encode(['ok' => true, 'seed' => $seed, 'market' => $market, 'data' => $data], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
