<?php
/**
 * Scraper — extracts structured product data from an arbitrary e-commerce URL
 * using only XAMPP-bundled PHP (curl, dom, libxml, mbstring, json). No Composer.
 *
 * Priority order:  JSON-LD Product > OpenGraph/product meta > Twitter card > microdata > visible text
 * Blocked / JS-only pages are detected and routed to a user "paste page content" fallback.
 */

if (!defined('ETSY_STUDIO')) { http_response_code(403); exit('Forbidden'); }

class Scraper
{
    private ?string $caBundle = null;
    private string $scraperApiKey = '';

    public function __construct(string $scraperApiKey = '')
    {
        $this->scraperApiKey = trim($scraperApiKey);
    }

    /**
     * Public entry point.
     * @param string      $url        product URL to scrape
     * @param string|null $pastedHtml optional user-pasted page HTML/text (fallback path)
     */
    public function scrape(string $url, ?string $pastedHtml = null): array
    {
        // Path A: user pasted HTML/text -> skip the network entirely.
        if ($pastedHtml !== null && trim($pastedHtml) !== '') {
            $utf8 = $this->toUtf8($pastedHtml, null);
            $data = $this->extractAll($utf8, $url);
            // Pasted content is often plain rendered text (Ctrl+A/Ctrl+C) with no structured
            // data — use the text itself as the product signal so it still works.
            if (empty($data['name']) && !empty($data['text'])) {
                $data['name'] = trim(mb_substr($data['text'], 0, 90));
            }
            $data['_source'] = 'pasted';
            return $data;
        }

        // Path B: network fetch.
        // Bot-walled marketplaces (AliExpress / Amazon / Alibaba / Temu / DHgate) always serve a
        // captcha to a direct server request, so for those we SKIP the slow, futile retries and go
        // straight to the free JS-rendering reader — much faster AND far more reliable.
        $resp = null;
        $utf8 = '';
        if (!$this->isBotWalledHost($url)) {
            $resp = $this->fetch($url);
            $utf8 = $this->toUtf8($resp['body'], $resp['contentType']);
            for ($try = 1; $try < 3 && $this->looksBlocked($resp, $utf8); $try++) {
                usleep(500000);
                $resp = $this->fetch($url);
                $utf8 = $this->toUtf8($resp['body'], $resp['contentType']);
            }
            if (!$this->looksBlocked($resp, $utf8)) {
                $data = $this->extractAll($utf8, $resp['finalUrl']);
                $data['_source'] = !empty($resp['insecure']) ? 'fetched-insecure' : 'fetched';
                return $data;
            }
        }

        // FREE fallback (and the primary path for bot-walled hosts): Jina Reader renders the page's
        // JavaScript and slips past most bot blocks — no API key required.
        $data = $this->jinaProduct($url);
        if ($data !== null) {
            return $data;
        }

        // Optional: scraping API (only if a key is configured in config.php).
        $apiHtml = $this->fetchViaApi($url);
        if ($apiHtml !== null) {
            $utf8b = $this->toUtf8($apiHtml, null);
            if (mb_strlen(trim($this->visibleText($utf8b))) > 200) {
                $data = $this->extractAll($utf8b, $url);
                if (empty($data['name']) && !empty($data['text'])) {
                    $data['name'] = trim(mb_substr($data['text'], 0, 90));
                }
                $data['_source'] = 'fetched-api';
                return $data;
            }
        }

        return [
            '_source'     => 'blocked',
            '_needsPaste' => true,
            '_status'     => $resp['status'] ?? 0,
            '_error'      => $resp['error'] ?? null,
            '_message'    => "We tried hard but this page wouldn't load automatically right now. "
                           . "Open the product page in your browser, press Ctrl+A then Ctrl+C to copy it, "
                           . "and paste it into \"Paste page content instead\" — that always works.",
            'sourceUrl'   => $resp['finalUrl'] ?? $url,
        ];
    }

    /* ------------------------------------------------------------------ *
     *  Fetching                                                          *
     * ------------------------------------------------------------------ */

