<?php
/**
 * config.deploy.php — configuration for hosting (Render / Railway / Fly / any Docker host).
 * Secrets are read from ENVIRONMENT VARIABLES so no keys ever live in the git repo.
 * Set these in your host's dashboard (Environment / Variables):
 *   GEMINI_API_KEY, ADMIN_EMAIL, ADMIN_PASSWORD, (optional) GOOGLE_CLIENT_ID, PROVIDER, FREE_LIMIT ...
 * The Docker build copies this file to config.php.
 */

return [
    'provider'           => getenv('PROVIDER') ?: 'gemini',

    'gemini_api_key'     => getenv('GEMINI_API_KEY') ?: '',
    'gemini_model'       => getenv('GEMINI_MODEL') ?: 'gemini-2.5-flash',

    'anthropic_api_key'  => getenv('ANTHROPIC_API_KEY') ?: 'sk-ant-REPLACE',
    'anthropic_model'    => getenv('ANTHROPIC_MODEL') ?: 'claude-opus-4-8',

    'openrouter_api_key' => getenv('OPENROUTER_API_KEY') ?: '',
    'openrouter_model'   => getenv('OPENROUTER_MODEL') ?: 'meta-llama/llama-3.3-70b-instruct:free',

    'default_market'     => getenv('DEFAULT_MARKET') ?: 'both',

    'google_client_id'   => getenv('GOOGLE_CLIENT_ID') ?: '',

    'free_limit'         => (int) (getenv('FREE_LIMIT') ?: 5),
    'access_codes'       => array_values(array_filter(array_map('trim', explode(',', (string) getenv('ACCESS_CODES'))))),
    'plan'               => 'free',

    'admin_email'        => getenv('ADMIN_EMAIL') ?: 'admin@etsystudio.local',
    'admin_password'     => getenv('ADMIN_PASSWORD') ?: 'admin12345',

    'scraper_api_key'    => getenv('SCRAPER_API_KEY') ?: '',

    // Optional free key from jina.ai — makes AliExpress/Amazon link reading more reliable (higher rate limit)
    'jina_api_key'       => getenv('JINA_API_KEY') ?: '',

    // Etsy Open API v3 key for the Policy Violation shop scan — "keystring:shared_secret" (developer.etsy.com)
    'etsy_api_key'       => getenv('ETSY_API_KEY') ?: '',
];
