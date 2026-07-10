# Etsy Listing Studio

Paste any product link → get a **ready-to-paste, Etsy-compliant listing**: unique SEO title,
13 tags, description, materials, category, attributes, GBP + USD pricing, a policy-compliance
check, and every product variation — all copy-ready and CSV-exportable, targeted at the UK & US
markets. Powered by Claude (Anthropic).

Built to run **directly under XAMPP** with plain PHP — no Composer, no build step.

---

## Setup (2 minutes)

1. Make a copy of **`config.sample.php`** and name the copy **`config.php`**.
2. Choose a provider and paste that provider's key into `config.php`:

   **Option A — Google Gemini (FREE, recommended to start):**
   - Go to <https://aistudio.google.com> → **Get API key** → **Create API key** (no card needed).
   - In `config.php`, keep `'provider' => 'gemini'` and paste the key into `gemini_api_key`.

   **Option B — Anthropic Claude (paid, top quality):**
   - Get a key at <https://console.anthropic.com> → **API Keys** (starts with `sk-ant-`) and add a little billing credit.
   - In `config.php`, set `'provider' => 'anthropic'` and paste the key into `anthropic_api_key`.

3. **Run the app — easiest way (no Apache needed):** double-click **`start-etsyai.bat`** in this
   folder. It picks a free port automatically, opens your browser, and shows the exact address
   in its window (usually <http://localhost:8123/>). Keep that window open while you use the
   app; close it to stop.

   *Why not Apache?* XAMPP's Apache sometimes runs without the PHP **cURL** extension loaded
   (you'd see a `curl_init()` error). The launcher uses PHP's built-in server, where cURL works.
   If you prefer Apache anyway, fully **Stop → Start** Apache in XAMPP so it reloads cURL, then
   open <http://localhost/etsyai/>.

4. Paste a product URL and click **Generate listing**.

> No key yet? Open the app and click **“See a sample listing”** to preview the full result for free.

### If HTTPS product pages fail to load
Fresh XAMPP often ships without an SSL certificate bundle. The app already works around this,
but for the cleanest fix download <https://curl.se/ca/cacert.pem> and save it as
`c:\xampp\htdocs\etsyai\api\lib\cacert.pem` — the app will pick it up automatically.

---

## How to use it

- **Product URL** – paste any product page: a supplier, a marketplace, or your own shop.
- **Market** – UK, US, or Both. Controls spelling (colour vs color), currency, and tag phrasing.
- **Category hint** *(optional)* – nudge it toward the right Etsy category if you already know it.
- **Paste page content instead** – if a site blocks automated fetching (Amazon, AliExpress,
  Temu, Shein often do) or you're offline, open the page in your browser, select all (Ctrl+A),
  copy, and paste it here. The app runs the same extraction on the pasted content.

### The result
Every field is shown in Etsy's own form order with a one-click **Copy** button, live character
counters (title `/140`, tags `/20`), an **Etsy policy check** (green / amber / red), and a
variations table you can **Download as CSV** or copy as TSV (pastes straight into a spreadsheet
or bulk editor). Use **Copy everything** or **Download JSON** for the whole listing.

> Etsy does not offer a native CSV import for *creating* listings with variations, so the
> workflow is: copy each field into Etsy's "Add a listing" form (top to bottom), and use the
> variations CSV/table as your reference while setting up variation options and prices.

---

## Configuration (`config.php`)

| Key | Meaning |
|---|---|
| `provider` | `gemini` (free) or `anthropic` (paid). |
| `gemini_api_key` | Your free Gemini key (from aistudio.google.com). |
| `gemini_model` | `gemini-2.0-flash` (free & fast) or `gemini-2.5-flash`. |
| `anthropic_api_key` | Your Claude key (`sk-ant-…`), if using Anthropic. |
| `anthropic_model` | `claude-opus-4-8` / `claude-sonnet-5` / `claude-haiku-4-5`. |
| `default_market` | `uk`, `us`, or `both`. |

**Cost:** Gemini's free tier covers everyday use at no charge. Claude is paid but cheap
(~$0.05/listing on Opus, ~$0.01 on Haiku).

---

## Files

```
etsyai/
├─ index.php              Dashboard UI (single file, light/dark, offline)
├─ config.sample.php      Copy → config.php and add your key
├─ api/
│  ├─ generate.php        POST endpoint: scrape → Claude → validated listing JSON
│  └─ lib/
│     ├─ Scraper.php      Extracts product data (JSON-LD, OpenGraph, microdata, variations)
│     └─ Anthropic.php    Claude Messages API client (cURL, structured outputs)
└─ README.md
```

## Notes
- Your API key lives only in `config.php` on your machine. Product pages are fetched
  server-side from your PC; nothing is stored.
- The generated compliance check is guidance, not legal advice — you're responsible for what
  you publish on Etsy.
