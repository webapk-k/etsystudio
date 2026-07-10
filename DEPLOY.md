# Deploy Etsy Listing Studio to a FREE domain (Render)

This app makes outbound API calls (Gemini, Jina) and writes files, so classic "free web
hosting" (InfinityFree, 000webhost) will **not** work — they block outbound API requests.
**Render's free tier works** and gives you a free HTTPS URL: `https://your-app.onrender.com`.

Everything is already prepared: `Dockerfile`, `config.deploy.php` (reads secrets from env),
`.dockerignore`, `render.yaml`. You only need two free accounts: **GitHub** + **Render**.

---

## 1. Put the code on GitHub (one time)
1. Create a free account at https://github.com and click **New repository** (name it `etsyai`, keep it **Private**).
2. On your PC, in `c:\xampp\htdocs\etsyai`, run (Git must be installed):
   ```bash
   git init
   git add .
   git commit -m "Etsy Listing Studio"
   git branch -M main
   git remote add origin https://github.com/<your-username>/etsyai.git
   git push -u origin main
   ```
   > `config.php` and `data/` are git-ignored, so **your API keys are NOT uploaded** — good.

## 2. Deploy on Render (free)
1. Create a free account at https://render.com (sign in with GitHub).
2. Click **New +** → **Web Service** → connect your `etsyai` repo.
3. Render auto-detects the **Dockerfile**. Set **Instance Type = Free**.
4. Open **Environment** and add these variables:
   | Key | Value |
   |-----|-------|
   | `PROVIDER` | `gemini` |
   | `GEMINI_API_KEY` | *your free Gemini key from aistudio.google.com* |
   | `ADMIN_EMAIL` | `admin@etsystudio.local` (or your email) |
   | `ADMIN_PASSWORD` | *your own secret* |
   | `GOOGLE_CLIENT_ID` | *(optional, for Google login)* |
5. Click **Create Web Service**. Wait ~2–3 min → you get a live URL: `https://etsy-listing-studio.onrender.com` 🎉

## 3. (Optional) Turn on "Continue with Google"
In Google Cloud Console → Credentials → your OAuth client → **Authorised JavaScript origins**,
add your Render URL (e.g. `https://etsy-listing-studio.onrender.com`). Set `GOOGLE_CLIENT_ID` env var.

---

## Good to know (free tier limits)
- **Cold start:** the free instance sleeps after ~15 min idle; the next visit takes ~30–60s to wake. Normal for free.
- **Data resets on redeploy:** `data/` (users, history, usage) is not persistent on the free plan. For permanent accounts, add a database later (Phase 2).
- **Shared API quota:** anyone who signs up uses *your* Gemini free quota. Keep the repo private and share the link only with people you trust, or raise `FREE_LIMIT` / add access codes.
- **Single-threaded:** the built-in PHP server handles one request at a time — fine for demos/low traffic.

## Alternatives (same Dockerfile works)
- **Railway** (railway.app): New Project → Deploy from GitHub → add the same env vars. Free trial credits.
- **Fly.io** (fly.io): `fly launch` (uses the Dockerfile) → `fly secrets set GEMINI_API_KEY=...`.
