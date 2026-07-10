<?php
/**
 * Etsy Listing Studio — configuration
 * ------------------------------------------------------------------
 * SETUP (one time):
 *   1. Copy this file and rename the copy to  config.php  (same folder).
 *   2. Choose a provider below and paste that provider's API key.
 *   3. Open  http://localhost/etsyai/
 *
 * Keep config.php private (it holds your key).
 */

return [

    /* Which AI to use:
       'gemini'     = Google Gemini — FREE tier (no card needed)
       'openrouter' = OpenRouter — many models incl. free, reliable routing
       'anthropic'  = Claude — paid, top quality */
    'provider' => 'gemini',

    /* ---------- Google Gemini (FREE) ---------- */
    // Get a free key: https://aistudio.google.com  ->  "Get API key"  ->  Create API key
    'gemini_api_key' => 'PASTE-YOUR-FREE-GEMINI-KEY-HERE',
    'gemini_model'   => 'gemini-2.5-flash',   // free & fast; or 'gemini-2.5-flash-lite'

    /* ---------- Anthropic Claude (paid) ---------- */
    // Get a key: https://console.anthropic.com  ->  API Keys
    'anthropic_api_key' => 'sk-ant-REPLACE-WITH-YOUR-KEY',
    'anthropic_model'   => 'claude-opus-4-8',  // or 'claude-sonnet-5' / 'claude-haiku-4-5'

    /* ---------- OpenRouter (many models incl. free — reliable routing) ---------- */
    // Get a key at https://openrouter.ai -> Keys.  Then set  'provider' => 'openrouter'  above.
    'openrouter_api_key' => 'sk-or-PASTE-YOUR-OPENROUTER-KEY',
    'openrouter_model'   => 'google/gemini-2.0-flash-exp:free',  // free; or paid 'google/gemini-2.5-flash' for max reliability

    /* Default SEO market when the UI does not send one: 'uk', 'us', or 'both' */
    'default_market' => 'both',

    /* ---------- Optional: "Continue with Google" sign-in ---------- */
    // Free OAuth Client ID from https://console.cloud.google.com (APIs & Services → Credentials →
    // OAuth client ID → Web application). Add  http://localhost:8123  as an Authorised JavaScript
    // origin, then paste the Client ID here. Leave empty to use email sign-up only.
    'google_client_id' => '',

    /* ---------- Freemium (MVP) ---------- */
    // How many listings can be generated free before the paywall appears.
    'free_limit' => 5,
    // Subscriber access codes that unlock unlimited use. Give one to each paid user.
    // e.g. ['PRO-7Q2M-9XК', 'PRO-4B8T-1LZ']   (later this is filled automatically by payments)
    'access_codes' => [],
    // Set to 'pro' to unlock this whole install (e.g. your own copy):
    'plan' => 'free',

    /* ---------- Optional: auto-fetch blocked sites (AliExpress / Amazon / Temu) ---------- */
    // These sites block server-side fetching. Get a FREE key at https://www.scraperapi.com
    // and paste it here to fetch them automatically. Leave empty to use "Paste page content".
    'scraper_api_key' => '',
];
