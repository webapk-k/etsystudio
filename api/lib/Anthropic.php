<?php
/**
 * Anthropic — minimal Claude Messages API client over cURL (no Composer needed).
 * Supports structured outputs (output_config.format = json_schema).
 */

if (!defined('ETSY_STUDIO')) { http_response_code(403); exit('Forbidden'); }

class AnthropicException extends Exception {}

class Anthropic
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;
    private int $timeout;
    private ?string $caBundle = null;

    public function __construct(string $apiKey, string $model = 'claude-opus-4-8', int $timeout = 180)
    {
        $this->apiKey  = $apiKey;
        $this->model   = $model;
        $this->baseUrl = 'https://api.anthropic.com';
        $this->timeout = $timeout;
    }

    /**
     * Call the Messages API and return the concatenated text output.
     *
     * @param string       $system      system prompt
     * @param string|array $userContent user message content (string or content blocks)
     * @param array        $opts        ['max_tokens'=>int, 'json_schema'=>array|null, 'model'=>string]
     */
    public function message(string $system, $userContent, array $opts = []): string
    {
        // NOTE: no temperature / top_p — those parameters are rejected by Opus 4.7/4.8 & Sonnet 5.
        $body = [
            'model'      => $opts['model'] ?? $this->model,
            'max_tokens' => $opts['max_tokens'] ?? 6000,
            'system'     => $system,
            'messages'   => [
                ['role' => 'user', 'content' => $userContent],
            ],
        ];
        if (!empty($opts['json_schema'])) {
            $body['output_config'] = [
                'format' => [
                    'type'   => 'json_schema',
                    'schema' => $opts['json_schema'],
                ],
            ];
        }

        $resp = $this->request('/v1/messages', $body);

        $stop = $resp['stop_reason'] ?? '';
        if ($stop === 'refusal') {
            throw new AnthropicException('The model declined to process this content. Try a different product or edit the input.');
        }

        $text = '';
        foreach (($resp['content'] ?? []) as $block) {
            if (($block['type'] ?? '') === 'text') $text .= $block['text'];
        }
        if (trim($text) === '') {
            throw new AnthropicException('The model returned no text (stop_reason: ' . ($stop ?: 'unknown') . '). Please try again.');
        }
        return $text;
    }

    private function request(string $path, array $body): array
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
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
            throw new AnthropicException('Could not reach the Claude API (' . $err . '). Check your internet connection.');
        }

        $json = json_decode($raw, true);
        if ($status < 200 || $status >= 300) {
            $msg = $json['error']['message'] ?? ('HTTP ' . $status);
            if ($status === 401) $msg = 'Your Anthropic API key was rejected. Check the key in config.php.';
            if ($status === 429) $msg = 'Rate limited by the Claude API. Wait a moment and try again.';
            throw new AnthropicException('Claude API error: ' . $msg);
        }
        if (!is_array($json)) {
            throw new AnthropicException('The Claude API returned an unreadable response.');
        }
        return $json;
    }

    /** Reuse a CA bundle if one is available so HTTPS verification stays on. */
    private function detectCaBundle(): ?string
    {
        if ($this->caBundle !== null) return $this->caBundle ?: null;
        foreach (['curl.cainfo', 'openssl.cafile'] as $k) {
            $v = ini_get($k);
            if ($v && is_file($v)) return $this->caBundle = $v;
        }
        foreach ([
            __DIR__ . '/cacert.pem',
            'C:/xampp/php/extras/ssl/cacert.pem',
            'C:/xampp/apache/bin/curl-ca-bundle.crt',
            'C:/xampp/php/cacert.pem',
        ] as $c) {
            if (is_file($c)) return $this->caBundle = $c;
        }
        return $this->caBundle = '';
    }
}
