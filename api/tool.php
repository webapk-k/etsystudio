<?php
/* tool.php — POST { tool, input, market } -> AI-powered mini tools (category, shop, comptags, spell).
 * Shares the provider/config pattern with generate.php/keywords.php. AI-based (honest estimates). */

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
        $client = new Gemini($key, $config['gemini_model'] ?? 'gemini-2.5-flash', 40, OpenRouter::fallbackFromConfig($config));
    } elseif ($provider === 'openrouter') {
        $key = trim($config['openrouter_api_key'] ?? '');
        if ($key === '' || stripos($key, 'PASTE') !== false) { echo json_encode(['error' => 'Add your OpenRouter API key in config.php (openrouter.ai).']); exit; }
        $client = new OpenRouter($key, $config['openrouter_model'] ?? 'google/gemini-2.0-flash-exp:free');
    } else {
        $key = trim($config['anthropic_api_key'] ?? '');
        if ($key === '' || stripos($key, 'REPLACE') !== false || !str_starts_with($key, 'sk-ant-')) { echo json_encode(['error' => 'Add your Anthropic API key in config.php.']); exit; }
        $client = new Anthropic($key, $config['anthropic_model'] ?? 'claude-opus-4-8');
    }

    $in     = json_decode(file_get_contents('php://input'), true) ?: [];
    $tool   = (string) ($in['tool'] ?? '');
    $input  = trim((string) ($in['input'] ?? ''));
    $market = in_array($in['market'] ?? '', ['uk', 'us', 'both'], true) ? $in['market'] : 'both';
    if ($input === '') { throw new RuntimeException('Please enter something to work with.'); }
    if (mb_strlen($input) > 6000) { $input = mb_substr($input, 0, 6000); }
    $mk = ['uk' => 'UK (en-GB)', 'us' => 'US (en-US)', 'both' => 'UK + US'][$market];

    switch ($tool) {
        case 'category':
            $system = 'You are an Etsy taxonomy expert. Given a product, return the 1-3 most accurate Etsy category '
                    . 'breadcrumb paths (Etsy\'s real category tree, joined by " > "), each with a confidence level and a '
                    . 'one-line reason, plus the key category-specific attributes Etsy would ask for this product. Output ONLY JSON.';
            $user = "PRODUCT: {$input}\nMARKET: {$mk}\n\nReturn ONLY: {\"paths\":[{\"path\":string,\"confidence\":\"high|medium|low\",\"why\":string}],\"attributes\":[{\"name\":string,\"value\":string}]}";
            $schema = tool_schema(['paths' => ['type'=>'array','items'=>obj(['path'=>'string','confidence'=>'string','why'=>'string'])], 'attributes' => ['type'=>'array','items'=>obj(['name'=>'string','value'=>'string'])]], ['paths','attributes']);
            break;

        case 'shop':
            $system = 'You are an Etsy shop strategist. Given a shop niche, name, or description, give an honest, specific '
                    . 'analysis of that NICHE on Etsy using your knowledge (do NOT fabricate live sales numbers or claim to '
                    . 'have accessed the shop). Output ONLY JSON.';
            $user = "SHOP / NICHE: {$input}\nMARKET: {$mk}\n\nReturn ONLY: {\"overview\":string,\"strengths\":[string],\"gaps\":[string],\"opportunities\":[string],\"suggested_tags\":[string (<=20 chars, up to 13)]}";
            $schema = tool_schema(['overview'=>['type'=>'string'],'strengths'=>arr(),'gaps'=>arr(),'opportunities'=>arr(),'suggested_tags'=>arr()], ['overview','strengths','gaps','opportunities','suggested_tags']);
            break;

        case 'comptags':
            $system = 'You are an Etsy SEO analyst. Given a competitor listing (its title and/or description text), infer the '
                    . '13 tags they are most likely using, then propose 13 BETTER tags (each <=20 chars, multi-word, low '
                    . 'duplication, buyer-intent) to out-rank them, plus 2-4 short notes. Output ONLY JSON.';
            $user = "COMPETITOR LISTING:\n{$input}\nMARKET: {$mk}\n\nReturn ONLY: {\"likely_tags\":[string],\"better_tags\":[string],\"notes\":[string]}";
            $schema = tool_schema(['likely_tags'=>arr(),'better_tags'=>arr(),'notes'=>arr()], ['likely_tags','better_tags','notes']);
            break;

        case 'spell':
            $system = 'You are a proofreader for Etsy listings (US/UK English). Find likely spelling errors and typos in the '
                    . 'given text/tags and suggest corrections. Ignore brand names and deliberate stylistic choices. Output ONLY JSON.';
            $user = "TEXT:\n{$input}\nMARKET: {$mk}\n\nReturn ONLY: {\"issues\":[{\"word\":string,\"suggestion\":string}],\"clean\":boolean}";
            $schema = tool_schema(['issues'=>['type'=>'array','items'=>obj(['word'=>'string','suggestion'=>'string'])],'clean'=>['type'=>'boolean']], ['issues','clean']);
            break;

        default:
            throw new RuntimeException('Unknown tool.');
    }

    if ($provider === 'gemini' || $provider === 'openrouter') {
        $user .= "\n\nReturn ONLY the raw JSON object, no markdown or code fences.";
    }

    $raw  = $client->message($system, $user, ['max_tokens' => 3500, 'json_schema' => $schema]);
    $data = json_decode($raw, true);
    if (!is_array($data)) { throw new RuntimeException('The model returned an unexpected format. Please try again.'); }

    echo json_encode(['ok' => true, 'tool' => $tool, 'result' => $data], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

function arr(): array { return ['type' => 'array', 'items' => ['type' => 'string']]; }
function obj(array $props): array {
    $p = []; foreach ($props as $k => $t) { $p[$k] = ['type' => $t]; }
    return ['type' => 'object', 'additionalProperties' => false, 'required' => array_keys($props), 'properties' => $p];
}
function tool_schema(array $props, array $required): array {
    return ['type' => 'object', 'additionalProperties' => false, 'required' => $required, 'properties' => $props];
}
