<?php
/* OpenRouter — OpenAI-compatible client (many models incl. free). Retries transient errors. */

if (!defined('ETSY_STUDIO')) { http_response_code(403); exit('Forbidden'); }

class OpenRouterException extends Exception { public bool $retryable = false; }

class OpenRouter
{
    private string $apiKey;
    private string $model;
    private int $timeout;
    private ?string $caBundle = null;

    public function __construct(string $apiKey, string $model = 'google/gemini-2.0-flash-exp:free', int $timeout = 60)
    {
        $this->apiKey = $apiKey;
        $this->model  = $model;
        $this->timeout = $timeout;
    }

    /** Free backup client for when Gemini's free quota runs out; null if no OpenRouter key is set. */
    public static function fallbackFromConfig(array $config): ?self
    {
        $key = trim((string) ($config['openrouter_api_key'] ?? ''));
        if ($key === '' || stripos($key, 'PASTE') !== false) return null;
        return new self($key, (string) ($config['openrouter_fallback_model'] ?? '') ?: 'nvidia/nemotron-3-ultra-550b-a55b:free,nvidia/nemotron-3.5-super-vl:free,poolside/laguna-s-2.1:free', 90);
    }

    public function message(string $system, $userContent, array $opts = []): string
    {
        $userText = is_array($userContent) ? json_encode($userContent) : (string) $userContent;
        $last = null;
        for ($i = 0; $i < 3; $i++) {
            try {
                return $this->callOnce($system, $userText, $opts);
            } catch (OpenRouterException $e) {
                $last = $e;
                if (!$e->retryable) throw $e;
                usleep(700000 * ($i + 1));
            }
        }
        throw ($last ?: new OpenRouterException('OpenRouter is busy — please try again in a moment.'));
    }

    private function callOnce(string $system, string $userText, array $opts): string
    {
        $models = array_values(array_filter(array_map('trim', explode(',', $this->model))));  // "a,b,c" = OpenRouter tries each in turn
        $body = [
            'model'    => $models[0] ?? $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $userText],
            ],
            'response_format' => ['type' => 'json_object'],
            'max_tokens'      => $opts['max_tokens'] ?? 6000,
            'temperature'     => 0.6,
        ];
        if (count($models) > 1) $body['models'] = $models;
        $resp = $this->request($body);
        $content = $resp['choices'][0]['message']['content'] ?? '';
        if (is_array($content)) {
            $t = ''; foreach ($content as $p) { $t .= is_array($p) ? ($p['text'] ?? '') : $p; } $content = $t;
        }
        $content = $this->stripFences(trim((string) $content));
        if ($content === '') {
            $e = new OpenRouterException('OpenRouter returned an empty response.'); $e->retryable = true; throw $e;
        }
        return $content;
    }

    private function stripFences(string $s): string
    {
        if (str_starts_with($s, '```')) {
            $s = preg_replace('~^```[a-zA-Z]*\s*~', '', $s);
            $s = preg_replace('~\s*```$~', '', $s);
        }
        return trim($s);
    }

    private function request(array $body): array
    {
        $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'HTTP-Referer: http://localhost/etsyai',
                'X-Title: Etsy Listing Studio',
            ],
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => $this->timeout,
        ]);
        $bundle = $this->detectCaBundle();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, (bool) $bundle);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $bundle ? 2 : 0);
        if ($bundle) curl_setopt($ch, CURLOPT_CAINFO, $bundle);

        $raw    = curl_exec($ch);
        $errno  = curl_errno($ch);
        $err    = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $errno) {
            $e = new OpenRouterException('Could not reach OpenRouter (' . $err . ').'); $e->retryable = true; throw $e;
        }
        $json = json_decode($raw, true);
        if ($status < 200 || $status >= 300) {
            $msg = $json['error']['message'] ?? ('HTTP ' . $status);
            if ($status === 401) $msg = 'Your OpenRouter API key was rejected. Check the key in config.php.';
            if ($status === 402) $msg = 'OpenRouter: insufficient credits. Add a little credit, or use a free model (…:free).';
            if ($status === 429) $msg = 'OpenRouter rate limit hit for this model.';
            $e = new OpenRouterException('OpenRouter error: ' . $msg);
            $e->retryable = in_array($status, [429, 500, 502, 503], true);
            throw $e;
        }
        if (!is_array($json)) throw new OpenRouterException('OpenRouter returned an unreadable response.');
        return $json;
    }

    private function detectCaBundle(): ?string
    {
        if ($this->caBundle !== null) return $this->caBundle ?: null;
        foreach (['curl.cainfo', 'openssl.cafile'] as $k) { $v = ini_get($k); if ($v && is_file($v)) return $this->caBundle = $v; }
        foreach ([__DIR__ . '/cacert.pem', 'C:/xampp/php/extras/ssl/cacert.pem', 'C:/xampp/apache/bin/curl-ca-bundle.crt', 'C:/xampp/php/cacert.pem'] as $c) {
            if (is_file($c)) return $this->caBundle = $c;
        }
        return $this->caBundle = '';
    }
}
