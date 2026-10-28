<?php
/**
 * Gemini — minimal Google Gemini (Generative Language API) client over cURL.
 * Uses the FREE tier. Returns JSON text (responseMimeType = application/json).
 */

if (!defined('ETSY_STUDIO')) { http_response_code(403); exit('Forbidden'); }

class GeminiException extends Exception { public bool $retryable = false; }

class Gemini
{
    private string $apiKey;
    private string $model;
    private int $timeout;
    private ?string $caBundle = null;
    private ?object $fallback;   // e.g. a free OpenRouter client, used when every Gemini model is out of quota / busy

    public function __construct(string $apiKey, string $model = 'gemini-2.5-flash', int $timeout = 40, ?object $fallback = null)
    {
        $this->fallback = $fallback;
        $this->apiKey  = $apiKey;
        $this->model   = $model;
        $this->timeout = $timeout;
    }

    /**
     * @param string       $system      system instruction
     * @param string|array $userContent user text (string)
     * @param array        $opts        ['max_tokens'=>int]  (json_schema is ignored; JSON is forced)
     */
    /**
     * Try the configured model, retrying transient "busy"/rate-limit errors, then falling back
     * to lighter free models — so the caller almost never sees a 503/overload.
     */
    public function message(string $system, $userContent, array $opts = []): string
    {
        $chain = [$this->model];
        foreach (['gemini-2.5-flash-lite', 'gemini-flash-latest'] as $fb) {   // fast fallbacks when busy
            if (!in_array($fb, $chain, true)) $chain[] = $fb;
        }
        $last = null;
        foreach ($chain as $model) {
            for ($i = 0; $i < 2; $i++) {
                try {
                    return $this->callOnce($model, $system, $userContent, $opts);
                } catch (GeminiException $e) {
                    $last = $e;
                    if (!$e->retryable) throw $e;   // fatal (bad key, blocked) — stop now
                    usleep(700000 * ($i + 1));        // 0.7s, 1.4s backoff, then next model
                }
            }
        }
        if ($this->fallback) {
            try {
                return $this->fallback->message($system, $userContent, $opts);
            } catch (Exception $fe) {
                error_log('Gemini fallback failed: ' . $fe->getMessage());
            }
        }
        throw ($last ?: new GeminiException('Gemini is busy right now — please try again in a few seconds.'));
    }

    /**
     * VISION: look at one or more product photos and return a rich plain-text description that the
     * listing generator can use as its product signal. Each image = ['mime'=>'image/jpeg','data'=>base64].
     * Runs on the free gemini-2.5-flash vision model with thinking off.
     */
    public function describeProduct(array $images): string
    {
        $parts = [];
        foreach ($images as $img) {
            if (!empty($img['mime']) && !empty($img['data'])) {
                $parts[] = ['inline_data' => ['mime_type' => $img['mime'], 'data' => $img['data']]];
            }
        }
        if (!$parts) {
            throw new GeminiException('No readable image was provided.');
        }
        $parts[] = ['text' =>
            "You are looking at product photo(s) for an Etsy listing. Describe the product in rich, "
          . "specific detail so a copywriter can list it accurately:\n"
          . "- Exactly WHAT the item is (specific product type, not vague).\n"
          . "- Likely materials, colour(s), size/scale cues, and finish.\n"
          . "- Style / aesthetic (e.g. boho, minimalist, vintage, kawaii) and who it's for.\n"
          . "- Notable features, components, and use/occasion.\n"
          . "- Any visible TEXT, brand name, logo or character — quote it exactly (this matters for trademark safety).\n"
          . "Write 6-10 plain-text sentences. Do not invent details you cannot see."];

        $body = [
            'contents' => [['role' => 'user', 'parts' => $parts]],
            'generationConfig' => [
                'temperature'     => 0.4,
                'maxOutputTokens' => 1200,
                'thinkingConfig'  => ['thinkingBudget' => 0],
            ],
        ];
        $model = 'gemini-2.5-flash';
        $url   = 'https://generativelanguage.googleapis.com/v1beta/models/'
               . rawurlencode($model) . ':generateContent';
        $resp  = $this->request($url, $body);

        if (!empty($resp['promptFeedback']['blockReason'])) {
            throw new GeminiException('The photo was blocked (' . $resp['promptFeedback']['blockReason'] . '). Try another image.');
        }
        $text = '';
        foreach (($resp['candidates'][0]['content']['parts'] ?? []) as $part) {
            if (isset($part['text'])) { $text .= $part['text']; }
        }
        $text = trim($text);
        if ($text === '') {
            $e = new GeminiException('Could not read the photo. Try a clearer image or use the link/paste instead.');
            $e->retryable = true;
            throw $e;
        }
        return $text;
    }