    private function fetch(string $url): array
    {
        $ch = curl_init();
        $headers = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language: en-GB,en-US;q=0.9,en;q=0.8',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'Sec-Ch-Ua: "Chromium";v="125", "Google Chrome";v="125", "Not-A.Brand";v="99"',
            'Sec-Ch-Ua-Mobile: ?0',
            'Sec-Ch-Ua-Platform: "Windows"',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?1',
            'Upgrade-Insecure-Requests: 1',
        ];
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
            . '(KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 8,
            CURLOPT_AUTOREFERER    => true,
            CURLOPT_USERAGENT      => $ua,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_ENCODING       => '',   // accept gzip/deflate/br AND auto-decode
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_REFERER        => $this->originOf($url),
            CURLOPT_COOKIEFILE     => '',   // in-memory cookie jar (survives consent redirects)
            CURLOPT_COOKIEJAR      => '',
        ]);
        $this->applySsl($ch);

        $body        = curl_exec($ch);
        $errno       = curl_errno($ch);
        $error       = $errno ? curl_error($ch) : null;
        $status      = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $finalUrl    = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        // Auto-retry once with verification off if the only issue was a missing CA bundle.
        if (in_array($errno, [CURLE_SSL_CACERT, CURLE_SSL_CACERT_BADFILE, 60], true)) {
            return $this->fetchInsecureRetry($url);
        }

        return [
            'ok'          => ($body !== false && $errno === 0),
            'status'      => $status,
            'body'        => $body === false ? '' : $body,
            'contentType' => $contentType,
            'finalUrl'    => $finalUrl ?: $url,
            'error'       => $error,
        ];
    }

    private function fetchInsecureRetry(string $url): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 8,
            CURLOPT_ENCODING       => '',
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                . 'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => false,   // last resort — local tool only
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $body        = curl_exec($ch);
        $errno       = curl_errno($ch);
        $status      = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $finalUrl    = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error       = $errno ? curl_error($ch) : null;
        curl_close($ch);

        return [
            'ok'          => ($body !== false && $errno === 0),
            'status'      => $status,
            'body'        => $body === false ? '' : $body,
            'contentType' => $contentType,
            'finalUrl'    => $finalUrl ?: $url,
            'error'       => $error,
            'insecure'    => true,
        ];
    }

    private function originOf(string $url): string
    {
        $p = parse_url($url);
        if (!$p || empty($p['scheme']) || empty($p['host'])) return $url;
        return $p['scheme'] . '://' . $p['host'] . '/';
    }

    /** FREE fallback via Jina Reader (r.jina.ai) — renders JS, no key. Returns markdown text or null. */
    /** True for marketplaces that always captcha a direct server request — go straight to Jina. */
    private function isBotWalledHost(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        foreach (['aliexpress.', 'amazon.', 'alibaba.', 'temu.com', 'dhgate.com', '1688.com'] as $needle) {
            if (str_contains($host, $needle)) { return true; }
        }
        return false;
    }

    /**
     * Fetch a product via the free Jina reader and turn it into our signal array, or null if the
     * reader returned nothing usable (genuine 404 / empty). Captcha pages are already filtered
     * inside fetchViaJina, so here we only reject true "not found" pages.
     */
    private function jinaProduct(string $url): ?array
    {
        $jina = $this->fetchViaJina($url);
        if ($jina === null) { return null; }
        if (preg_match('~page not found|404 not found|no longer available|item you.?re looking for|this listing (?:has expired|is no longer available)~i', mb_substr($jina, 0, 2500))) {
            return null;
        }
        $data = $this->extractAll($this->toUtf8($jina, null), $url);
        if (empty($data['name'])) {
            if (preg_match('~^\s*Title:\s*(.+)$~mi', $jina, $m)) {
                $data['name'] = trim(preg_replace('~\s*[-|]\s*(AliExpress|Amazon|Alibaba|Etsy).*$~i', '', mb_substr($m[1], 0, 140)));
            } elseif (!empty($data['text'])) {
                $data['name'] = trim(mb_substr($data['text'], 0, 90));
            }
        }
        if (mb_strlen((string) ($data['text'] ?? '')) > 200 || !empty($data['name'])) {
            $data['_source'] = 'fetched-jina';
            return $data;
        }
        return null;
    }

    /**
     * FREE JS-rendering fallback via Jina Reader. Tries a few URL variants and skips any
     * variant that Jina itself returns as a captcha/interception page — so heavily-protected
     * desktop pages (e.g. AliExpress) automatically fall through to the version that works.
     */
    private function fetchViaJina(string $url): ?string
    {
        foreach ($this->jinaCandidates($url) as $candidate) {
            for ($attempt = 0; $attempt < 2; $attempt++) {
                $body = $this->jinaGet($candidate);
                if ($body === null) { usleep(300000); continue; }
                // Jina sometimes returns the site's own captcha/interception page — reject and try next.
                if (preg_match('~captcha interception|captcha|robot check|are you a human|verify you are human|slider verification~i', mb_substr($body, 0, 1500))) {
                    usleep(400000);
                    continue;
                }
                return $body;
            }
        }
        return null;
    }

    /** URL variants to try through Jina, best-first. */
    private function jinaCandidates(string $url): array
    {
        $list = [$url];
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        // AliExpress desktop is served a captcha even through Jina, but the MOBILE site is not —
        // so for AliExpress we try m.aliexpress.com FIRST (any region → the universal mobile host).
        if (str_contains($host, 'aliexpress.') && !str_starts_with($host, 'm.')) {
            $mobile = preg_replace('~://[^/]*aliexpress\.[^/]*~i', '://m.aliexpress.com', $url, 1);
            if (is_string($mobile) && $mobile !== $url) { array_unshift($list, $mobile); }
        }
        return array_values(array_unique($list));
    }

    /** Single Jina Reader fetch. Null on transport error / non-2xx / too-short body. */
    private function jinaGet(string $url): ?string
    {
        $ch = curl_init('https://r.jina.ai/' . $url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/125.0 Safari/537.36',
            CURLOPT_HTTPHEADER     => ['Accept: text/plain', 'X-Return-Format: markdown'],
        ]);
        $body   = curl_exec($ch);
        $errno  = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return (!$errno && $status >= 200 && $status < 300 && is_string($body) && mb_strlen($body) > 200) ? $body : null;
    }

    /** Optional fetch via ScraperAPI (renders JS, solves challenges). Null if no key / fails. */
    private function fetchViaApi(string $url): ?string
    {
        if ($this->scraperApiKey === '') return null;
        $api = 'https://api.scraperapi.com/?api_key=' . urlencode($this->scraperApiKey)
             . '&render=true&url=' . urlencode($url);
        $ch = curl_init($api);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 75,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $body   = curl_exec($ch);
        $errno  = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return (!$errno && $status >= 200 && $status < 300 && is_string($body) && $body !== '') ? $body : null;
    }

    private function detectCaBundle(): ?string
    {
        if ($this->caBundle !== null) return $this->caBundle ?: null;
        foreach (['curl.cainfo', 'openssl.cafile'] as $k) {
            $v = ini_get($k);
            if ($v && is_file($v)) { return $this->caBundle = $v; }
        }
        $candidates = [
            __DIR__ . '/cacert.pem',
            'C:/xampp/php/extras/ssl/cacert.pem',
            'C:/xampp/apache/bin/curl-ca-bundle.crt',
            'C:/xampp/php/cacert.pem',
            getenv('CURL_CA_BUNDLE') ?: '',
        ];
        foreach ($candidates as $c) {
            if ($c && is_file($c)) { return $this->caBundle = $c; }
        }
        return $this->caBundle = '';
    }

    private function applySsl($ch): void
    {
        $bundle = $this->detectCaBundle();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        if ($bundle) {
            curl_setopt($ch, CURLOPT_CAINFO, $bundle);
        }
    }

    /* ------------------------------------------------------------------ *
     *  Encoding / DOM                                                    *
     * ------------------------------------------------------------------ */

    private function toUtf8(string $html, ?string $httpContentType): string
    {
        if ($html === '') return '';
        $charset = null;
        if ($httpContentType && preg_match('~charset=([\w\-]+)~i', $httpContentType, $m)) {
            $charset = strtoupper($m[1]);
        }
        if (!$charset) {
            if (preg_match('~<meta[^>]+charset=["\']?\s*([\w\-]+)~i', $html, $m)) {
                $charset = strtoupper($m[1]);
            } elseif (preg_match('~<meta[^>]+content=["\'][^"\']*charset=([\w\-]+)~i', $html, $m)) {
                $charset = strtoupper($m[1]);
            }
        }
        if (!$charset) {
            $det = mb_detect_encoding($html, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true);
            $charset = $det ?: 'UTF-8';
        }
        if ($charset === 'UTF8')                          $charset = 'UTF-8';
        if (in_array($charset, ['LATIN1', 'ISO8859-1'], true)) $charset = 'ISO-8859-1';

        if ($charset !== 'UTF-8') {
            $converted = @mb_convert_encoding($html, 'UTF-8', $charset);
            if ($converted !== false && $converted !== '') $html = $converted;
        }
        return preg_replace('/^\xEF\xBB\xBF/', '', $html);
    }

    private function loadDom(string $utf8Html): DOMDocument
    {
        $dom  = new DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $wrapped = '<?xml encoding="UTF-8">' . $utf8Html;
        if (defined('LIBXML_HTML_NODEFDTD')) {
            $dom->loadHTML($wrapped, LIBXML_HTML_NODEFDTD | LIBXML_NOWARNING | LIBXML_NOERROR);
        } else {
            $dom->loadHTML($wrapped);
        }
        foreach (iterator_to_array($dom->childNodes) as $node) {
            if ($node->nodeType === XML_PI_NODE) $dom->removeChild($node);
        }
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        return $dom;
    }

    /* ------------------------------------------------------------------ *
     *  Blocked detection                                                 *
     * ------------------------------------------------------------------ */

    private function looksBlocked(array $resp, string $utf8Html): bool
    {
        if (!$resp['ok']) return true;
        if (in_array($resp['status'], [401, 403, 405, 406, 429, 451, 503], true)) return true;

        if ($resp['contentType'] && !preg_match('~text/html|application/xhtml~i', $resp['contentType'])
            && stripos($utf8Html, '<html') === false) {
            return true;
        }

        $len  = strlen($utf8Html);
        $text = trim($this->visibleText($utf8Html));
        if ($len < 1500 || mb_strlen($text) < 200) return true;

        $needles = [
            'captcha', 'are you a human', 'verify you are human', 'unusual traffic',
            'access denied', 'request blocked', 'px-captcha', 'cf-challenge',
            'enable javascript', 'please enable js', 'robot check',
            'to continue, please', 'security check', 'geo.captcha',
        ];
        $lc = mb_strtolower($text . ' ' . $utf8Html);
        foreach ($needles as $n) {
            if (mb_strpos($lc, $n) !== false) return true;
        }

        $hasSignal = (mb_stripos($utf8Html, 'application/ld+json') !== false)
                  || (mb_stripos($utf8Html, 'og:title') !== false)
                  || (mb_stripos($utf8Html, '<h1') !== false);
        return !$hasSignal;
    }

    private function visibleText(string $utf8Html): string
    {
        $s = preg_replace('~<script\b[^>]*>.*?</script>~is', ' ', $utf8Html);
        $s = preg_replace('~<style\b[^>]*>.*?</style>~is', ' ', $s);
        $s = preg_replace('~<noscript\b[^>]*>.*?</noscript>~is', ' ', $s);
        $s = strip_tags($s);
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return preg_replace('/\s+/u', ' ', $s);
    }

    /* ------------------------------------------------------------------ *
     *  Extraction pipeline                                               *
     * ------------------------------------------------------------------ */

    private function extractAll(string $utf8Html, string $baseUrl): array
    {
        $dom   = $this->loadDom($utf8Html);
        $xpath = new DOMXPath($dom);
        $base  = $this->resolveBaseHref($xpath, $baseUrl);

        $out = $this->emptyProduct();
        $out['sourceUrl'] = $baseUrl;

        $this->mergeProduct($out, $this->fromJsonLd($xpath, $base));
        $this->mergeProduct($out, $this->fromOpenGraph($xpath, $base));
        $this->mergeProduct($out, $this->fromTwitter($xpath, $base));
        $this->mergeProduct($out, $this->fromMicrodata($xpath, $base));
        $this->mergeProduct($out, $this->fromVisible($xpath, $base));

        $out['variations'] = $this->collectVariations($xpath, $out, $base);
        $out['images']     = $this->dedupeImages($out['images']);
        $out['text']       = mb_substr(trim($this->visibleText($utf8Html)), 0, 6000);
        return $this->finalizeText($out);
    }

    private function emptyProduct(): array
    {
        return [
            'name' => null, 'brand' => null, 'description' => null,
            'price' => null, 'currency' => null,
            'images' => [], 'specs' => [], 'variations' => [],
            'text' => '', 'sourceUrl' => null,
        ];
    }

    private function mergeProduct(array &$dst, array $src): void
    {
        foreach (['name', 'brand', 'description', 'price', 'currency'] as $k) {
            if (($dst[$k] === null || $dst[$k] === '') && !empty($src[$k])) $dst[$k] = $src[$k];
        }
        if (!empty($src['images']))     $dst['images']     = array_merge($dst['images'], $src['images']);
        if (!empty($src['specs']))      $dst['specs']      = array_merge($dst['specs'], $src['specs']);
        if (!empty($src['variations'])) $dst['variations'] = array_merge($dst['variations'], $src['variations']);
    }

    private function resolveBaseHref(DOMXPath $xpath, string $docUrl): string
    {
        $n = $xpath->query('//base[@href]')->item(0);
        if ($n) {
            $href = trim($n->getAttribute('href'));
            if ($href !== '') return $this->absolutize($href, $docUrl) ?? $docUrl;
        }
        return $docUrl;
    }

    private function absolutize(?string $link, string $base): ?string
    {
        if ($link === null) return null;
        $link = trim($link);
        if ($link === '' || stripos($link, 'data:') === 0 || stripos($link, 'javascript:') === 0) return null;
        if (preg_match('~^https?://~i', $link)) return $link;
        if (strpos($link, '//') === 0) {
            $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';
            return $scheme . ':' . $link;
        }
        $p = parse_url($base);
        if (!$p || empty($p['scheme']) || empty($p['host'])) return $link;
        $origin = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        if ($link[0] === '/') return $origin . $link;
        if ($link[0] === '#' || $link[0] === '?') return $origin . ($p['path'] ?? '/') . $link;
        $dir  = isset($p['path']) ? preg_replace('~/[^/]*$~', '/', $p['path']) : '/';
        $path = $dir . $link;
        $segments = [];
        foreach (explode('/', $path) as $seg) {
            if ($seg === '' || $seg === '.') continue;
            if ($seg === '..') { array_pop($segments); continue; }
            $segments[] = $seg;
        }
        return $origin . '/' . implode('/', $segments);
    }

    /* ----- JSON-LD ----- */

    private function fromJsonLd(DOMXPath $xpath, string $base): array
    {
        $out = $this->emptyProduct();
        $nodes = $xpath->query('//script[@type="application/ld+json"]');
        if (!$nodes) return $out;
        foreach ($nodes as $node) {
            $raw = trim($node->textContent);
            if ($raw === '') continue;
            $json = json_decode($raw, true);
            if ($json === null) $json = json_decode($this->repairJson($raw), true);
            if (!is_array($json)) continue;
            foreach ($this->flattenJsonLd($json) as $item) {
                if (!$this->isProductType($item)) continue;
                $this->absorbJsonLdProduct($out, $item, $base);
            }
        }
        return $out;
    }

    private function flattenJsonLd($json): array
    {
        $items = [];
        $walk = function ($n) use (&$items, &$walk) {
            if (!is_array($n)) return;
            if ($n !== [] && array_keys($n) === range(0, count($n) - 1)) {
                foreach ($n as $child) $walk($child);
                return;
            }
            $items[] = $n;
            if (isset($n['@graph']) && is_array($n['@graph'])) {
                foreach ($n['@graph'] as $child) $walk($child);
            }
        };
        $walk($json);
        return $items;
    }

    private function isProductType(array $item): bool
    {
        if (!isset($item['@type'])) return false;
        foreach ((array) $item['@type'] as $t) {
            if (preg_match('~(^|/)(Product|ProductGroup|IndividualProduct|Vehicle|Book|SoftwareApplication)$~i', (string) $t)) {
                return true;
            }
        }
        return false;
    }

    private function absorbJsonLdProduct(array &$out, array $p, string $base): void
    {
        if (empty($out['name']) && !empty($p['name']))               $out['name'] = $this->clean($p['name']);
        if (empty($out['description']) && !empty($p['description']))  $out['description'] = $this->clean($p['description']);
        if (empty($out['brand']) && isset($p['brand'])) {
            $b = $p['brand'];
            $out['brand'] = $this->clean(is_array($b) ? ($b['name'] ?? '') : $b);
        }
        foreach (['sku', 'mpn', 'gtin', 'color', 'material', 'size'] as $k) {
            if (!empty($p[$k]) && is_scalar($p[$k])) {
                $out['specs'][] = ['key' => ucfirst($k), 'value' => $this->clean($p[$k])];
            }
        }
        if (!empty($p['additionalProperty']) && is_array($p['additionalProperty'])) {
            foreach ($p['additionalProperty'] as $prop) {
                if (isset($prop['name'], $prop['value'])) {
                    $out['specs'][] = ['key' => $this->clean($prop['name']), 'value' => $this->clean($prop['value'])];
                }
            }
        }
        foreach ($this->jsonLdImages($p) as $img) {
            $abs = $this->absolutize($img, $base);
            if ($abs) $out['images'][] = $abs;
        }
        $this->jsonLdOffers($out, $p, $base);
    }

    private function jsonLdImages(array $p): array
    {
        if (empty($p['image'])) return [];
        $imgs = [];
        $img  = $p['image'];
        $push = function ($v) use (&$imgs) {
            if (is_string($v)) $imgs[] = $v;
            elseif (is_array($v) && isset($v['url'])) $imgs[] = $v['url'];
        };
        if (is_array($img) && $img !== [] && array_keys($img) === range(0, count($img) - 1)) {
            foreach ($img as $v) $push($v);
        } else {
            $push($img);
        }
        return $imgs;
    }

    private function jsonLdOffers(array &$out, array $p, string $base): void
    {
        if (empty($p['offers'])) return;
        $offers = $p['offers'];
        $list = (isset($offers['@type']) || isset($offers['price']) || isset($offers['priceCurrency']) || isset($offers['lowPrice']))
            ? [$offers] : (array) $offers;

        $variantNames = [];
        foreach ($list as $off) {
            if (!is_array($off)) continue;
            $price = $off['price'] ?? $off['lowPrice'] ?? ($off['priceSpecification']['price'] ?? null);
            $cur   = $off['priceCurrency'] ?? ($off['priceSpecification']['priceCurrency'] ?? null);
            if ($price !== null && $price !== '' && empty($out['price'])) $out['price'] = $this->normalizePrice($price);
            if (!empty($cur) && empty($out['currency'])) $out['currency'] = strtoupper($this->clean($cur));
            $nm = $off['name'] ?? ($off['itemOffered']['name'] ?? '');
            if ($nm) $variantNames[] = $this->clean($nm);
        }
        $variantNames = array_values(array_unique(array_filter($variantNames)));
        if (count($variantNames) > 1) {
            $out['variations'][] = ['name' => 'Variant', 'values' => array_slice($variantNames, 0, 40)];
        }
    }

    private function repairJson(string $s): string
    {
        $s = preg_replace('/,\s*([}\]])/', '$1', $s);
        $s = preg_replace('~/\*.*?\*/~s', '', $s);
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s);
        return $s;
    }

    private function normalizePrice($v): ?string
    {
        if (is_numeric($v)) return (string) (0 + $v);
        $s = preg_replace('~[^\d,.\-]~', '', (string) $v);
        if ($s === '') return null;
        if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
            $s = (strrpos($s, ',') > strrpos($s, '.')) ? str_replace('.', '', $s) : str_replace(',', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (substr_count($s, ',') === 1 && preg_match('~,\d{2}$~', $s)) {
            $s = str_replace(',', '.', $s);
        } else {
            $s = str_replace(',', '', $s);
        }
        return $s;
    }

    /* ----- OpenGraph ----- */

    private function fromOpenGraph(DOMXPath $xpath, string $base): array
    {
        $out = $this->emptyProduct();
        $get = fn(string $prop) => $this->metaContent($xpath, 'property', $prop);
        $out['name']        = $this->clean($get('og:title'));
        $out['description'] = $this->clean($get('og:description'));
        foreach ($xpath->query('//meta[@property="og:image" or @property="og:image:url" or @property="og:image:secure_url"]') as $m) {
            $abs = $this->absolutize($m->getAttribute('content'), $base);
            if ($abs) $out['images'][] = $abs;
        }
        $price = $get('product:price:amount') ?: $this->metaContent($xpath, 'property', 'og:price:amount');
        $cur   = $get('product:price:currency') ?: $this->metaContent($xpath, 'property', 'og:price:currency');
        if ($price) $out['price']    = $this->normalizePrice($price);
        if ($cur)   $out['currency'] = strtoupper($this->clean($cur));
        $brand = $get('product:brand') ?: $get('og:brand');
        if ($brand) $out['brand'] = $this->clean($brand);
        return $out;
    }

    private function metaContent(DOMXPath $xpath, string $attr, string $val): ?string
    {
        $n = $xpath->query("//meta[@$attr=" . $this->xpLiteral($val) . "]/@content")->item(0);
        return $n ? trim($n->nodeValue) : null;
    }

    private function xpLiteral(string $s): string
    {
        if (strpos($s, "'") === false) return "'$s'";
        if (strpos($s, '"') === false) return "\"$s\"";
        return "concat('" . str_replace("'", "',\"'\",'", $s) . "')";
    }

    /* ----- Twitter cards ----- */

    private function fromTwitter(DOMXPath $xpath, string $base): array
    {
        $out = $this->emptyProduct();
        $get = fn(string $name) => $this->metaContent($xpath, 'name', $name);
        $out['name']        = $this->clean($get('twitter:title'));
        $out['description'] = $this->clean($get('twitter:description'));
        $img = $get('twitter:image') ?: $get('twitter:image:src');
        if ($img) { $abs = $this->absolutize($img, $base); if ($abs) $out['images'][] = $abs; }
        for ($i = 1; $i <= 2; $i++) {
            $label = $get("twitter:label$i");
            $data  = $get("twitter:data$i");
            if ($label && $data && stripos($label, 'price') !== false) $out['price'] = $this->normalizePrice($data);
        }
        return $out;
    }

    /* ----- Microdata ----- */

    private function fromMicrodata(DOMXPath $xpath, string $base): array
    {
        $out = $this->emptyProduct();
        $scope = $xpath->query('//*[contains(translate(@itemtype,"PRODUCT","product"),"product")]')->item(0);
        $q = function (string $prop) use ($xpath, $scope) {
            $expr = './/*[@itemprop=' . $this->xpLiteral($prop) . ']';
            return $scope ? $xpath->query($expr, $scope) : $xpath->query('//*[@itemprop=' . $this->xpLiteral($prop) . ']');
        };
        $first = fn($nodes) => ($nodes && $nodes->length) ? $nodes->item(0) : null;

        if ($n = $first($q('name')))          $out['name']        = $this->clean($this->itempropValue($n));
        if ($n = $first($q('description')))   $out['description'] = $this->clean($this->itempropValue($n));
        if ($n = $first($q('brand')))         $out['brand']       = $this->clean($this->itempropValue($n));
        if ($n = $first($q('price')))         $out['price']       = $this->normalizePrice($this->itempropValue($n));
        if ($n = $first($q('priceCurrency'))) $out['currency']    = strtoupper((string) $this->clean($this->itempropValue($n)));
        foreach ($q('image') as $n) {
            $abs = $this->absolutize($this->itempropValue($n), $base);
            if ($abs) $out['images'][] = $abs;
        }
        return $out;
    }

    private function itempropValue(DOMElement $n): ?string
    {
        foreach (['content', 'src', 'href', 'datetime', 'data-value'] as $a) {
            if ($n->hasAttribute($a) && trim($n->getAttribute($a)) !== '') {
                if (in_array($a, ['src', 'href'], true) && !in_array($n->tagName, ['img', 'a', 'link', 'source', 'meta'], true)) continue;
                return trim($n->getAttribute($a));
            }
        }
        return trim($n->textContent);
    }

    /* ----- Visible-text heuristics ----- */

    private function fromVisible(DOMXPath $xpath, string $base): array
    {
        $out = $this->emptyProduct();
        if ($h1 = $xpath->query('//h1')->item(0)) $out['name'] = $this->clean($h1->textContent);

        $priceNodes = $xpath->query(
            '//*[contains(translate(@class,"PRICE","price"),"price") or ' .
            'contains(translate(@id,"PRICE","price"),"price") or @data-price]'
        );
        foreach ($priceNodes as $pn) {
            $txt = $pn->getAttribute('data-price') ?: $pn->textContent;
            if ($cur = $this->matchCurrency($txt)) {
                $out['currency'] = $out['currency'] ?: $cur['currency'];
                $out['price']    = $out['price']    ?: $cur['price'];
                break;
            }
        }
        foreach ($xpath->query('//img[@src or @data-src or @data-original or @srcset]') as $img) {
            $src = $img->getAttribute('data-src')
                ?: $img->getAttribute('data-original')
                ?: $img->getAttribute('src')
                ?: $this->firstFromSrcset($img->getAttribute('srcset'));
            $abs = $this->absolutize($src, $base);
            if ($abs && $this->looksLikeProductImage($abs)) $out['images'][] = $abs;
        }
        $out['specs'] = array_merge($out['specs'], $this->specsFromTables($xpath));
        return $out;
    }

    private function firstFromSrcset(string $srcset): ?string
    {
        if ($srcset === '') return null;
        $first = trim(explode(',', $srcset)[0]);
        return trim(explode(' ', $first)[0]) ?: null;
    }

    private function looksLikeProductImage(string $url): bool
    {
        if (!preg_match('~\.(jpe?g|png|webp|avif|gif)(\?|$)~i', $url)
            && !preg_match('~(image|img|photo|media|cdn)~i', $url)) {
            return false;
        }
        return !preg_match('~(sprite|icon|logo|placeholder|1x1|pixel|blank|spinner|loading)~i', $url);
    }

    private function matchCurrency(string $txt): ?array
    {
        $txt = (string) $this->clean($txt);
        if (preg_match('~(?:(USD|EUR|GBP|PKR|INR|CAD|AUD|JPY|CNY|AED|SAR)\s*)?([€£$₹₨¥]?)\s*([\d.,]+\d)~u', $txt, $m)) {
            $iso   = $m[1] ?: $this->symbolToIso($m[2]);
            $price = $this->normalizePrice($m[3]);
            if ($price !== null) return ['currency' => $iso, 'price' => $price];
        }
        return null;
    }

    private function symbolToIso(string $sym): ?string
    {
        return ['$' => 'USD', '€' => 'EUR', '£' => 'GBP', '₹' => 'INR', '₨' => 'PKR', '¥' => 'JPY'][$sym] ?? null;
    }

    private function specsFromTables(DOMXPath $xpath): array
    {
        $specs = [];
        foreach ($xpath->query('//table//tr') as $tr) {
            $cells = [];
            foreach ($tr->childNodes as $c) {
                if ($c->nodeType === XML_ELEMENT_NODE && in_array(strtolower($c->nodeName), ['td', 'th'], true)) {
                    $cells[] = $this->clean($c->textContent);
                }
            }
            if (count($cells) === 2 && $cells[0] !== '' && $cells[1] !== '' && $cells[0] !== null && $cells[1] !== null) {
                $specs[] = ['key' => $cells[0], 'value' => $cells[1]];
            }
        }
        foreach ($xpath->query('//dl') as $dl) {
            $dt = null;
            foreach ($dl->childNodes as $c) {
                if ($c->nodeType !== XML_ELEMENT_NODE) continue;
                $tag = strtolower($c->nodeName);
                if ($tag === 'dt') $dt = $this->clean($c->textContent);
                elseif ($tag === 'dd' && $dt !== null) { $specs[] = ['key' => $dt, 'value' => $this->clean($c->textContent)]; $dt = null; }
            }
        }
        return array_slice($specs, 0, 80);
    }

    /* ----- Variations ----- */

    private function collectVariations(DOMXPath $xpath, array $out, string $base): array
    {
        $vars = $out['variations'];

        foreach ($xpath->query('//select') as $sel) {
            $label  = $this->labelForControl($xpath, $sel);
            $values = [];
            foreach ($xpath->query('.//option', $sel) as $opt) {
                $v   = $this->clean($opt->textContent);
                $val = $opt->getAttribute('value');
                if ($v === null || $v === '' || $val === '' || preg_match('~^(choose|select|please|--)~i', $v)) continue;
                $values[] = $v;
            }
            if (count($values) >= 2) $vars[] = ['name' => $label ?: 'Option', 'values' => array_values(array_unique($values))];
        }

        $swatchXp = '//*[contains(translate(@class,"SWATCH","swatch"),"swatch") '
                  . 'or contains(@class,"variation") or contains(@class,"variant") '
                  . 'or contains(@class,"option") or contains(@class,"attribute")]';
        foreach ($xpath->query($swatchXp) as $group) {
            $label  = $this->clean($this->labelForControl($xpath, $group)) ?: 'Option';
            $values = [];
            foreach ($xpath->query('.//*[@data-value or @title or @aria-label]', $group) as $sw) {
                $v = $this->clean($sw->getAttribute('data-value') ?: $sw->getAttribute('title') ?: $sw->getAttribute('aria-label'));
                if ($v !== null && $v !== '' && mb_strlen($v) <= 60) $values[] = $v;
            }
            if (!$values) {
                foreach ($xpath->query('.//li|.//button', $group) as $li) {
                    $v = $this->clean($li->textContent);
                    if ($v !== null && $v !== '' && mb_strlen($v) <= 60) $values[] = $v;
                }
            }
            $values = array_values(array_unique(array_filter($values)));
            if (count($values) >= 2) $vars[] = ['name' => $label, 'values' => $values];
        }
        return $this->dedupeVariations($vars);
    }

    private function labelForControl(DOMXPath $xpath, DOMElement $el): ?string
    {
        if ($el->hasAttribute('aria-label')) return $this->clean($el->getAttribute('aria-label'));
        $name = $el->hasAttribute('name') ? $el->getAttribute('name') : '';
        if ($el->hasAttribute('id')) {
            $lab = $xpath->query('//label[@for=' . $this->xpLiteral($el->getAttribute('id')) . ']')->item(0);
            if ($lab) return $this->clean($lab->textContent);
        }
        $prev = $xpath->query('preceding-sibling::*[self::label or self::span or self::div or self::h2 or self::h3][1]', $el)->item(0);
        if ($prev) {
            $t = $this->clean($prev->textContent);
            if ($t !== null && $t !== '' && mb_strlen($t) <= 40) return $t;
        }
        return $name !== '' ? ucfirst($name) : null;
    }

    private function dedupeVariations(array $vars): array
    {
        $seen = [];
        $result = [];
        foreach ($vars as $v) {
            if (empty($v['values'])) continue;
            $key = mb_strtolower($v['name']) . '|' . mb_strtolower(implode(',', $v['values']));
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $result[] = $v;
        }
        return array_slice($result, 0, 6);
    }

    /* ----- Cleaning / finalisation ----- */

    private function clean($s): ?string
    {
        if ($s === null) return null;
        $s = html_entity_decode((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = str_replace(["\xC2\xA0", "\t"], ' ', $s);
        $s = preg_replace('/\s+/u', ' ', $s);
        return trim($s) ?: null;
    }

    private function dedupeImages(array $imgs): array
    {
        $seen = [];
        $out  = [];
        foreach ($imgs as $u) {
            if (!$u) continue;
            $key = preg_replace('~^https?://~', '', rtrim($u, '/'));
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = $u;
        }
        return array_slice($out, 0, 12);
    }

    private function finalizeText(array $out): array
    {
        if ($out['description']) $out['description'] = mb_substr($out['description'], 0, 5000);
        $seen = [];
        $specs = [];
        foreach ($out['specs'] as $s) {
            $k = mb_strtolower((string) $s['key']);
            if ($k === '' || isset($seen[$k])) continue;
            $seen[$k] = true;
            $specs[] = $s;
        }
        $out['specs'] = array_slice($specs, 0, 60);
        return $out;
    }
}