    protected function callOnce(string $model, string $system, $userContent, array $opts): string
    {
        $userText = is_array($userContent) ? json_encode($userContent) : (string) $userContent;
        $gen = [
            'responseMimeType' => 'application/json',
            'maxOutputTokens'  => $opts['max_tokens'] ?? 6000,
            'temperature'      => 0.6,
        ];
        // Turn OFF "thinking" on models that support it — much faster and avoids long hangs.
        if (preg_match('/2\.5|flash-latest|flash-lite-latest/i', $model)) {
            $gen['thinkingConfig'] = ['thinkingBudget' => 0];
        }
        $body = [
            'system_instruction' => ['parts' => [['text' => $system]]],
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $userText]]],
            ],
            'generationConfig' => $gen,
        ];

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
             . rawurlencode($model) . ':generateContent';
        $resp = $this->request($url, $body);

        if (!empty($resp['promptFeedback']['blockReason'])) {
            throw new GeminiException('Gemini blocked this content (' . $resp['promptFeedback']['blockReason'] . '). Try a different product.');
        }
        $cand = $resp['candidates'][0] ?? null;
        if (!$cand) {
            $e = new GeminiException('Gemini returned no result.'); $e->retryable = true; throw $e;
        }
        if (($cand['finishReason'] ?? '') === 'SAFETY') {
            throw new GeminiException('Gemini declined this content for safety reasons. Try a different product.');
        }

        $text = '';
        foreach (($cand['content']['parts'] ?? []) as $part) {
            if (isset($part['text'])) $text .= $part['text'];
        }
        $text = $this->stripFences(trim($text));
        if ($text === '') {
            $e = new GeminiException('Gemini returned an empty response (finish: ' . ($cand['finishReason'] ?? 'unknown') . ').');
            $e->retryable = true; throw $e;
        }
        return $text;
    }

    private function stripFences(string $s): string
    {
        if (str_starts_with($s, '```')) {
            $s = preg_replace('~^```[a-zA-Z]*\s*~', '', $s);
            $s = preg_replace('~\s*```$~', '', $s);
        }
        return trim($s);
    }

    private function request(string $url, array $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'x-goog-api-key: ' . $this->apiKey],
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
            $e = new GeminiException('Could not reach the Gemini API (' . $err . ').');
            $e->retryable = true;   // network blip — retry / fall back
            throw $e;
        }
        $json = json_decode($raw, true);
        if ($status < 200 || $status >= 300) {
            $msg = $json['error']['message'] ?? ('HTTP ' . $status);
            if ($status === 400 && stripos($msg, 'API key') !== false) $msg = 'Your Gemini API key was rejected. Check the key in config.php.';
            if ($status === 403) $msg = 'Gemini rejected the key or the request. Make sure the key is valid (aistudio.google.com).';
            if ($status === 429) $msg = 'Gemini free limit reached (quota exhausted). Wait about a minute and try again — or enable billing on your key / paste a fresh free key in config.php for higher limits.';
            if ($status === 503) $msg = 'Gemini is busy (high demand). Please try again in a few seconds.';
            $e = new GeminiException('Gemini API error: ' . $msg);
            $e->retryable = in_array($status, [429, 500, 503], true);   // transient → retry / fall back
            throw $e;
        }
        if (!is_array($json)) {
            throw new GeminiException('The Gemini API returned an unreadable response.');
        }
        return $json;
    }

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
