<?php
$cfg      = file_exists(__DIR__ . '/config.php') ? (require __DIR__ . '/config.php') : null;
$noConfig = !is_array($cfg);
$provider = is_array($cfg) ? strtolower($cfg['provider'] ?? 'anthropic') : 'gemini';
if ($provider === 'gemini') {
    $key = is_array($cfg) ? trim((string)($cfg['gemini_api_key'] ?? '')) : '';
    $needsKey = ($key === '' || stripos($key, 'PASTE') !== false);
} elseif ($provider === 'openrouter') {
    $key = is_array($cfg) ? trim((string)($cfg['openrouter_api_key'] ?? '')) : '';
    $needsKey = ($key === '' || stripos($key, 'PASTE') !== false);
} else {
    $key = is_array($cfg) ? trim((string)($cfg['anthropic_api_key'] ?? '')) : '';
    $needsKey = ($key === '' || stripos($key, 'REPLACE') !== false || !str_starts_with($key, 'sk-ant-'));
}
$googleClientId = is_array($cfg) ? trim((string)($cfg['google_client_id'] ?? '')) : '';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Etsy Listing Studio</title>
<style>
:root{
  --bg:#FAF8F4; --bg-tint:#F3EEE6; --surface:#FFFFFF; --surface-2:#F7F4EE; --sunken:#EFEBE2;
  --border:#EBE6DC; --border-2:#DDD6C9;
  --text:#211E19; --muted:#6E675C; --faint:#A79E90;
  --accent:#C15A34; --accent-2:#E1854F; --accent-ink:#fff; --accent-weak:#F7E7DC; --accent-line:#EAD3C4;
  --ok:#2F8F57; --ok-bg:#E8F2E9; --ok-ink:#1E6B3E;
  --warn:#C58A17; --warn-bg:#FAEFD7; --warn-ink:#8A5E0C;
  --err:#C6432C; --err-bg:#F8E4DF; --err-ink:#973020;
  --ring:rgba(193,90,52,.28);
  --sh-sm:0 1px 2px rgba(40,32,24,.05),0 1px 3px rgba(40,32,24,.05);
  --sh-md:0 6px 24px -6px rgba(40,32,24,.14),0 2px 8px rgba(40,32,24,.06);
  --sh-lg:0 24px 60px -20px rgba(40,32,24,.28);
  --r:16px; --r-lg:20px; --r-sm:10px; --pill:999px;
  --fs:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
  --serif:"Iowan Old Style","Palatino Linotype",Palatino,Georgia,"Times New Roman",serif;
  --mono:ui-monospace,"SF Mono",SFMono-Regular,Menlo,Consolas,monospace;
}
:root[data-theme="dark"]{
  --bg:#15130F; --bg-tint:#1B1813; --surface:#1E1B15; --surface-2:#252118; --sunken:#2A251C;
  --border:#332E24; --border-2:#413A2E;
  --text:#F3EFE6; --muted:#ABA394; --faint:#7C7263;
  --accent:#E1854F; --accent-2:#EFA070; --accent-ink:#1A0E07; --accent-weak:#3A271B; --accent-line:#4A3524;
  --ok:#49B072; --ok-bg:#17281D; --ok-ink:#8CD9A6;
  --warn:#DBAA46; --warn-bg:#2B2411; --warn-ink:#E6C879;
  --err:#DD5B44; --err-bg:#2C1712; --err-ink:#F0A091;
  --ring:rgba(225,133,79,.34);
  --sh-sm:0 1px 2px rgba(0,0,0,.4); --sh-md:0 8px 30px -8px rgba(0,0,0,.6);
  --sh-lg:0 30px 70px -24px rgba(0,0,0,.75);
}
@media (prefers-color-scheme:dark){:root:not([data-theme]){
  --bg:#15130F; --bg-tint:#1B1813; --surface:#1E1B15; --surface-2:#252118; --sunken:#2A251C;
  --border:#332E24; --border-2:#413A2E; --text:#F3EFE6; --muted:#ABA394; --faint:#7C7263;
  --accent:#E1854F; --accent-2:#EFA070; --accent-ink:#1A0E07; --accent-weak:#3A271B; --accent-line:#4A3524;
  --ok:#49B072; --ok-bg:#17281D; --ok-ink:#8CD9A6; --warn:#DBAA46; --warn-bg:#2B2411; --warn-ink:#E6C879;
  --err:#DD5B44; --err-bg:#2C1712; --err-ink:#F0A091; --ring:rgba(225,133,79,.34);
  --sh-sm:0 1px 2px rgba(0,0,0,.4); --sh-md:0 8px 30px -8px rgba(0,0,0,.6); --sh-lg:0 30px 70px -24px rgba(0,0,0,.75);
}}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0;background:var(--bg);color:var(--text);font-family:var(--fs);font-size:15px;line-height:1.6;
  -webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility;
  background-image:radial-gradient(1200px 500px at 50% -10%,var(--bg-tint),transparent 70%);background-attachment:fixed}
.tnum{font-variant-numeric:tabular-nums}
/* topbar */
.topbar{position:sticky;top:0;z-index:30;display:flex;align-items:center;justify-content:space-between;
  padding:14px 24px;background:color-mix(in srgb,var(--bg) 78%,transparent);backdrop-filter:saturate(1.4) blur(10px);
  border-bottom:1px solid var(--border)}
.brand{display:flex;align-items:center;gap:12px}
.logo{width:34px;height:34px;border-radius:11px;background:linear-gradient(140deg,var(--accent),var(--accent-2));
  display:grid;place-items:center;color:var(--accent-ink);box-shadow:0 4px 12px -3px var(--ring);flex:0 0 auto}
.brand b{font-size:16px;font-weight:700;letter-spacing:-.02em;display:block;line-height:1.15}
.brand span{font-size:11.5px;color:var(--muted);letter-spacing:.02em}
.tbar-r{display:flex;align-items:center;gap:12px}
.badge{font-size:11.5px;color:var(--muted);border:1px solid var(--border);border-radius:var(--pill);padding:5px 11px;
  display:inline-flex;align-items:center;gap:6px;background:var(--surface)}
.dot-ok{width:7px;height:7px;border-radius:50%;background:var(--ok);box-shadow:0 0 0 3px color-mix(in srgb,var(--ok) 22%,transparent)}
.wrap{max-width:none;margin:0;padding:28px 40px 80px}
/* cards */
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh-sm)}
.card.pad{padding:22px 24px}
.stack>*+*{margin-top:16px}
.chead{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px}
.lbl{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:var(--muted)}
.lbl .n{color:var(--faint);font-weight:600;margin-left:6px}
.muted{color:var(--muted)} .faint{color:var(--faint)}
/* input hero */
.hero-input{position:relative;padding:26px;border-radius:var(--r-lg);border:1px solid var(--border);
  background:linear-gradient(180deg,var(--surface),var(--surface-2));box-shadow:var(--sh-md);overflow:hidden}
.hero-input::before{content:"";position:absolute;inset:0;pointer-events:none;
  background:radial-gradient(600px 200px at 90% -30%,var(--accent-weak),transparent 60%)}
.fieldlbl{display:block;font-size:12px;font-weight:600;color:var(--muted);margin:0 0 8px 2px}
.urlrow{display:flex;gap:12px}
.urlbox{flex:1;display:flex;align-items:center;gap:10px;background:var(--surface);border:1.5px solid var(--border-2);
  border-radius:14px;padding:0 14px;transition:.15s;position:relative;z-index:1}
.urlbox:focus-within{border-color:var(--accent);box-shadow:0 0 0 4px var(--ring)}
.urlbox svg{color:var(--faint);flex:0 0 auto}
.urlbox input{flex:1;border:0;background:none;outline:none;color:var(--text);font-family:var(--fs);font-size:15px;padding:13px 0}
input,textarea,button{font-family:inherit}
textarea{width:100%;background:var(--surface);border:1.5px solid var(--border-2);border-radius:14px;padding:13px 14px;
  color:var(--text);font-family:var(--mono);font-size:13px;resize:vertical;min-height:120px;max-height:340px;outline:none}
textarea:focus{border-color:var(--accent);box-shadow:0 0 0 4px var(--ring)}
.hint{font-size:12.5px;color:var(--faint);margin:9px 0 0 2px;position:relative;z-index:1}
.controls{display:flex;flex-wrap:wrap;gap:18px 26px;align-items:flex-end;margin-top:20px;position:relative;z-index:1}
.catbox input{width:100%;background:var(--surface);border:1.5px solid var(--border-2);border-radius:12px;padding:11px 13px;
  color:var(--text);font-size:14px;outline:none}
.catbox input:focus{border-color:var(--accent);box-shadow:0 0 0 4px var(--ring)}
.catbox{flex:1;min-width:200px}
.mksel{background:var(--surface);border:1.5px solid var(--border-2);border-radius:12px;padding:11px 13px;color:var(--text);font-size:14px;font-family:inherit;outline:none;cursor:pointer}
.mksel:focus{border-color:var(--accent);box-shadow:0 0 0 4px var(--ring)}
/* buttons */
.btn{border:1px solid transparent;border-radius:13px;padding:0 20px;height:48px;font-size:14.5px;font-weight:650;
  display:inline-flex;align-items:center;gap:9px;cursor:pointer;transition:.16s;white-space:nowrap;letter-spacing:.01em}
.btn:active{transform:translateY(1px)}
.btn:focus-visible{outline:none;box-shadow:0 0 0 4px var(--ring)}
.btn.primary{background:linear-gradient(140deg,var(--accent),var(--accent-2));color:var(--accent-ink);
  box-shadow:0 8px 20px -8px var(--ring)}
.btn.primary:hover{filter:brightness(1.05);box-shadow:0 10px 24px -8px var(--ring)}
.btn.sm{height:40px;padding:0 15px;font-size:13.5px;border-radius:11px}
.btn.ghost{background:var(--surface);color:var(--text);border-color:var(--border-2)}
.btn.ghost:hover{background:var(--surface-2);border-color:var(--accent-line)}
.btn[disabled]{opacity:.55;cursor:not-allowed;filter:none;box-shadow:none}
.icobtn{width:34px;height:34px;border-radius:10px;border:1px solid transparent;background:transparent;color:var(--faint);
  cursor:pointer;display:grid;place-items:center;transition:.14s;flex:0 0 auto}
.icobtn:hover{background:var(--surface-2);color:var(--text);border-color:var(--border)}
.icobtn.copied{color:var(--ok);background:var(--ok-bg);border-color:transparent}
svg{display:block}
/* segmented */
.seg{display:inline-flex;background:var(--sunken);border-radius:var(--pill);padding:4px;gap:2px}
.seg button{border:0;background:transparent;color:var(--muted);font-weight:650;font-size:13.5px;padding:8px 18px;
  border-radius:var(--pill);cursor:pointer;transition:.16s}
.seg button[aria-checked=true]{background:var(--surface);color:var(--accent);box-shadow:var(--sh-sm)}
.disc{margin-top:18px;position:relative;z-index:1}
.disc-btn{background:none;border:0;color:var(--muted);font-weight:650;font-size:13px;cursor:pointer;display:inline-flex;
  align-items:center;gap:7px;padding:4px 0}
.disc-btn .car{transition:transform .18s} .disc-btn[aria-expanded=true] .car{transform:rotate(90deg)}
.disc-panel[hidden]{display:none} .disc-panel{margin-top:12px}
/* chips */
.chips{display:flex;flex-wrap:wrap;gap:9px}
.chip{display:inline-flex;align-items:center;gap:7px;background:var(--surface-2);border:1px solid var(--border);
  border-radius:var(--pill);padding:7px 14px;font-size:14px;cursor:pointer;transition:.14s;line-height:1}
.chip:hover{border-color:var(--accent-line);background:var(--accent-weak);color:var(--accent)}
.chip:hover .cg{opacity:1}
.chip .cg{opacity:0;transition:.14s;color:var(--accent)}
.chip.static{cursor:default} .chip.static:hover{background:var(--surface-2);border-color:var(--border);color:var(--text)}
.chip.over{border-color:var(--err);color:var(--err-ink);background:var(--err-bg)}
.chip.over b{font-size:11px;font-weight:700;background:var(--err);color:#fff;border-radius:var(--pill);padding:1px 7px}
.chip.flash{border-color:var(--ok);background:var(--ok-bg);color:var(--ok-ink)}
/* result */
.rbar{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:16px 20px;position:sticky;top:66px;z-index:20;
  background:color-mix(in srgb,var(--surface) 92%,transparent);backdrop-filter:blur(8px)}
.rbar .t{font-weight:700;font-size:15px;letter-spacing:-.01em}
.rbar .t small{display:block;font-weight:500;font-size:12px;color:var(--muted);letter-spacing:.02em;margin-top:1px}
.acts{display:flex;gap:9px}
.thumbs{display:flex;gap:10px;overflow-x:auto;padding:2px 0 6px}
.thumbs img{width:60px;height:60px;border-radius:12px;object-fit:cover;border:1px solid var(--border);background:var(--sunken);flex:0 0 auto}
.titletext{font-size:19px;font-weight:600;line-height:1.45;letter-spacing:-.01em}
.counter{font-size:12px;font-weight:650;color:var(--muted);background:var(--surface-2);border:1px solid var(--border);
  border-radius:var(--pill);padding:4px 11px}
.counter.over{color:#fff;background:var(--err);border-color:transparent}
.desc{white-space:pre-wrap;max-width:70ch;color:var(--text);line-height:1.7}
.two{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.attrs{display:grid;grid-template-columns:1fr 1fr;gap:16px 26px}
.attr .k{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--faint);margin-bottom:3px}
.attr .v{font-weight:600;display:flex;align-items:center;gap:7px}
.bread{display:flex;flex-wrap:wrap;gap:7px;align-items:center;margin-bottom:18px}
.crumb{background:var(--surface-2);border:1px solid var(--border);border-radius:9px;padding:5px 11px;font-size:13px;font-weight:500}
.sep{color:var(--faint)}
.sw{width:13px;height:13px;border-radius:50%;border:1px solid var(--border-2);flex:0 0 auto}
.price-big{font-size:32px;font-weight:750;letter-spacing:-.02em;line-height:1}
.price-cur{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:6px}
.prices{display:flex;gap:34px;align-items:flex-end}
.rationale{font-size:13px;color:var(--muted);margin-top:14px;line-height:1.6}
.banner{border-radius:var(--r);padding:18px 20px;border:1px solid var(--border);display:flex;gap:14px;align-items:flex-start}
.banner .ic{width:30px;height:30px;border-radius:9px;display:grid;place-items:center;flex:0 0 auto;font-weight:800}
.banner .bh{font-weight:700;font-size:15px;margin-bottom:4px}
.banner ul{margin:8px 0 0;padding-left:18px;color:var(--text)} .banner li{margin:3px 0}
.banner.ok{background:var(--ok-bg);border-color:transparent} .banner.ok .ic{background:var(--ok);color:#fff} .banner.ok .bh{color:var(--ok-ink)}
.banner.caution{background:var(--warn-bg);border-color:transparent} .banner.caution .ic{background:var(--warn);color:#fff} .banner.caution .bh{color:var(--warn-ink)}
.banner.likely_violation{background:var(--err-bg);border-color:transparent} .banner.likely_violation .ic{background:var(--err);color:#fff} .banner.likely_violation .bh{color:var(--err-ink)}
.tablewrap{border:1px solid var(--border);border-radius:12px;overflow:hidden}
.tscroll{overflow-x:auto}
table{border-collapse:collapse;width:100%;font-size:14px;min-width:540px}
th{background:var(--surface-2);font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);font-weight:700;
  text-align:left;padding:11px 14px;border-bottom:1px solid var(--border)}
td{padding:12px 14px;border-bottom:1px solid var(--border)}
tbody tr:last-child td{border-bottom:0}
tbody tr:hover{background:var(--surface-2)}
td.tnum,th.tnum{text-align:right;font-variant-numeric:tabular-nums}
td.sku{font-family:var(--mono);font-size:12.5px;color:var(--muted)}
/* sidebar + AI suggestions */
.rgrid{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:20px;align-items:start;margin-top:16px}
.raside{display:flex;flex-direction:column;gap:16px;position:sticky;top:84px}
.sug{border-color:var(--accent-line);background:linear-gradient(180deg,var(--surface),var(--accent-weak))}
.sug .lbl{color:var(--accent)}
.sugsec+.sugsec{margin-top:18px;padding-top:18px;border-top:1px solid var(--border)}
.sugt{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:10px}
.sugrow{background:var(--surface);border:1px solid var(--border);border-radius:11px;padding:9px 12px;font-size:13.5px;margin-bottom:8px;cursor:pointer;transition:.14s;line-height:1.45}
.sugrow:hover{border-color:var(--accent-line);background:var(--accent-weak);color:var(--accent)}
.sugrow.flash{border-color:var(--ok);background:var(--ok-bg);color:var(--ok-ink)}
.tips{margin:0;padding-left:18px} .tips li{margin:7px 0;font-size:13.5px;line-height:1.5;color:var(--text)}
.tips li::marker{color:var(--accent)}
.tips.ttips li{list-style:none;position:relative;padding-left:6px}
.tips.ttips li::before{content:"✓";position:absolute;left:-16px;color:var(--accent);font-weight:800}
@media(max-width:900px){.rgrid{grid-template-columns:1fr}.raside{position:static}}
/* pricing / paywall */
.warnpill{border-color:var(--warn)!important;color:var(--warn-ink)!important}
.pay{padding:44px 24px;text-align:center}
.plans{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;max-width:840px;margin:28px auto 0;text-align:left}
.plan{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);padding:22px;display:flex;flex-direction:column;box-shadow:var(--sh-sm)}
.plan.feat{border-color:var(--accent);box-shadow:var(--sh-md);position:relative}
.plan.feat::before{content:"Most popular";position:absolute;top:-11px;left:22px;background:linear-gradient(140deg,var(--accent),var(--accent-2));color:var(--accent-ink);font-size:11px;font-weight:700;padding:3px 11px;border-radius:var(--pill)}
.plan h3{margin:0;font-size:16px;font-weight:700}
.plan .amt{font-size:32px;font-weight:750;letter-spacing:-.02em;margin:10px 0 2px}
.plan .amt small{font-size:13px;font-weight:600;color:var(--muted)}
.plan ul{list-style:none;margin:16px 0 20px;padding:0;flex:1}
.plan li{display:flex;gap:9px;align-items:flex-start;margin:9px 0;font-size:13.5px;line-height:1.4}
.plan li::before{content:"✓";color:var(--accent);font-weight:800;flex:0 0 auto}
.plan .btn{width:100%;justify-content:center}
.codebox{max-width:440px;margin:30px auto 0;display:flex;gap:8px}
.codebox input{flex:1;background:var(--surface);border:1.5px solid var(--border-2);border-radius:12px;padding:12px 13px;font-size:14px;font-family:var(--mono);color:var(--text);outline:none}
.codebox input:focus{border-color:var(--accent);box-shadow:0 0 0 4px var(--ring)}
@media(max-width:720px){.plans{grid-template-columns:1fr}}
/* onboarding */
.hero{text-align:center;padding:56px 28px}
.hero .kicker{display:inline-flex;align-items:center;gap:7px;font-size:12px;font-weight:650;color:var(--accent);
  background:var(--accent-weak);border:1px solid var(--accent-line);border-radius:var(--pill);padding:6px 14px;margin-bottom:22px}
.hero h1{font-family:var(--serif);font-weight:600;font-size:38px;line-height:1.12;letter-spacing:-.02em;margin:0 auto 16px;max-width:16ch}
.hero h1 em{font-style:italic;color:var(--accent)}
.hero p{color:var(--muted);font-size:16.5px;max-width:52ch;margin:0 auto 34px;line-height:1.6}
.steps{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;max-width:720px;margin:0 auto 30px}
.step{text-align:left;background:var(--surface);border:1px solid var(--border);border-radius:var(--r);padding:20px;box-shadow:var(--sh-sm)}
.step .n{width:30px;height:30px;border-radius:9px;background:var(--accent-weak);color:var(--accent);font-weight:750;
  display:grid;place-items:center;margin-bottom:12px;font-size:14px}
.step b{display:block;margin-bottom:3px;font-size:15px}
.step span{font-size:13.5px;color:var(--muted);line-height:1.5}
.trust{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
/* loading */
.bar{height:5px;border-radius:var(--pill);background:var(--sunken);overflow:hidden;margin-bottom:22px}
.bar i{display:block;height:100%;width:34%;border-radius:var(--pill);background:linear-gradient(90deg,var(--accent),var(--accent-2));
  animation:sl 1.15s infinite ease-in-out}
@keyframes sl{0%{margin-left:-34%}100%{margin-left:100%}}
.stages{list-style:none;margin:0 0 20px;padding:0;display:flex;flex-direction:column;gap:11px}
.stages li{display:flex;align-items:center;gap:12px;color:var(--faint);transition:.25s;font-weight:500}
.stages li.on{color:var(--text)} .stages li.done{color:var(--muted)}
.stages .tk{width:22px;height:22px;border-radius:50%;border:2px solid var(--border-2);display:grid;place-items:center;flex:0 0 auto;transition:.25s}
.stages li.on .tk{border-color:var(--accent);box-shadow:0 0 0 4px var(--ring)}
.stages li.done .tk{border-color:var(--ok);background:var(--ok);color:#fff}
.skel{height:14px;border-radius:8px;background:linear-gradient(90deg,var(--sunken),var(--surface-2),var(--sunken));
  background-size:200% 100%;animation:sh 1.4s infinite;margin:10px 0}
@keyframes sh{0%{background-position:200% 0}100%{background-position:-200% 0}}
.setup{display:flex;gap:12px;align-items:flex-start;background:var(--warn-bg);color:var(--warn-ink);border:1px solid transparent;
  border-radius:var(--r);padding:15px 18px;margin-bottom:20px;font-size:14px}
.setup code{background:color-mix(in srgb,var(--warn) 16%,transparent);padding:1px 6px;border-radius:6px;font-family:var(--mono);font-size:12.5px}
.fade{animation:fd .22s ease-out}
@keyframes fd{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
.hidden{display:none}
@media (max-width:720px){
  .wrap{padding:18px 16px 60px}.card.pad{padding:18px}.hero-input{padding:18px}
  .urlrow{flex-direction:column}.btn.primary{width:100%;justify-content:center}
  .attrs,.two,.steps{grid-template-columns:1fr}.hero h1{font-size:30px}.hero{padding:40px 12px}
  .rbar{flex-direction:column;align-items:stretch}.acts{justify-content:stretch}.acts .btn{flex:1;justify-content:center}
  .prices{gap:22px}
}
@media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important;scroll-behavior:auto!important}}

/* ---- photo upload, premium toggle ---- */
.ordiv{display:flex;align-items:center;gap:12px;margin:16px 2px 10px;color:var(--faint);font-size:11.5px;
  font-weight:600;text-transform:uppercase;letter-spacing:.08em;position:relative;z-index:1}
.ordiv::before,.ordiv::after{content:"";flex:1;height:1px;background:var(--border)}
.imgdrop{display:flex;align-items:center;gap:14px;padding:16px 18px;border:1.5px dashed var(--accent-line);
  border-radius:var(--r);background:var(--surface-2);color:var(--muted);cursor:pointer;transition:.16s;position:relative;z-index:1}
.imgdrop:hover,.imgdrop:focus-visible{border-color:var(--accent);background:var(--accent-weak);color:var(--text);outline:none}
.imgdrop.drag{border-color:var(--accent);background:var(--accent-weak);transform:scale(1.006)}
.imgdrop svg{color:var(--accent);flex:0 0 auto}
.imgdrop .idt{display:flex;flex-direction:column;line-height:1.4}
.imgdrop .idt b{color:var(--text);font-size:14px}.imgdrop .idt span{font-size:12px}
.imgprev{margin-top:12px;position:relative;z-index:1}
.imgprev .th{position:relative;flex:0 0 auto}
.imgprev img{width:64px;height:64px;border-radius:12px;object-fit:cover;border:1px solid var(--border)}
.imgprev .rm{position:absolute;top:-6px;right:-6px;width:20px;height:20px;border-radius:50%;border:0;cursor:pointer;
  background:var(--err,#c0392b);color:#fff;font-size:12px;line-height:20px;text-align:center;padding:0;box-shadow:0 2px 6px rgba(0,0,0,.25)}
.prem{display:flex;align-items:flex-start;gap:12px;margin:16px 2px 0;position:relative;z-index:1}
.prem .premt{font-size:13px;line-height:1.45}.prem .premt b{color:var(--text)}
.switch{flex:0 0 auto;cursor:pointer;margin-top:1px}
.switch input{position:absolute;opacity:0;width:0;height:0}
.switch .track{display:block;width:40px;height:23px;border-radius:999px;background:var(--sunken);border:1px solid var(--border);transition:.18s;position:relative}
.switch .knob{position:absolute;top:2px;left:2px;width:17px;height:17px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.3);transition:.18s}
.switch input:checked+.track{background:linear-gradient(140deg,var(--accent),var(--accent-2));border-color:transparent}
.switch input:checked+.track .knob{transform:translateX(17px)}
.switch input:focus-visible+.track{box-shadow:0 0 0 4px var(--ring)}
.engpill{display:inline-block;margin-left:6px;padding:2px 9px;border-radius:999px;font-size:10.5px;font-weight:700;letter-spacing:.03em;
  vertical-align:middle;background:var(--surface-2);border:1px solid var(--border);color:var(--muted)}
.engpill.prem{background:linear-gradient(140deg,var(--accent),var(--accent-2));color:var(--accent-ink);border-color:transparent}

/* ---- listing quality score ---- */
.score{display:flex;gap:22px;align-items:center;flex-wrap:wrap}
.ring{--v:0;flex:0 0 auto;width:96px;height:96px;border-radius:50%;display:grid;place-items:center;
  background:conic-gradient(var(--sc,var(--accent)) calc(var(--v)*1%),var(--sunken) 0);position:relative}
.ring::before{content:"";position:absolute;inset:8px;border-radius:50%;background:var(--surface)}
.ring .num{position:relative;font-size:26px;font-weight:800;letter-spacing:-.02em;line-height:1}
.ring .num small{display:block;font-size:10px;font-weight:600;color:var(--muted);letter-spacing:.06em;margin-top:2px}
.scbars{flex:1;min-width:220px;display:flex;flex-direction:column;gap:9px}
.scrow{display:grid;grid-template-columns:118px 1fr auto;gap:10px;align-items:center;font-size:12.5px}
.scrow .sbar{height:7px;border-radius:999px;background:var(--sunken);overflow:hidden}
.scrow .sbar i{display:block;height:100%;border-radius:999px;background:var(--accent);animation:none;transition:width .5s ease-out}
.scrow .sbar i.good{background:var(--ok)}.scrow .sbar i.mid{background:var(--warn,#DBAA46)}.scrow .sbar i.low{background:var(--err,#c0392b)}
.scrow .pt{font-weight:700;color:var(--muted);font-variant-numeric:tabular-nums}
.scfix{margin-top:16px;padding-top:14px;border-top:1px solid var(--border)}
.scfix .sugt{margin-bottom:8px}

/* ---- profit / fee calculator ---- */
.calc{display:grid;grid-template-columns:1fr 1fr;gap:14px 22px}
.calc .field{display:flex;flex-direction:column;gap:5px}
.calc label{font-size:11.5px;font-weight:650;color:var(--muted);letter-spacing:.02em}
.calc input,.calc select{padding:9px 11px;border:1px solid var(--border);border-radius:var(--r-sm);background:var(--surface);color:var(--text);font-size:14px;font-variant-numeric:tabular-nums}
.calc input:focus,.calc select:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--ring);outline:none}
.calcout{grid-column:1/-1;display:flex;flex-wrap:wrap;gap:10px;margin-top:4px}
.calcstat{flex:1;min-width:120px;background:var(--surface-2);border:1px solid var(--border);border-radius:var(--r-sm);padding:11px 13px}
.calcstat .k{font-size:11px;color:var(--muted);font-weight:650}
.calcstat .v{font-size:19px;font-weight:800;letter-spacing:-.01em;margin-top:3px;font-variant-numeric:tabular-nums}
.calcstat.hero{background:var(--accent-weak);border-color:var(--accent-line)}
.calcstat .v.pos{color:var(--ok-ink,var(--ok))}.calcstat .v.neg{color:var(--err,#c0392b)}
.calcnote{grid-column:1/-1;font-size:11.5px;color:var(--faint);line-height:1.5}
@media (max-width:560px){.calc{grid-template-columns:1fr}}

/* ---- keyword research ---- */
.kstats{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-top:14px}
.kstat{background:var(--surface-2);border:1px solid var(--border);border-radius:var(--r-sm);padding:11px 13px}
.kstat .k{font-size:11px;color:var(--muted);font-weight:650}
.kstat .v{margin-top:5px;font-size:16px;font-weight:700}
.kpill{display:inline-block;padding:2px 10px;border-radius:999px;font-size:11.5px;font-weight:700;text-transform:capitalize}
.kpill.good{background:var(--ok-bg,#E8F2E9);color:var(--ok-ink,#1E6B3E)}
.kpill.mid{background:var(--warn-bg,#FBF3DC);color:var(--warn-ink,#8A6410)}
.kpill.low{background:#F6E4DF;color:#B0432F}
:root[data-theme=dark] .kpill.low,.kpill.low{}
@media (prefers-color-scheme:dark){.kpill.low{background:#3A2018;color:#E9A48C}}
.kfilter{width:100%;margin:2px 0 14px;padding:9px 12px;border:1px solid var(--border);border-radius:var(--r-sm);background:var(--surface);color:var(--text);font-size:13.5px}
.kfilter:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--ring);outline:none}
table.kwtable{width:100%;border-collapse:collapse;font-size:13px}
table.kwtable th{text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);font-weight:700;
  padding:7px 9px;border-bottom:1px solid var(--border);white-space:nowrap;user-select:none}
table.kwtable th.tnum,table.kwtable td.tnum{text-align:right}
table.kwtable td{padding:8px 9px;border-bottom:1px solid var(--border);vertical-align:middle}
table.kwtable tr:hover td{background:var(--surface-2)}
table.kwtable .kkw{font-weight:600;min-width:170px}
table.kwtable .ksave{width:30px}
.star{background:none;border:0;cursor:pointer;font-size:16px;line-height:1;color:var(--muted);padding:0}
.star.on{color:#E6A100}
.oppwrap{display:flex;align-items:center;gap:8px;min-width:110px}
.oppbar{flex:1;height:7px;border-radius:999px;background:var(--sunken);overflow:hidden}
.oppbar i{display:block;height:100%;border-radius:999px;background:var(--accent)}
.oppbar i.good{background:var(--ok)}.oppbar i.mid{background:var(--warn,#DBAA46)}.oppbar i.low{background:var(--err,#c0392b)}
.oppwrap b{font-variant-numeric:tabular-nums;font-size:12px;color:var(--muted);width:24px;text-align:right}
.klink{display:inline-block;text-decoration:none;color:var(--accent);font-weight:800;font-size:15px;padding:0 4px}
.klink:hover{transform:translateY(-1px)}
.krow{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px 14px;background:var(--surface-2);
  border:1px solid var(--border);border-radius:var(--r-sm);font-size:14px}
.rmk{background:none;border:0;cursor:pointer;color:var(--muted);font-size:18px;line-height:1;padding:0 4px}
.rmk:hover{color:var(--err,#c0392b)}
@media (max-width:560px){.kstats{grid-template-columns:repeat(2,1fr)}}

/* ---- app shell + sidebar dashboard ---- */
.app{display:flex;min-height:100vh;align-items:stretch}
.sidebar{width:250px;flex:0 0 250px;background:var(--surface);border-right:1px solid var(--border);
  display:flex;flex-direction:column;padding:16px 14px;position:sticky;top:0;height:100vh;z-index:45}
.main-col{flex:1;min-width:0;display:flex;flex-direction:column}
.sb-brand{display:flex;align-items:center;gap:11px;padding:6px 8px 14px;cursor:pointer}
.sb-brand .logo{width:34px;height:34px;flex:0 0 auto}
.sb-brand b{font-size:15px;display:block;letter-spacing:-.01em}
.sb-brand span{font-size:11px;color:var(--muted)}
.sb-nav{display:flex;flex-direction:column;gap:3px;margin-top:6px}
.sb-item{display:flex;align-items:center;gap:11px;padding:10px 12px;border:0;background:none;border-radius:10px;
  color:var(--muted);font-size:14px;font-weight:600;cursor:pointer;text-align:left;width:100%;transition:.12s}
.sb-item svg{width:18px;height:18px;flex:0 0 auto;stroke-width:2}
.sb-item:hover{background:var(--surface-2);color:var(--text)}
.sb-item.active{background:var(--accent-weak);color:var(--accent);font-weight:700}
.sb-sep{height:1px;background:var(--border);margin:10px 6px}
.sb-foot{margin-top:auto;display:flex;flex-direction:column;gap:10px;padding-top:12px}
.sb-usage{font-size:12px;font-weight:650;color:var(--muted);background:var(--surface-2);border:1px solid var(--border);border-radius:10px;padding:9px 12px;text-align:center}
.sb-acct{display:flex;align-items:center;gap:10px;padding:8px;border-radius:10px;background:var(--surface-2);border:1px solid var(--border)}
.sb-acct .ava{width:32px;height:32px;border-radius:50%;background:linear-gradient(140deg,var(--accent),var(--accent-2));color:var(--accent-ink);display:grid;place-items:center;font-weight:800;font-size:14px;flex:0 0 auto}
.sb-acct-info{flex:1;min-width:0}.sb-acct-info b{font-size:13px;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sb-acct-info span{font-size:11px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block}
.sb-signout{background:none;border:0;cursor:pointer;color:var(--muted);font-size:17px;padding:4px;flex:0 0 auto}.sb-signout:hover{color:var(--err,#c0392b)}
.hamb{display:none;background:none;border:1px solid var(--border);border-radius:9px;cursor:pointer;color:var(--text);padding:7px}
.sb-backdrop{display:none}
body:not(.noauth) .topbar .brand{display:none}
body:not(.noauth) .noauth-only{display:none}
body:not(.is-admin) .admin-only{display:none}
.sb-item.admin-only.active,.sb-item.admin-only{color:var(--accent)}
body.noauth .sidebar{display:none}
body.noauth #inputCard{display:none}

/* landing / auth */
.auth-wrap{max-width:640px;margin:30px auto 0;padding:0 20px}
.auth-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:44px 52px;box-shadow:0 12px 34px -16px var(--ring)}
.auth-card h2{font-size:25px}
.auth-field input{padding:13px 15px;font-size:15px}
.auth-card h2{font-size:22px;letter-spacing:-.02em;margin-bottom:6px}
.auth-card .sub{color:var(--muted);font-size:13.5px;margin-bottom:20px}
.auth-field{margin-bottom:14px}
.auth-field label{display:block;font-size:12px;font-weight:650;color:var(--muted);margin-bottom:6px}
.auth-field input{width:100%;padding:11px 13px;border:1px solid var(--border);border-radius:var(--r-sm);background:var(--surface-2);color:var(--text);font-size:14.5px}
.auth-field input:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--ring);outline:none;background:var(--surface)}
.auth-alt{text-align:center;font-size:13px;color:var(--muted);margin-top:16px}
.auth-alt a{color:var(--accent);font-weight:650;cursor:pointer;text-decoration:none}
.gwrap{margin-bottom:16px;display:flex;justify-content:center;min-height:42px}
.gwrap>div{width:100%;display:flex;justify-content:center}
.gbtn{width:100%;justify-content:center;gap:10px;background:var(--surface);color:var(--text);border:1px solid var(--border-2);font-weight:600}
.gbtn:hover{background:var(--surface-2)}
.authdiv{display:flex;align-items:center;gap:12px;margin:0 0 16px;color:var(--faint);font-size:12px;font-weight:600}
.authdiv::before,.authdiv::after{content:"";flex:1;height:1px;background:var(--border)}
.rowacts{display:flex;gap:6px;align-items:center;flex-wrap:nowrap}
.icobtn.del:hover{color:var(--err,#c0392b)}
.modal-ov{position:fixed;inset:0;background:rgba(30,24,18,.5);display:flex;align-items:center;justify-content:center;z-index:100;padding:20px;backdrop-filter:blur(2px)}
.modal{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:24px 26px;width:100%;max-width:440px;box-shadow:var(--sh-lg);animation:fd .18s ease-out}
.modal select{width:100%;padding:11px 13px;border:1px solid var(--border-2);border-radius:var(--r-sm);background:var(--surface-2);color:var(--text);font-size:14.5px}
.modal select:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--ring);outline:none}
.auth-steps{display:flex;gap:6px;margin-bottom:20px}
.auth-steps .st{flex:1;height:4px;border-radius:999px;background:var(--sunken)}
.auth-steps .st.on{background:var(--accent)}
.chipsel{display:flex;flex-wrap:wrap;gap:8px}
.chipsel button{padding:9px 14px;border:1px solid var(--border);border-radius:999px;background:var(--surface-2);color:var(--text);font-size:13px;cursor:pointer;font-weight:600}
.chipsel button.on,.chipsel button:hover{border-color:var(--accent);background:var(--accent-weak);color:var(--accent)}

/* dashboard */
.dash-hd{margin-bottom:22px}
.dash-hd h1{font-size:25px;letter-spacing:-.02em}
.dash-hd p{color:var(--muted);margin-top:4px}
.dash-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:22px}
.dash-stat{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);padding:16px 18px}
.dash-stat .k{font-size:12px;color:var(--muted);font-weight:650}
.dash-stat .v{font-size:26px;font-weight:800;letter-spacing:-.02em;margin-top:6px}
.dash-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:14px}
.dash-tile{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);padding:20px;cursor:pointer;transition:.14s;text-align:left;display:block}
.dash-tile:hover{border-color:var(--accent);transform:translateY(-2px);box-shadow:0 10px 24px -14px var(--ring)}
.dash-tile .ti{width:40px;height:40px;border-radius:11px;background:var(--accent-weak);color:var(--accent);display:grid;place-items:center;margin-bottom:12px}
.dash-tile .ti svg{width:20px;height:20px}
.dash-tile b{font-size:15px;display:block}
.dash-tile span{font-size:12.5px;color:var(--muted);margin-top:3px;display:block;line-height:1.45}
.dgrid2{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px}
.anz{margin-bottom:16px;background:linear-gradient(145deg,var(--accent),var(--accent-2));border-color:transparent;box-shadow:var(--sh-md)}
.anz .lbl{color:rgba(255,255,255,.95)}
.anz .hint{color:rgba(255,255,255,.88)}
.anz .urlbox{background:#fff;border-color:transparent;box-shadow:0 2px 10px -2px rgba(0,0,0,.18)}
.anz .urlbox svg{color:var(--accent)}
.anz .urlbox input{color:#211E19}
.anz .btn.primary{background:#22190f;color:#fff;border-color:transparent}
.anz .btn.primary:hover{background:#000;transform:translateY(-1px)}
.dash-grid.mini{grid-template-columns:1fr 1fr;gap:10px}
.dash-grid.mini .dash-tile{padding:14px}
.dash-grid.mini .dash-tile .ti{width:34px;height:34px;margin-bottom:8px}
.dash-grid.mini .dash-tile b{font-size:13.5px}
.trow,.crow{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:9px 0;border-bottom:1px solid var(--border)}
.trow:last-child,.crow:last-child{border-bottom:0}
.tkw{font-weight:600;color:var(--text);text-decoration:none;font-size:14px}
.tkw:hover{color:var(--accent)}
.sugcard{background:linear-gradient(180deg,var(--accent-weak),var(--surface));border-color:var(--accent-line)}
.bblist{display:flex;flex-direction:column;gap:8px}
.bbrow{display:flex;flex-direction:column;gap:2px;padding:10px 13px;background:var(--surface);border:1px solid var(--border);border-radius:10px}
.bbrow .tkw{font-size:14px}
.bbrow .muted{font-size:12.5px;line-height:1.5}
.anz .disc-btn{color:rgba(255,255,255,.92)}
.anz .disc-panel textarea{background:#fff;color:#211E19;border-color:transparent}
.audit-modal{max-width:800px;width:100%;max-height:88vh;padding:0;display:flex;flex-direction:column;overflow:hidden}
.audit-hd{display:flex;align-items:center;justify-content:space-between;padding:15px 22px;border-bottom:1px solid var(--border);background:var(--surface);flex:0 0 auto}
.audit-hd .lbl{font-size:13px}
.audit-body{padding:20px 22px 26px;overflow-y:auto}
.ringwrap{display:flex;flex-direction:column;align-items:center;gap:6px;flex:0 0 auto}
.verdict{font-size:11.5px;font-weight:800;text-transform:uppercase;letter-spacing:.05em}
.audit-cols{display:grid;grid-template-columns:1.05fr .95fr;gap:24px;margin-top:18px;align-items:start}
.acol{min-width:0}
.colhd{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:10px}
.colhd .n{color:var(--faint);font-weight:600;margin-left:5px}
@media (max-width:640px){.audit-cols{grid-template-columns:1fr;gap:16px}}
.strengths{margin-top:12px;font-size:13px;color:var(--ok-ink,#1E6B3E);background:var(--ok-bg,#E8F2E9);border-radius:9px;padding:9px 13px;line-height:1.55}
.asec{border:1px solid var(--border);border-radius:14px;padding:16px 18px;margin-top:14px}
.asec-hd{display:flex;align-items:center;justify-content:space-between;gap:10px;font-size:15px;font-weight:700;margin-bottom:12px}
.asec-hd .faint{font-weight:600;font-size:12px}
.asec-cols{display:grid;grid-template-columns:1fr 1fr;gap:18px;align-items:start}
.sublbl{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:6px}
.sublbl.anzrow{display:flex;justify-content:space-between;align-items:center}
.secbadge{font-size:11px;font-weight:800;padding:3px 10px;border-radius:999px;white-space:nowrap;flex:0 0 auto}
.secbadge.err{background:var(--err-bg,#F8E4DF);color:var(--err-ink,#973020)}
.secbadge.warn{background:var(--warn-bg,#FAEFD7);color:var(--warn-ink,#8A5E0C)}
.secbadge.ok{background:var(--ok-bg,#E8F2E9);color:var(--ok-ink,#1E6B3E)}
.desc.sm{font-size:13px;line-height:1.55}
@media (max-width:640px){.asec-cols{grid-template-columns:1fr;gap:12px}}
.audit-top{display:flex;gap:20px;align-items:center;flex-wrap:wrap;margin-top:6px}
.atiles{flex:1;min-width:250px;display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
.atile{background:var(--surface-2);border:1px solid var(--border);border-radius:11px;padding:11px 13px}
.atile .k{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted)}
.atile .v{font-size:21px;font-weight:800;margin-top:3px;font-variant-numeric:tabular-nums}
.atile .v small{font-size:11px;color:var(--muted);font-weight:600;margin-left:1px}
.atile.ok{border-color:color-mix(in srgb,var(--ok) 45%,var(--border))}
.atile.warn .v{color:var(--warn,#C58A17)}.atile.bad .v{color:var(--err,#c0392b)}
.atile.bad{border-color:color-mix(in srgb,var(--err,#c0392b) 40%,var(--border))}
.isslist{list-style:none;padding:0;margin:8px 0 0;display:flex;flex-direction:column;gap:7px}
.iss{display:flex;gap:10px;align-items:flex-start;font-size:13.5px;line-height:1.5;padding:9px 12px;border-radius:9px;border:1px solid var(--border)}
.iss .issi{flex:0 0 20px;width:20px;height:20px;border-radius:50%;display:grid;place-items:center;font-size:11px;font-weight:800;color:#fff;margin-top:1px}
.iss.err{background:var(--err-bg,#F8E4DF);border-color:transparent}.iss.err .issi{background:var(--err,#c0392b)}
.iss.warn{background:var(--warn-bg,#FAEFD7);border-color:transparent}.iss.warn .issi{background:var(--warn,#C58A17)}
.iss.ok{background:var(--ok-bg,#E8F2E9);border-color:transparent}.iss.ok .issi{background:var(--ok,#2F8F57)}
@media (max-width:560px){.atiles{grid-template-columns:repeat(2,1fr)}}
.crow b{font-size:14px}
.cdays{font-weight:800;font-size:15px;color:var(--accent);font-variant-numeric:tabular-nums;flex:0 0 auto}
@media (max-width:820px){.dgrid2{grid-template-columns:1fr}}

@media (max-width:820px){
  .sidebar{position:fixed;left:0;top:0;transform:translateX(-100%);transition:transform .2s;box-shadow:0 0 40px rgba(0,0,0,.25)}
  .sidebar.open{transform:none}
  body:not(.noauth) .hamb{display:inline-flex}
  .sb-backdrop.show{display:block;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:44}
  .dash-stats{grid-template-columns:1fr}
}

/* ===== landing (full-width marketing) ===== */
#landing{display:none}
body.showing-landing #landing{display:block}
body.showing-landing .wrap{display:none}
body.showing-landing .topbar .brand{display:flex}
.landing{width:100%}
.lin{max-width:1240px;margin:0 auto;padding:0 32px}
.lsec{padding:74px 0}
.lhero{position:relative;overflow:hidden;padding:66px 0 82px;
  background:radial-gradient(1100px 460px at 80% -10%,var(--accent-weak),transparent 60%),linear-gradient(180deg,var(--surface),var(--bg))}
.lhero-in{display:grid;grid-template-columns:1.05fr .95fr;gap:56px;align-items:center}
.lkick{display:inline-flex;align-items:center;gap:8px;font-size:12px;font-weight:700;letter-spacing:.03em;
  color:var(--accent);background:var(--accent-weak);border:1px solid var(--accent-line);padding:6px 14px;border-radius:999px}
.lhero h1{font-family:var(--serif);font-weight:600;font-size:clamp(34px,4.6vw,55px);line-height:1.06;letter-spacing:-.02em;margin:20px 0 0}
.lhero h1 em{font-style:italic;color:var(--accent)}
.lhero .lead{font-size:clamp(16px,1.4vw,19px);color:var(--muted);max-width:36ch;margin:20px 0 0;line-height:1.6}
.lcta{display:flex;gap:12px;flex-wrap:wrap;margin-top:28px}
.btn.xl{font-size:16px;padding:15px 30px;border-radius:14px}
.ltrust{display:flex;gap:20px;flex-wrap:wrap;margin-top:26px;color:var(--muted);font-size:13px}
.ltrust span{display:inline-flex;align-items:center;gap:7px}.ltrust .tick{color:var(--ok);font-weight:800}
.mock{background:var(--surface);border:1px solid var(--border);border-radius:16px;box-shadow:var(--sh-lg);overflow:hidden}
.mock-bar{display:flex;align-items:center;gap:7px;padding:11px 14px;border-bottom:1px solid var(--border);background:var(--surface-2)}
.mock-bar i{width:10px;height:10px;border-radius:50%;background:var(--border-2)}
.mock-bar .url{margin-left:10px;flex:1;height:22px;border-radius:7px;background:var(--sunken);display:flex;align-items:center;padding:0 12px;font-size:11px;color:var(--faint)}
.mock-body{padding:20px}
.mk-lbl{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--faint);margin:0 0 8px}
.mk-title{font-size:14px;font-weight:600;line-height:1.4;color:var(--text)}
.mk-tags{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}
.mk-tags span{font-size:11px;background:var(--accent-weak);color:var(--accent);border:1px solid var(--accent-line);padding:3px 9px;border-radius:999px}
.mk-row{display:flex;align-items:center;gap:14px;margin-top:16px;padding-top:16px;border-top:1px solid var(--border)}
.mk-ring{position:relative;width:56px;height:56px;border-radius:50%;background:conic-gradient(var(--ok) 88%,var(--sunken) 0);display:grid;place-items:center;flex:0 0 auto}
.mk-ring::before{content:"";position:absolute;width:40px;height:40px;border-radius:50%;background:var(--surface)}
.mk-ring b{position:relative;font-size:15px;font-weight:800}
.mk-price{margin-left:auto;text-align:right}.mk-price .p{font-size:19px;font-weight:800}.mk-price .s{font-size:11px;color:var(--muted)}
.mk-krow{display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid var(--border);font-size:12px}
.mk-krow:last-child{border-bottom:0}.mk-krow .kw{flex:1;font-weight:600}
.mk-pill{font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px}
.mk-pill.g{background:var(--ok-bg);color:var(--ok-ink)}.mk-pill.m{background:var(--warn-bg);color:var(--warn-ink)}
.mk-ob{width:56px;height:6px;border-radius:999px;background:var(--sunken);overflow:hidden}.mk-ob i{display:block;height:100%;background:var(--accent)}
.lstrip{background:var(--surface-2);border-top:1px solid var(--border);border-bottom:1px solid var(--border)}
.lstrip-in{display:flex;justify-content:center;gap:44px;flex-wrap:wrap;padding:22px 32px;text-align:center}
.lstrip b{font-family:var(--serif);font-size:24px;display:block;color:var(--text)}
.lstrip span{font-size:12.5px;color:var(--muted)}
.lsec-h{text-align:center;max-width:660px;margin:0 auto 46px}
.lsec-h .lkick2{font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--accent)}
.lsec-h h2{font-family:var(--serif);font-weight:600;font-size:clamp(26px,3vw,38px);letter-spacing:-.02em;margin:10px 0 0}
.lsec-h p{color:var(--muted);margin:12px 0 0;font-size:16px}
.fgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.fcard{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);padding:26px 24px;transition:.15s}
.fcard:hover{border-color:var(--accent-line);box-shadow:var(--sh-md);transform:translateY(-3px)}
.fcard .fi{width:46px;height:46px;border-radius:12px;background:var(--accent-weak);color:var(--accent);display:grid;place-items:center;margin-bottom:16px}
.fcard .fi svg{width:23px;height:23px}
.fcard h3{font-size:17px;letter-spacing:-.01em;margin:0 0 7px}
.fcard p{color:var(--muted);font-size:14px;line-height:1.6;margin:0}
.spot{display:grid;grid-template-columns:1fr 1fr;gap:56px;align-items:center;padding:32px 0}
.spot.rev .spot-txt{order:2}
.spot-txt .lkick2{color:var(--accent);font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase}
.spot-txt h3{font-family:var(--serif);font-weight:600;font-size:clamp(22px,2.4vw,30px);letter-spacing:-.02em;margin:10px 0 12px}
.spot-txt p{color:var(--muted);font-size:15.5px;line-height:1.65;margin:0}
.spot-txt ul{list-style:none;padding:0;margin:16px 0 0;display:flex;flex-direction:column;gap:9px}
.spot-txt li{display:flex;gap:10px;font-size:14.5px;color:var(--text)}
.spot-txt li::before{content:"✓";color:var(--ok);font-weight:800;flex:0 0 auto}
.lsteps{display:grid;grid-template-columns:repeat(3,1fr);gap:24px}
.lstep{text-align:center;padding:8px}
.lstep .num{width:44px;height:44px;border-radius:50%;background:var(--accent);color:var(--accent-ink);display:grid;place-items:center;font-weight:800;font-size:18px;margin:0 auto 14px}
.lstep h4{font-size:17px;margin:0 0 6px}.lstep p{color:var(--muted);font-size:14px;margin:0;line-height:1.55}
.lcta-sec{text-align:center;background:linear-gradient(160deg,var(--accent),var(--accent-2));border-radius:24px;padding:56px 32px}
.lcta-sec h2{font-family:var(--serif);font-weight:600;color:#fff;font-size:clamp(26px,3vw,36px);margin:0 0 10px}
.lcta-sec p{color:rgba(255,255,255,.92);margin:0 0 24px;font-size:16px}
.lcta-sec .btn{background:#fff;color:var(--accent);border-color:#fff}
.lfoot{border-top:1px solid var(--border);padding:32px 0}
.lfoot-in{display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;align-items:center;color:var(--muted);font-size:13px}
@media (max-width:900px){
  .lhero-in,.spot{grid-template-columns:1fr;gap:34px}
  .spot.rev .spot-txt{order:0}
  .fgrid,.lsteps{grid-template-columns:1fr}
  .lin{padding:0 20px}.lsec{padding:52px 0}
}
</style>
<script src="https://accounts.google.com/gsi/client" async defer onload="window.__gsiReady=true"></script>
</head>
<body class="noauth">
<div class="sb-backdrop" id="sbBackdrop"></div>
<div class="app">
<aside class="sidebar" id="sidebar">
  <div class="sb-brand" id="sbBrand">
    <span class="logo"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.6 12.5 12.5 20.6a2 2 0 0 1-2.8 0l-6.3-6.3a2 2 0 0 1 0-2.8L9.9 3a2 2 0 0 1 1.5-.6l5.6.2a2 2 0 0 1 1.9 1.9l.2 5.6a2 2 0 0 1-.5 1.4Z"/><circle cx="15" cy="9" r="1.4" fill="currentColor" stroke="none"/></svg></span>
    <div><b>Etsy Studio</b><span>AI Etsy toolkit</span></div>
  </div>
  <nav class="sb-nav" id="sbNav">
    <button class="sb-item" data-view="dashboard"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>Dashboard</button>
    <button class="sb-item admin-only" data-view="admin"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3Z"/><path d="m9 12 2 2 4-4"/></svg>Admin</button>
    <button class="sb-item" data-view="generator"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>New Listing</button>
    <button class="sb-item" data-view="keywords"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>Keyword Research</button>
    <button class="sb-item" data-view="research"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17l6-6 4 4 7-7"/><path d="M17 8h4v4"/></svg>Product Research</button>
    <button class="sb-item" data-view="tools"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg>Tools</button>
    <div class="sb-sep"></div>
    <button class="sb-item" data-view="plans"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3 6.3 6.9.9-5 4.8 1.2 6.9L12 18.6 5.9 20.9 7.1 14l-5-4.8 6.9-.9L12 2Z"/></svg>Plans &amp; Pricing</button>
  </nav>
  <div class="sb-foot">
    <div class="sb-usage" id="sbUsage">…</div>
    <div class="sb-acct">
      <div class="ava" id="sbAva">U</div>
      <div class="sb-acct-info"><b id="sbName">User</b><span id="sbEmail">—</span></div>
      <button class="sb-signout" id="signoutBtn" title="Sign out">⎋</button>
    </div>
  </div>
</aside>
<div class="main-col">
<header class="topbar">
  <button class="hamb" id="hambBtn" aria-label="Menu"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg></button>
  <div class="brand" id="homeBtn" style="cursor:pointer">
    <span class="logo"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.6 12.5 12.5 20.6a2 2 0 0 1-2.8 0l-6.3-6.3a2 2 0 0 1 0-2.8L9.9 3a2 2 0 0 1 1.5-.6l5.6.2a2 2 0 0 1 1.9 1.9l.2 5.6a2 2 0 0 1-.5 1.4Z"/><circle cx="15" cy="9" r="1.4" fill="currentColor" stroke="none"/></svg></span>
    <div><b>Etsy Listing Studio</b><span>Product link → ready-to-sell listing</span></div>
  </div>
  <div class="tbar-r">
    <button class="btn ghost sm noauth-only" id="navLogin">Log in</button>
    <button class="btn primary sm noauth-only" id="navStart">Get started free</button>
    <span class="badge" id="usage">…</span>
    <button class="icobtn" id="themeBtn" title="Toggle theme" aria-label="Toggle theme"></button>
  </div>
</header>

<div id="landing" class="landing"></div>

<main class="wrap">
  <?php if ($needsKey): ?>
  <div class="setup"><span style="font-size:18px;line-height:1">⚙️</span><div><b>Almost ready.</b>
    <?php if ($noConfig): ?> Copy <code>config.sample.php</code> to <code>config.php</code>, then paste an API key.
    <?php elseif ($provider === 'gemini'): ?> Open <code>config.php</code> and paste your free Gemini API key (from <code>aistudio.google.com</code>), then reload.
    <?php elseif ($provider === 'openrouter'): ?> Open <code>config.php</code> and paste your OpenRouter API key (from <code>openrouter.ai</code>), then reload.
    <?php else: ?> Open <code>config.php</code> and paste your Anthropic API key, then reload.
    <?php endif; ?> You can preview everything now with <b>See a sample listing</b> below.</div></div>
  <?php endif; ?>

  <section class="hero-input" id="inputCard">
    <label class="fieldlbl" for="url">Product URL</label>
    <div class="urlrow">
      <div class="urlbox">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/></svg>
        <input id="url" type="url" inputmode="url" autocomplete="off" spellcheck="false" placeholder="https://example.com/products/hand-poured-soy-candle">
      </div>
      <button class="btn primary" id="goBtn">Generate listing
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>
    </div>
    <p class="hint">Paste any product page — a supplier, a marketplace, or your own shop.</p>

    <div class="ordiv"><span>or start from a photo — no link needed</span></div>
    <div class="imgdrop" id="imgDrop" tabindex="0" role="button" aria-label="Upload product photos">
      <input id="imgInput" type="file" accept="image/*" multiple hidden>
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4h-5L8 6H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-4l-1.5-2Z"/><circle cx="12" cy="13" r="3.5"/></svg>
      <div class="idt"><b>Add product photo(s)</b><span>Drag &amp; drop or click — AI reads the image. Up to 4.</span></div>
    </div>
    <div class="thumbs imgprev" id="imgPrev" hidden></div>

    <div class="controls">
      <div>
        <div class="fieldlbl" style="margin-bottom:8px">Target market</div>
        <div class="seg" role="radiogroup" aria-label="Target market" id="market">
          <button role="radio" data-v="uk" aria-checked="false">UK</button>
          <button role="radio" data-v="us" aria-checked="false">US</button>
          <button role="radio" data-v="both" aria-checked="true">Both</button>
        </div>
      </div>
      <div>
        <label class="fieldlbl" for="maker" style="margin-bottom:8px">How it's made</label>
        <select id="maker" class="mksel">
          <option value="i_make">I make / assemble it</option>
          <option value="shop_member">A shop member makes it</option>
          <option value="reseller">Another company makes it</option>
        </select>
      </div>
      <div class="catbox">
        <label class="fieldlbl" for="cat">Category hint <span class="faint">(optional)</span></label>
        <input id="cat" type="text" placeholder="e.g. Home &amp; Living › Candles">
      </div>
    </div>
    <p class="hint">“How it's made” sets Etsy's <b>Who made it / When was it made</b> — pick it honestly; a wrong choice can risk your shop.</p>

    <div class="prem">
      <label class="switch"><input type="checkbox" id="premium"><span class="track"><span class="knob"></span></span></label>
      <div class="premt"><b>✦ Premium quality</b><span class="faint"> — sharper, more unique copy via Claude (needs a Claude key in config.php; otherwise uses the free model).</span></div>
    </div>

    <div class="disc">
      <button class="disc-btn" id="discBtn" aria-expanded="false"><span class="car">▸</span> Paste page content instead</button>
      <div class="disc-panel" id="discPanel" hidden>
        <textarea id="html" placeholder="Offline, or the page won't load (Amazon / AliExpress often block)? Open it in your browser, select all (Ctrl+A), copy, and paste here."></textarea>
        <p class="hint">If both the URL and this box are filled, the pasted content wins.</p>
      </div>
    </div>
  </section>

  <section id="state" aria-live="polite" style="margin-top:20px"></section>
</main>
</div><!-- .main-col -->
</div><!-- .app -->

<template id="ic">
  <svg class="i-copy" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2.5"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
  <svg class="i-check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
  <svg class="i-dl" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>
  <svg class="i-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
  <svg class="i-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>
</template>

<script>
const $=(s,r=document)=>r.querySelector(s), $$=(s,r=document)=>[...r.querySelectorAll(s)];
const IC=$('#ic').content, icon=n=>IC.querySelector('.i-'+n).cloneNode(true);
const esc=s=>String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
const GOOGLE_CLIENT_ID=<?= json_encode($googleClientId) ?>;
window.addEventListener('error',function(e){try{document.documentElement.setAttribute('data-jserr',(e.message||'')+' @ '+String(e.filename||'').split('/').pop()+':'+(e.lineno||0)+':'+(e.colno||0));}catch(_){}});

/* Google Sign-In (real GIS if a Client ID is set in config.php; else a button with setup steps) */
const gLogo=()=>'<svg width="17" height="17" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9 3.6l6.7-6.7C35.6 2.4 30.2 0 24 0 14.6 0 6.5 5.4 2.6 13.2l7.8 6.1C12.3 13.2 17.6 9.5 24 9.5z"/><path fill="#4285F4" d="M46.1 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.4c-.5 2.9-2.1 5.3-4.6 7l7.1 5.5c4.2-3.9 6.6-9.6 6.6-16z"/><path fill="#FBBC05" d="M10.4 28.3c-.5-1.4-.8-2.9-.8-4.3s.3-3 .8-4.3l-7.8-6.1C.9 16.7 0 20.2 0 24s.9 7.3 2.6 10.4l7.8-6.1z"/><path fill="#34A853" d="M24 48c6.2 0 11.5-2 15.3-5.5l-7.1-5.5c-2 1.3-4.6 2.1-8.2 2.1-6.4 0-11.7-3.7-13.6-9.8l-7.8 6.1C6.5 42.6 14.6 48 24 48z"/></svg>';
async function onGoogleCred(resp){
  try{const b=resp.credential.split('.')[1].replace(/-/g,'+').replace(/_/g,'/');
    const p=JSON.parse(decodeURIComponent(escape(atob(b))));
    const r=await authApi('google',{name:p.name||'',email:p.email||'',google:true});
    if(r.error){alert(r.error);return;}
    applyAuth(r);
  }catch(e){alert('Google sign-in could not be completed — please use email sign-up.');}
}
function mountGoogle(sel,label,tries){
  const box=$(sel);if(!box)return;tries=tries||0;
  if(GOOGLE_CLIENT_ID && window.google && google.accounts && google.accounts.id){
    try{
      google.accounts.id.initialize({client_id:GOOGLE_CLIENT_ID,callback:onGoogleCred,auto_select:false});
      box.innerHTML='';
      google.accounts.id.renderButton(box,{theme:'outline',size:'large',type:'standard',text:label||'continue_with',shape:'pill',width:Math.min(360,box.clientWidth||340),logo_alignment:'left'});
      return;
    }catch(e){}
  }
  if(GOOGLE_CLIENT_ID && tries<8){setTimeout(()=>mountGoogle(sel,label,tries+1),350);return;}
  // fallback: option is still visible, explains how to enable
  box.innerHTML=`<button class="btn gbtn" type="button">${gLogo()}<span>Continue with Google</span></button>`;
  box.querySelector('button').onclick=()=>{alert("To turn on Google sign-in:\n1. console.cloud.google.com  →  APIs & Services  →  Credentials\n2. Create OAuth client ID  →  type: Web application\n3. Add  http://localhost:8123  to Authorised JavaScript origins\n4. Paste the Client ID into config.php as google_client_id\n\nMeanwhile, use the email sign-up below — it works right away.");};
}

/* theme */
function applyTheme(t){document.documentElement.setAttribute('data-theme',t);const b=$('#themeBtn');b.innerHTML='';b.appendChild(icon(t==='dark'?'sun':'moon'));}
(function(){let t=localStorage.getItem('els-theme')||(matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light');applyTheme(t);})();
$('#themeBtn').onclick=()=>{const t=document.documentElement.getAttribute('data-theme')==='dark'?'light':'dark';localStorage.setItem('els-theme',t);applyTheme(t);};

/* inputs */
let market=localStorage.getItem('els-market')||'both';
function setMarket(v){market=v;localStorage.setItem('els-market',v);$$('#market button').forEach(b=>b.setAttribute('aria-checked',String(b.dataset.v===v)));}
setMarket(market);
$$('#market button').forEach(b=>b.onclick=()=>setMarket(b.dataset.v));
let maker=localStorage.getItem('els-maker')||'i_make';
const makerSel=$('#maker');makerSel.value=maker;makerSel.onchange=()=>{maker=makerSel.value;localStorage.setItem('els-maker',maker);};
$('#discBtn').onclick=e=>{const on=e.currentTarget.getAttribute('aria-expanded')==='true';e.currentTarget.setAttribute('aria-expanded',String(!on));$('#discPanel').hidden=on;if(!on)$('#html').focus();};
$('#url').addEventListener('keydown',e=>{if(e.key==='Enter')run();});
$('#goBtn').onclick=run;

/* photo upload (up to 4) → base64 data URLs, read by Gemini Vision */
let photos=[];
const imgDrop=$('#imgDrop'),imgInput=$('#imgInput'),imgPrev=$('#imgPrev');
function renderPhotos(){
  imgPrev.hidden=!photos.length;imgPrev.innerHTML='';
  photos.forEach((p,i)=>{const w=document.createElement('div');w.className='th';
    const im=new Image();im.src=p;const rm=document.createElement('button');rm.className='rm';rm.type='button';rm.innerHTML='&times;';rm.title='Remove';
    rm.onclick=()=>{photos.splice(i,1);renderPhotos();};w.append(im,rm);imgPrev.appendChild(w);});
  imgDrop.querySelector('.idt b').textContent=photos.length?`${photos.length} photo${photos.length>1?'s':''} added`:'Add product photo(s)';
}
function addFiles(files){
  const room=4-photos.length;[...files].filter(f=>/^image\//.test(f.type)).slice(0,Math.max(0,room)).forEach(f=>{
    if(f.size>8*1024*1024){renderError({h:'That image is too large.',b:`"${f.name}" is over 8MB. Please use a smaller photo.`,a:'retry'});return;}
    const r=new FileReader();r.onload=()=>{photos.push(r.result);renderPhotos();};r.readAsDataURL(f);});
}
imgDrop.onclick=()=>imgInput.click();
imgDrop.onkeydown=e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();imgInput.click();}};
imgInput.onchange=()=>{addFiles(imgInput.files);imgInput.value='';};
['dragenter','dragover'].forEach(ev=>imgDrop.addEventListener(ev,e=>{e.preventDefault();imgDrop.classList.add('drag');}));
['dragleave','drop'].forEach(ev=>imgDrop.addEventListener(ev,e=>{e.preventDefault();imgDrop.classList.remove('drag');}));
imgDrop.addEventListener('drop',e=>{if(e.dataTransfer&&e.dataTransfer.files)addFiles(e.dataTransfer.files);});

/* clipboard */
async function copyText(t){try{await navigator.clipboard.writeText(t);return true;}catch(e){const a=document.createElement('textarea');a.value=t;a.style.cssText='position:fixed;opacity:0';document.body.appendChild(a);a.select();let ok=false;try{ok=document.execCommand('copy');}catch(_){}a.remove();return ok;}}
function copyBtn(get){const b=document.createElement('button');b.className='icobtn';b.title='Copy';b.setAttribute('aria-label','Copy');b.appendChild(icon('copy'));
  b.onclick=async()=>{if(await copyText(get())){b.innerHTML='';b.appendChild(icon('check'));b.classList.add('copied');setTimeout(()=>{b.innerHTML='';b.appendChild(icon('copy'));b.classList.remove('copied');},1400);}};return b;}
function dl(name,mime,data){const u=URL.createObjectURL(new Blob([data],{type:mime}));const a=document.createElement('a');a.href=u;a.download=name;a.click();setTimeout(()=>URL.revokeObjectURL(u),2000);}

/* run */
let stageTimer;
function run(){
  const url=$('#url').value.trim(), html=$('#html').value.trim(), premium=$('#premium').checked, imgs=photos.slice();
  if(!url && html.length<40 && !imgs.length){renderError({h:"Need a product to work from.",b:"Paste a product URL, upload a product photo, or paste the page content.",a:'url'});return;}
  const btn=$('#goBtn');btn.disabled=true;btn.textContent='Working…';
  renderLoading((!!url && html.length<40) || imgs.length>0);
  fetch('api/generate.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({url,html,market,maker,premium,image:imgs,access_code:accessCode,token:getToken(),category:$('#cat').value.trim()})})
    .then(r=>r.json()).then(d=>{clearInterval(stageTimer);
      if(d.paywall){usage={used:d.used,limit:d.limit,unlimited:false,remaining:0};renderUsage();renderPaywall(true);return;}
      if(d.error)renderError({h:'We couldn’t finish that one.',b:d.error,a:d.needs_paste?'paste':'retry'});
      else{if(d.usage){usage=d.usage;renderUsage();}localStorage.setItem('els-gencount',(+localStorage.getItem('els-gencount')||0)+1);renderResult(d);}})
    .catch(err=>{clearInterval(stageTimer);renderError({h:'Something interrupted the write-up.',b:'Network error: '+err.message+'. Your inputs are still here — try again.',a:'retry'});})
    .finally(()=>{btn.disabled=false;btn.innerHTML='Regenerate';});
}

const STATE=$('#state');
function frame(){STATE.innerHTML='';const d=document.createElement('div');d.className='fade';STATE.appendChild(d);return d;}

function renderLoading(slow){
  const w=frame();
  const st=['Reading your product','Analysing the details','Writing your Etsy listing','Checking Etsy rules & competition'];
  w.innerHTML=`<div class="card pad"><div class="bar"><i></i></div>
    <ul class="stages">${st.map((s,i)=>`<li data-i="${i}"><span class="tk"></span><span>${s}…</span></li>`).join('')}</ul>
    <div class="skel" style="width:66%"></div><div class="skel" style="width:88%"></div><div class="skel" style="width:44%"></div>
    ${slow?`<div class="muted" style="margin-top:15px;font-size:12.5px;line-height:1.5">Reading a supplier page or a photo the safe way can take <b>30–60 seconds</b> — hang tight, it's working.</div>`:''}</div>`;
  const lis=$$('.stages li',w);let c=0;
  const set=i=>lis.forEach((li,k)=>{li.classList.toggle('on',k===i);li.classList.toggle('done',k<i);if(k<i){const t=$('.tk',li);if(!t.querySelector('svg')){t.innerHTML='';t.appendChild(icon('check'));}}});
  set(0);stageTimer=setInterval(()=>{if(c<st.length-1){c++;set(c);}},slow?4500:1400);
}

function renderError({h,b,a}){
  const w=frame();
  w.innerHTML=`<div class="banner likely_violation"><span class="ic">!</span><div style="flex:1"><div class="bh">${esc(h)}</div>
    <div>${esc(b)}</div><div style="margin-top:14px;display:flex;gap:9px;flex-wrap:wrap" id="ea"></div></div></div>`;
  const ea=$('#ea',w),mk=(t,fn,cls='ghost sm')=>{const x=document.createElement('button');x.className='btn '+cls;x.textContent=t;x.onclick=fn;return x;};
  if(a==='paste')ea.appendChild(mk('Paste page content instead',()=>{$('#discBtn').setAttribute('aria-expanded','true');$('#discPanel').hidden=false;$('#html').focus();},'primary sm'));
  if(a==='url')ea.appendChild(mk('Edit URL',()=>$('#url').focus(),'primary sm'));
  ea.appendChild(mk('Try again',run));
}

/* result */
const CMAP={beige:'#E4D5B7',black:'#1c1c1c',blue:'#3B6FB0',bronze:'#8C6B3F',brown:'#6B4A2B',clear:'#D9E4E9',copper:'#B87333',gold:'#D4AF37',grey:'#9AA0A6',green:'#3F8A54',orange:'#E1712B',pink:'#E48FB0',purple:'#7E57C2',red:'#C0392B',rose_gold:'#E6A99B',silver:'#C2C6CB',white:'#F3F1EC',yellow:'#E6C74A'};
const pretty=s=>String(s||'').replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase());
const WHO={i_did:'I did',someone_else:'Another company or person',collective:'A member of my shop'};
const WHAT={finished_product:'A finished product',supply_or_tool:'A supply or tool to make things'};
const whenL=v=>{if(v==='made_to_order')return'Made to order';const m=String(v).match(/^(\d{4})_(\d{4})$/);return m?`Made ${m[1]}–${m[2]}`:pretty(v);};
const fmt=n=>{const x=Number(n);return isNaN(x)?(n??''):x.toFixed(2);};
function card(label,meta,body,copyGet){
  const c=document.createElement('section');c.className='card pad';
  const h=document.createElement('div');h.className='chead';
  h.innerHTML=`<span class="lbl">${esc(label)}</span>`;
  const r=document.createElement('div');r.style.cssText='display:flex;align-items:center;gap:10px';
  if(meta){const m=typeof meta==='string'?Object.assign(document.createElement('span'),{className:'counter',textContent:meta}):meta;r.appendChild(m);}
  if(copyGet)r.appendChild(copyBtn(copyGet));
  h.appendChild(r);c.appendChild(h);c.appendChild(body);return c;
}
const swatch=n=>{const hex=CMAP[String(n).toLowerCase()];return hex?`<span class="sw" style="background:${hex}"></span>`:'';};

/* Listing Quality Score — deterministic, transparent (no opaque AI self-grade) */
function computeScore(L,src){
  const rows=[],fixes=[],clamp=(n,mx)=>Math.max(0,Math.min(mx,Math.round(n)));
  const tags=Array.isArray(L.tags)?L.tags:[];
  const kw=((tags[0]||'').toLowerCase().split(/\s+/)[0])||'';
  // Title /20
  const title=(L.title||'').trim(),tlen=title.length,tw=title.split(/\s+/).filter(Boolean).length;let tp=0;
  if(tlen>=45&&tlen<=140)tp+=8;else if(tlen>0)tp+=4;
  if(tlen>140)fixes.push(`Title is ${tlen} characters — trim to Etsy's 140 max.`);
  if(tw<=14)tp+=4;else fixes.push(`Title has ${tw} words — Etsy favours 14 or fewer.`);
  if(!(title&&title===title.toUpperCase()))tp+=3;
  if(kw&&title.toLowerCase().slice(0,40).includes(kw))tp+=5;else{tp+=2;fixes.push('Front-load your strongest keyword in the first 40 characters of the title.');}
  rows.push({label:'Title',pts:clamp(tp,20),max:20});
  // Tags /20
  const n=tags.length;let gp=Math.round(n/13*8);
  if(n<13)fixes.push(`Only ${n}/13 tags — fill all 13 to widen reach.`);
  const over=tags.filter(t=>(t||'').length>20).length;if(over)fixes.push(`${over} tag(s) exceed 20 characters.`);else gp+=2;
  const multi=tags.filter(t=>(t||'').trim().split(/\s+/).length>=2).length;gp+=Math.round(multi/Math.max(1,n)*6);
  if(n&&multi<n*0.7)fixes.push('Use more multi-word (long-tail) tags — they rank better than single words.');
  const allw=tags.join(' ').toLowerCase().split(/\s+/).filter(Boolean),uniq=new Set(allw).size;
  if(allw.length&&uniq/allw.length>0.6)gp+=4;else{gp+=1;if(allw.length)fixes.push('Reduce repeated words across your tags — diversify the phrases.');}
  rows.push({label:'Tags',pts:clamp(gp,20),max:20});
  // Description /20
  const desc=(L.description||'').trim(),dlen=desc.length;let dp=0;
  if(dlen>=600)dp+=8;else if(dlen>=300)dp+=6;else{dp+=2;fixes.push('Lengthen the description — aim for 600+ characters (materials, size, use, care).');}
  if(kw&&desc.slice(0,160).toLowerCase().includes(kw))dp+=6;else{dp+=2;fixes.push('Put your primary keyword in the first sentence of the description.');}
  dp+=desc.includes('\n')?6:3;
  rows.push({label:'Description',pts:clamp(dp,20),max:20});
  // Attributes /15
  const a=L.attributes||{},mats=Array.isArray(L.materials)?L.materials:[],ca=Array.isArray(L.category_attributes)?L.category_attributes:[];let ap=2;
  if(mats.length)ap+=4;else fixes.push('Add materials — Etsy uses them as search filters.');
  if(ca.length>=3)ap+=5;else if(ca.length)ap+=3;else fixes.push('Fill the category-specific attributes for more filter visibility.');
  if(a.primary_colour&&a.primary_colour!=='none')ap+=2;
  ap+=((a.occasion&&a.occasion!=='none')||(a.style&&a.style!=='none'))?2:1;
  rows.push({label:'Attributes',pts:clamp(ap,15),max:15});
  // Photos /10
  const imgs=(src.images||[]).length;let ip=imgs>=7?10:imgs>=4?8:imgs>=1?5:2;
  if(imgs<4)fixes.push('Add more photos (Etsy allows 10) — lifestyle + scale shots lift clicks.');
  rows.push({label:'Photos',pts:ip,max:10});
  // Policy /15
  const stt=(L.policy_check||{}).status||'caution';const pp=stt==='ok'?15:stt==='caution'?9:0;
  if(stt==='likely_violation')fixes.push('Resolve the policy flags before publishing — risk of takedown/suspension.');
  else if(stt==='caution')fixes.push('Check the compliance notes (brand/trademark words, “who made it”).');
  rows.push({label:'Etsy policy',pts:pp,max:15});
  const total=clamp(rows.reduce((s,r)=>s+r.pts,0),100);
  const grade=total>=85?'Excellent':total>=70?'Strong':total>=55?'Fair':'Needs work';
  return {total,rows,fixes:fixes.slice(0,6),grade};
}

/* Interactive Etsy profit & fee calculator */
function calcCard(L,mk){
  const p=L.pricing||{},cur=mk==='us'?'USD':'GBP';
  const sell=cur==='USD'?p.retail_usd:p.retail_gbp,cost=Number(p.source_price)||0;
  const c=document.createElement('section');c.className='card pad';
  const h=document.createElement('div');h.className='chead';h.innerHTML='<span class="lbl">Profit & Etsy fee calculator</span>';c.appendChild(h);
  const wrap=document.createElement('div');wrap.className='calc';
  wrap.innerHTML=`
    <div class="field"><label>Currency</label><select data-c="cur"><option value="GBP" ${cur==='GBP'?'selected':''}>£ GBP (UK)</option><option value="USD" ${cur==='USD'?'selected':''}>$ USD (US)</option></select></div>
    <div class="field"><label>Sell price</label><input data-c="sell" type="number" step="0.01" min="0" value="${fmt(sell||0)}"></div>
    <div class="field"><label>Your item cost</label><input data-c="cost" type="number" step="0.01" min="0" value="${fmt(cost||0)}"></div>
    <div class="field"><label>Shipping charged to buyer</label><input data-c="ship" type="number" step="0.01" min="0" value="0"></div>
    <div class="field"><label>Your shipping cost</label><input data-c="shipcost" type="number" step="0.01" min="0" value="0"></div>
    <div class="field"><label>Offsite Ads fee</label><select data-c="ads"><option value="0">No (usually off)</option><option value="0.15">Yes — apply 15%</option></select></div>
    <div class="calcout" data-c="out"></div>
    <div class="calcnote">Fees: £/$0.20 listing · 6.5% transaction (item + shipping) · payment processing (UK 4% + £0.20, US 3% + $0.25) · optional 15% Offsite Ads. VAT/regional taxes excluded — estimates only.</div>`;
  c.appendChild(wrap);
  const g=k=>wrap.querySelector(`[data-c="${k}"]`);
  const recompute=()=>{
    const curr=g('cur').value,sym=curr==='USD'?'$':'£';
    const sp=+g('sell').value||0,co=+g('cost').value||0,sh=+g('ship').value||0,shc=+g('shipcost').value||0,ads=+g('ads').value||0;
    const order=sp+sh,trans=order*0.065,proc=curr==='USD'?order*0.03+0.25:order*0.04+0.20,adfee=order*ads,fees=0.20+trans+proc+adfee;
    const net=order-co-shc-fees,margin=sp>0?net/sp*100:0,cls=net>=0?'pos':'neg';
    g('out').innerHTML=`
      <div class="calcstat"><div class="k">Etsy fees</div><div class="v">${sym}${fees.toFixed(2)}</div></div>
      <div class="calcstat hero"><div class="k">Net profit / sale</div><div class="v ${cls}">${sym}${net.toFixed(2)}</div></div>
      <div class="calcstat"><div class="k">Profit margin</div><div class="v ${cls}">${Math.round(margin)}%</div></div>`;
  };
  wrap.addEventListener('input',recompute);wrap.addEventListener('change',recompute);recompute();
  return c;
}

function renderResult(d){
  const L=d.listing,src=d.source||{},mk=d.market,w=frame();
  const mkName={uk:'UK market',us:'US market',both:'UK + US market'}[mk];

  const viaTxt=(v=>({photo:'from your photo',pasted:'from pasted content',none:'',sample:'sample listing'}[v]||(String(v).includes('photo')?'from link + photo':'from the product link')))(src.via||'');
  const engBadge=d.premium?'<span class="engpill prem">✦ Premium (Claude)</span>':'<span class="engpill">Free model</span>';
  const bar=document.createElement('div');bar.className='card rbar';
  bar.innerHTML=`<div class="t">Your Etsy listing<small>${esc(mkName)}${viaTxt?' · '+esc(viaTxt):''} ${engBadge}</small></div>`;
  const acts=document.createElement('div');acts.className='acts';
  const everything=()=>listingText(L,mk);
  const cA=Object.assign(document.createElement('button'),{className:'btn primary sm',textContent:'Copy everything'});
  cA.onclick=async()=>{if(await copyText(everything())){cA.textContent='Copied ✓';setTimeout(()=>cA.textContent='Copy everything',1400);}};
  const jB=document.createElement('button');jB.className='btn ghost sm';jB.appendChild(icon('dl'));jB.insertAdjacentText('beforeend',' JSON');
  jB.onclick=()=>dl('etsy-listing.json','application/json',JSON.stringify(L,null,2));
  acts.append(cA,jB);bar.appendChild(acts);w.appendChild(bar);

  const grid=document.createElement('div');grid.className='rgrid';
  const stk=document.createElement('div');stk.className='stack';
  const aside=document.createElement('aside');aside.className='raside';
  grid.append(stk,aside);w.appendChild(grid);

  // premium fallback note (e.g. Premium requested but no Claude key)
  if(d.note){const n=document.createElement('div');n.className='banner caution';
    n.innerHTML=`<span class="ic">i</span><div><div class="bh">Heads up</div><div>${esc(d.note)}</div></div>`;stk.appendChild(n);}

  // Listing Quality Score — the headline metric (transparent, computed live)
  {const sc=computeScore(L,src,mk);
   const c=document.createElement('section');c.className='card pad';
   const h=document.createElement('div');h.className='chead';
   h.innerHTML=`<span class="lbl">Listing Quality Score</span><span class="counter">${sc.grade}</span>`;
   c.appendChild(h);
   const body=document.createElement('div');body.className='score';
   const sccol=sc.total>=80?'var(--ok)':sc.total>=55?'var(--warn,#DBAA46)':'var(--err,#c0392b)';
   const ring=document.createElement('div');ring.className='ring';ring.style.setProperty('--v',sc.total);ring.style.setProperty('--sc',sccol);
   ring.innerHTML=`<div class="num">${sc.total}<small>/ 100</small></div>`;
   const bars=document.createElement('div');bars.className='scbars';
   bars.innerHTML=sc.rows.map(r=>{const pct=Math.round(r.pts/r.max*100);const cls=pct>=80?'good':pct>=50?'mid':'low';
     return `<div class="scrow"><span>${esc(r.label)}</span><span class="sbar"><i class="${cls}" style="width:${pct}%"></i></span><span class="pt">${r.pts}/${r.max}</span></div>`;}).join('');
   body.append(ring,bars);c.appendChild(body);
   if(sc.fixes.length){const f=document.createElement('div');f.className='scfix';
     f.innerHTML=`<div class="sugt">Quick wins to raise your score</div><ul class="tips">${sc.fixes.map(x=>`<li>${esc(x)}</li>`).join('')}</ul>`;c.appendChild(f);}
   stk.appendChild(c);}

  // AI Suggestions sidebar
  {const S=L.suggestions||{};const sug=document.createElement('section');sug.className='card pad sug';
   sug.innerHTML='<div class="chead"><span class="lbl">✦ AI Suggestions</span></div>';
   const sec=(t,el)=>{const d=document.createElement('div');d.className='sugsec';d.innerHTML=`<div class="sugt">${t}</div>`;d.appendChild(el);sug.appendChild(d);};
   const words=(L.title||'').trim().split(/\s+/).filter(Boolean).length;
   const tt=[(words>14?`Consider using 14 words or fewer — your title has ${words} words.`:`Good title length — ${words} words (Etsy likes 14 or fewer).`)].concat(Array.isArray(S.title_tips)?S.title_tips:[]);
   {const ul=document.createElement('ul');ul.className='tips ttips';ul.innerHTML=tt.map(x=>`<li>${esc(x)}</li>`).join('');sec('Tips to improve your title',ul);}
   if(S.alt_titles&&S.alt_titles.length){const box=document.createElement('div');
     S.alt_titles.forEach(t=>{const row=document.createElement('div');row.className='sugrow';row.textContent=t;row.title='Click to copy';
       row.onclick=async()=>{if(await copyText(t)){row.classList.add('flash');setTimeout(()=>row.classList.remove('flash'),700);}};box.appendChild(row);});sec('Alternative titles',box);}
   if(S.extra_keywords&&S.extra_keywords.length){const ch=document.createElement('div');ch.className='chips';
     S.extra_keywords.forEach(k=>{const c=document.createElement('span');c.className='chip';c.textContent=k;c.title='Click to copy';
       c.onclick=async()=>{if(await copyText(k)){c.classList.add('flash');setTimeout(()=>c.classList.remove('flash'),700);}};ch.appendChild(c);});sec('Extra keyword ideas',ch);}
   if(S.tips&&S.tips.length){const ul=document.createElement('ul');ul.className='tips';ul.innerHTML=S.tips.map(x=>`<li>${esc(x)}</li>`).join('');sec('Tips to sell more',ul);}
   aside.appendChild(sug);}

  if(d.warnings&&d.warnings.length){const b=document.createElement('div');b.className='banner caution';
    b.innerHTML=`<span class="ic">!</span><div><div class="bh">A few Etsy limits to tidy up</div><ul>${d.warnings.map(x=>`<li>${esc(x)}</li>`).join('')}</ul></div>`;stk.appendChild(b);}

  if(src.images&&src.images.length){const t=document.createElement('div');t.className='card pad';
    const strip=document.createElement('div');strip.className='thumbs';
    src.images.forEach(u=>{const im=new Image();im.src=u;im.loading='lazy';im.referrerPolicy='no-referrer';im.onerror=()=>im.remove();strip.appendChild(im);});
    t.innerHTML='<div class="lbl" style="margin-bottom:12px">Detected images</div>';t.appendChild(strip);stk.appendChild(t);}

  // compliance — shown FIRST (account safety)
  {const pc=L.policy_check||{};const st=pc.status||'caution';const notes=pc.reasons||[];
   const head={ok:'Looks Etsy-ready',caution:'Check these before you publish',likely_violation:'This may break Etsy’s rules — read carefully'}[st];
   const ico={ok:'✓',caution:'!',likely_violation:'✕'}[st];
   const b=document.createElement('div');b.className='banner '+st;b.setAttribute('role','status');
   const fl=(pc.flags||[]).filter(f=>f&&f!=='none');
   b.innerHTML=`<span class="ic">${ico}</span><div style="flex:1"><div class="bh">${head}</div>`+
     (notes.length?`<ul>${notes.map(n=>`<li>${esc(n)}</li>`).join('')}</ul>`:'<div class="muted">Nothing flagged — still double-check for any brand/trademark words and that your “How it’s made” choice is truthful.</div>')+
     (fl.length?`<div style="margin-top:8px;font-size:12px;color:var(--muted)">Flags: ${fl.map(pretty).join(', ')}</div>`:'')+`</div>`;
   stk.appendChild(b);}

  // title
  {const tl=(L.title||''),over=tl.length>140;const b=document.createElement('div');b.className='titletext';b.textContent=tl;
   const ctr=Object.assign(document.createElement('span'),{className:'counter'+(over?' over':''),textContent:`${tl.length} / 140`});
   stk.appendChild(card('Title',ctr,b,()=>tl));}

  // tags
  {const tags=Array.isArray(L.tags)?L.tags:[];const body=document.createElement('div');body.className='chips';
   tags.forEach(tag=>{const over=tag.length>20;const chip=document.createElement('span');chip.className='chip'+(over?' over':'');
     chip.innerHTML=esc(tag)+(over?` <b>${tag.length}</b>`:'')+`<span class="cg">${icon('copy').outerHTML}</span>`;
     chip.title='Click to copy';chip.onclick=async()=>{if(await copyText(tag)){chip.classList.add('flash');setTimeout(()=>chip.classList.remove('flash'),700);}};body.appendChild(chip);});
   const meta=Object.assign(document.createElement('span'),{className:'counter'+(tags.length===13?'':' over'),textContent:`${tags.length} / 13`});
   stk.appendChild(card('Tags',meta,body,()=>tags.join(', ')));}

  // description
  {const b=document.createElement('div');b.className='desc';b.textContent=L.description||'';
   stk.appendChild(card('Description',`${(L.description||'').length} chars`,b,()=>L.description||''));}

  // materials
  if(Array.isArray(L.materials)&&L.materials.length){const body=document.createElement('div');body.className='chips';
   L.materials.forEach(m=>{const c=document.createElement('span');c.className='chip static';c.textContent=m;body.appendChild(c);});
   stk.appendChild(card('Materials','',body,()=>L.materials.join(', ')));}

  // attributes
  {const a=L.attributes||{};const body=document.createElement('div');
   const bread=(L.taxonomy_path||'').split('>').map(s=>s.trim()).filter(Boolean);
   const bh=bread.length?`<div class="attr" style="margin-bottom:18px"><div class="k">Category</div><div class="bread">${bread.map((x,i)=>`${i?'<span class="sep">›</span>':''}<span class="crumb">${esc(x)}</span>`).join('')}</div></div>`:'';
   const pairs=[['Who made it',WHO[a.who_made]||pretty(a.who_made)],['What is it',WHAT[a.what_is_it]||pretty(a.what_is_it)],['When made',whenL(a.when_made)],
     ['Primary colour',a.primary_colour?swatch(a.primary_colour)+pretty(a.primary_colour):'—'],['Secondary colour',a.secondary_colour&&a.secondary_colour!=='none'?swatch(a.secondary_colour)+pretty(a.secondary_colour):'—'],
     ['Occasion',a.occasion&&a.occasion!=='none'?pretty(a.occasion):'—'],['Holiday',a.holiday&&a.holiday!=='none'?pretty(a.holiday):'—'],['Style',a.style&&a.style!=='none'?pretty(a.style):'—']];
   const ca=Array.isArray(L.category_attributes)?L.category_attributes:[];
   const caHtml=ca.length?`<div style="margin-top:20px;padding-top:18px;border-top:1px solid var(--border)"><div class="k" style="margin-bottom:12px">Etsy asks these for this category</div><div class="attrs">${ca.map(x=>`<div class="attr"><div class="k">${esc(x.name)}</div><div class="v">${esc(x.value)}</div></div>`).join('')}</div></div>`:'';
   body.innerHTML=bh+`<div class="attrs">${pairs.map(([k,v])=>`<div class="attr"><div class="k">${k}</div><div class="v">${v}</div></div>`).join('')}</div>`+caHtml;
   const txt=()=>`Category: ${L.taxonomy_path}\n`+pairs.map(([k,v])=>`${k}: ${String(v).replace(/<[^>]+>/g,'')}`).join('\n')+(ca.length?`\n\nCategory attributes:\n`+ca.map(x=>`${x.name}: ${x.value}`).join('\n'):'');
   stk.appendChild(card('Attributes','',body,txt));}

  // price
  {const p=L.pricing||{},showG=mk!=='us',showU=mk!=='uk';
   const pc=document.createElement('section');pc.className='card pad';
   const parts=[];
   if(showG)parts.push(`<div><div class="price-cur">GBP</div><div class="price-big tnum">£${fmt(p.retail_gbp)}</div></div>`);
   if(showU)parts.push(`<div><div class="price-cur">USD</div><div class="price-big tnum">$${fmt(p.retail_usd)}</div></div>`);
   pc.innerHTML=`<div class="chead"><span class="lbl">Suggested price</span></div><div class="prices">${parts.join('')}</div>
     <div class="rationale">${esc(p.margin_note||'')}${p.margin_pct?` <b style="color:var(--text)">· ~${Math.round(p.margin_pct)}% net margin</b>`:''}</div>`;
   $('.chead',pc).appendChild(copyBtn(()=>[showG?`£${fmt(p.retail_gbp)}`:'',showU?`$${fmt(p.retail_usd)}`:''].filter(Boolean).join('  ')));
   stk.appendChild(pc);}

  // profit & Etsy fee calculator (interactive — real Etsy fees)
  {stk.appendChild(calcCard(L,mk));}

  // variations
  {const rows=Array.isArray(L.variations)?L.variations:[];const c=document.createElement('section');c.className='card pad';
   const h=document.createElement('div');h.className='chead';h.innerHTML=`<span class="lbl">Variations${rows.length?`<span class="n">${rows.length} rows</span>`:''}</span>`;
   const r=document.createElement('div');r.style.cssText='display:flex;gap:8px;align-items:center';
   if(rows.length){const csv=document.createElement('button');csv.className='btn ghost sm';csv.appendChild(icon('dl'));csv.insertAdjacentText('beforeend',' CSV');
     csv.onclick=()=>dl('variations.csv','text/csv',varCSV(rows));r.append(csv,copyBtn(()=>varTSV(rows)));}
   h.appendChild(r);c.appendChild(h);
   if(rows.length){const tw=document.createElement('div');tw.className='tablewrap';
     tw.innerHTML=`<div class="tscroll"><table><thead><tr><th>SKU</th><th>Option</th><th class="tnum">£ GBP</th><th class="tnum">$ USD</th><th class="tnum">Qty</th></tr></thead>
       <tbody>${rows.map(v=>`<tr><td class="sku">${esc(v.sku)}</td><td>${esc(v.option_label)}</td><td class="tnum">${fmt(v.price_gbp)}</td><td class="tnum">${fmt(v.price_usd)}</td><td class="tnum">${esc(v.default_quantity)}</td></tr>`).join('')}</tbody></table></div>`;
     c.appendChild(tw);}
   else{const p=document.createElement('div');p.className='muted';p.textContent='No variations detected — this is a single-option listing.';c.appendChild(p);}
   stk.appendChild(c);}

  // etsy competition (AI estimate + one-click link to the REAL Etsy search)
  {const cp=L.competition||{};
   const q=String(L.search_query||(Array.isArray(L.tags)&&L.tags[0])||(L.title||'').split(/\s+/).slice(0,4).join(' ')||'').trim();
   const etsyUrl='https://www.etsy.com/search?q='+encodeURIComponent(q);
   const lvlCol={low:'#2f8f4e',medium:'#c98a1b',high:'#d2691e',saturated:'#c0392b'};
   const demCol={high:'#2f8f4e',medium:'#c98a1b',low:'#b04632'};
   const lvl=String(cp.level||'').toLowerCase(),dem=String(cp.demand||'').toLowerCase();
   const c=document.createElement('section');c.className='card pad';
   const h=document.createElement('div');h.className='chead';
   h.innerHTML='<span class="lbl">Competition on Etsy <span class="muted" style="font-weight:500;font-size:11px">· AI estimate</span></span>';
   const link=document.createElement('a');link.href=etsyUrl;link.target='_blank';link.rel='noopener noreferrer';link.className='btn primary sm';link.textContent='See live on Etsy ↗';
   h.appendChild(link);c.appendChild(h);
   const stats=document.createElement('div');stats.className='attrs';stats.style.marginTop='6px';
   stats.innerHTML=
     `<div class="attr"><div class="k">Competition</div><div class="v" style="color:${lvlCol[lvl]||'var(--text)'};font-weight:700">${esc(pretty(lvl)||'—')}</div></div>`+
     `<div class="attr"><div class="k">Buyer demand</div><div class="v" style="color:${demCol[dem]||'var(--text)'};font-weight:700">${esc(pretty(dem)||'—')}</div></div>`+
     `<div class="attr"><div class="k">Typical price</div><div class="v">${esc(cp.price_range||'—')}</div></div>`;
   c.appendChild(stats);
   if(cp.estimate){const e=document.createElement('div');e.className='rationale';e.style.marginTop='14px';
     e.innerHTML='≈ '+esc(cp.estimate)+' for “'+esc(q)+'”. Click <b>See live on Etsy</b> for today’s real count &amp; bestsellers.';c.appendChild(e);}
   const so=Array.isArray(cp.stand_out)?cp.stand_out:[];
   if(so.length){const t=document.createElement('div');t.className='sugt';t.style.margin='18px 0 8px';t.textContent='How to stand out';c.appendChild(t);
     const ul=document.createElement('ul');ul.className='tips';ul.innerHTML=so.map(x=>`<li>${esc(x)}</li>`).join('');c.appendChild(ul);}
   stk.appendChild(c);}

  // footer
  {const f=document.createElement('div');f.style.cssText='display:flex;gap:9px;justify-content:flex-end;flex-wrap:wrap;margin-top:4px';
   const c2=Object.assign(document.createElement('button'),{className:'btn ghost sm',textContent:'Copy everything'});
   c2.onclick=async()=>{if(await copyText(everything())){c2.textContent='Copied ✓';setTimeout(()=>c2.textContent='Copy everything',1400);}};
   const j2=document.createElement('button');j2.className='btn ghost sm';j2.appendChild(icon('dl'));j2.insertAdjacentText('beforeend',' JSON');
   j2.onclick=()=>dl('etsy-listing.json','application/json',JSON.stringify(L,null,2));
   f.append(c2,j2);stk.appendChild(f);}

  window.scrollTo({top:0,behavior:'smooth'});
}

/* exporters */
function listingText(L,mk){const p=L.pricing||{},a=L.attributes||{};
  const price=[mk!=='us'?`£${fmt(p.retail_gbp)}`:'',mk!=='uk'?`$${fmt(p.retail_usd)}`:''].filter(Boolean).join('  ');
  const vars=(L.variations||[]).map(r=>`  ${r.sku} | ${r.option_label} | £${fmt(r.price_gbp)} / $${fmt(r.price_usd)} | qty ${r.default_quantity}`).join('\n');
  const cp=L.competition||{};
  const comp=cp.level?`\n\nETSY COMPETITION (estimate)\nSearch: ${L.search_query||''}\nCompetition: ${cp.level} · Demand: ${cp.demand||'—'} · Typical price: ${cp.price_range||'—'}\n${cp.estimate?'≈ '+cp.estimate+'\n':''}${Array.isArray(cp.stand_out)&&cp.stand_out.length?'How to stand out:\n'+cp.stand_out.map(s=>'  - '+s).join('\n'):''}`:'';
  return `TITLE\n${L.title}\n\nTAGS\n${(L.tags||[]).join(', ')}\n\nDESCRIPTION\n${L.description}\n\nMATERIALS\n${(L.materials||[]).join(', ')}\n\nCATEGORY\n${L.taxonomy_path}\n\nATTRIBUTES\nWho made it: ${WHO[a.who_made]||a.who_made}\nWhat is it: ${WHAT[a.what_is_it]||a.what_is_it}\nWhen made: ${whenL(a.when_made)}\nPrimary colour: ${a.primary_colour}\nSecondary colour: ${a.secondary_colour}\nOccasion: ${a.occasion}\nHoliday: ${a.holiday}\nStyle: ${a.style}\n\nPRICE\n${price}  (${p.margin_note||''})\n\nVARIATIONS\n${vars||'  (single option)'}${comp}\n`;}
const cell=v=>{v=String(v??'');return /[",\n]/.test(v)?'"'+v.replace(/"/g,'""')+'"':v;};
const varCSV=r=>[['SKU','Option','Price GBP','Price USD','Quantity'].join(','),...r.map(x=>[x.sku,x.option_label,fmt(x.price_gbp),fmt(x.price_usd),x.default_quantity].map(cell).join(','))].join('\r\n');
const varTSV=r=>[['SKU','Option','Price GBP','Price USD','Quantity'].join('\t'),...r.map(x=>[x.sku,x.option_label,fmt(x.price_gbp),fmt(x.price_usd),x.default_quantity].join('\t'))].join('\n');

/* sample (offline demo) */
const SW=c=>`data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='60' height='60'%3E%3Crect width='60' height='60' rx='12' fill='%23${c}'/%3E%3C/svg%3E`;
const SAMPLE={market:'both',warnings:[],source:{via:'sample',images:[SW('C15A34'),SW('3F8A54'),SW('3B6FB0'),SW('E48FB0')]},
  listing:{title:'Insulated Stainless Steel Tumbler with Handle & Straw, 30oz Travel Cup, Gift for Coffee Lovers',
    tags:['travel tumbler','insulated cup','coffee gift','tumbler w straw','30oz tumbler','desk cup','gift for her','iced coffee cup','stainless mug','reusable cup','office gift','water tumbler','handled tumbler'],
    description:'Insulated stainless steel tumbler with a handle and reusable straw — a 30oz travel cup that keeps drinks cold for 24 hours and hot for 12.\n\nDouble-wall vacuum insulation, a leak-resistant lid and a cup-holder-friendly base make it perfect for the commute, the desk or the gym. Made from durable 18/8 stainless steel with a BPA-free lid.\n\nAvailable in four colours and two sizes — a thoughtful, practical gift for coffee and tea lovers, teachers, or anyone always on the go.\n\nCare: hand wash recommended. Not microwave safe.',
    materials:['stainless steel','bpa-free plastic','silicone'],taxonomy_path:'Home & Living > Kitchen & Dining > Drink & Barware > Tumblers & Water Glasses',
    attributes:{who_made:'someone_else',what_is_it:'finished_product',when_made:'2020_2026',primary_colour:'black',secondary_colour:'green',occasion:'everyday',holiday:'none',style:'modern'},
    pricing:{source_price:6.20,source_currency:'USD',retail_gbp:22.99,retail_usd:27.99,assumed_fx_gbp_usd:1.27,margin_pct:41,margin_note:'Priced ~4.5x over the $6.20 source cost to net ~41% after Etsy fees; sits mid-range for insulated tumblers.'},
    variation_axes:['size','colour'],
    variations:[{sku:'TUMB-30-BLK',option_label:'30oz / Black',price_gbp:22.99,price_usd:27.99,default_quantity:25},{sku:'TUMB-30-SAG',option_label:'30oz / Sage Green',price_gbp:22.99,price_usd:27.99,default_quantity:25},{sku:'TUMB-30-NVY',option_label:'30oz / Navy',price_gbp:22.99,price_usd:27.99,default_quantity:25},{sku:'TUMB-40-BLK',option_label:'40oz / Black',price_gbp:25.99,price_usd:30.99,default_quantity:25},{sku:'TUMB-40-SAG',option_label:'40oz / Sage Green',price_gbp:25.99,price_usd:30.99,default_quantity:25},{sku:'TUMB-40-NVY',option_label:'40oz / Navy',price_gbp:25.99,price_usd:30.99,default_quantity:25}],
    policy_check:{status:'caution',reasons:['This looks like a mass-produced / dropshipped item. Etsy expects the seller to design or make what they sell, so resold generic goods can be removed.','No trademarked brand or character detected — that part is fine.'],flags:['dropshipped','mass_produced']},
    suggestions:{alt_titles:['30oz Insulated Tumbler with Straw & Handle, Travel Coffee Cup, Personalised Gift for Her','Stainless Steel Travel Tumbler 30oz, Leakproof Iced Coffee Cup, Office & Gym Water Bottle'],title_tips:['Front-load "insulated tumbler" — the phrase most buyers search.','Add an occasion word like "gift" early, not only at the end.','You repeat cup/tumbler — swap one for "flask" to widen reach.'],extra_keywords:['tumbler with lid','hot cold flask','commuter mug','teacher gift cup','bridesmaid tumbler','reusable straw cup','gym flask 30oz'],tips:['Add a lifestyle photo (in a car cup holder or on a desk) as image 2 — it lifts click-through.','Offer a small bundle discount on the 40oz to raise average order value.','List during evenings and weekends when Etsy buyer traffic peaks.','Rotate 2–3 extra keywords into your tags each month to test what ranks.']},category_attributes:[{name:'Capacity',value:'30oz / 40oz'},{name:'Material',value:'Stainless steel'},{name:'Care instructions',value:'Hand wash only'},{name:'Dishwasher safe',value:'No'},{name:'Lid type',value:'Leak-resistant with straw'}],
    search_query:'insulated tumbler with straw',
    competition:{level:'saturated',demand:'high',estimate:'tens of thousands of similar listings',price_range:'£12–£30 (most around £20)',stand_out:['Offer free name/initial personalisation — many mass sellers do not, and it lifts perceived value.','Lead with a lifestyle photo in a car cup holder or on a desk — most competitors show plain product shots.','Target an under-served phrase like "teacher tumbler" or "commuter cup" in your first tag instead of the crowded "tumbler".','Bundle a straw-cleaning brush or spare straw to stand out on value.']}}};

/* onboarding */
function renderOnboard(){const w=frame();
  w.innerHTML=`<div class="hero">
    <span class="kicker">✦ AI-powered · Etsy-compliant</span>
    <h1>Turn any product link into a <em>ready-to-sell</em> Etsy listing.</h1>
    <p>Paste a URL and get a keyword-rich title, 13 tags, a polished description, attributes, pricing and every variation — copy-ready for the UK &amp; US.</p>
    <div class="steps">
      <div class="step"><div class="n">1</div><b>Paste</b><span>Drop in a product URL, or paste the page text.</span></div>
      <div class="step"><div class="n">2</div><b>Generate</b><span>We read the product and write the full listing.</span></div>
      <div class="step"><div class="n">3</div><b>Copy &amp; sell</b><span>Copy each field, or export JSON / CSV.</span></div>
    </div>
    <div class="trust"><span class="badge">🔒 Runs locally</span><span class="badge">✓ Policy check</span><span class="badge">£ + $ pricing</span><span class="badge">All variations</span></div>
    <div style="margin-top:26px"><button class="btn ghost" id="sampleBtn">See a sample listing — no key needed</button></div>
  </div>`;
  $('#sampleBtn').onclick=()=>renderResult(SAMPLE);
}

/* freemium */
const PLANS=[
  {name:'Free',price:'$0',per:'',feat:false,items:['5 listings total','All fields + variations','Trademark & policy check'],cta:'Current plan'},
  {name:'Starter',price:'$6',per:'/mo',feat:false,items:['100 listings / month','Everything in Free','Priority generation'],cta:'Subscribe'},
  {name:'Pro',price:'$12',per:'/mo',feat:true,items:['Unlimited listings','Everything in Starter','Bulk & CSV export'],cta:'Subscribe'}
];
let accessCode=localStorage.getItem('els-code')||'';
let usage={used:0,limit:5,unlimited:false,remaining:5};
function renderUsage(){const el=$('#usage');
  const txt=usage.unlimited?'Unlimited ✦':`${usage.remaining} free left`;
  if(el){el.textContent=txt;el.className='badge'+(!usage.unlimited&&(usage.remaining||0)<=1?' warnpill':'');}
  const sb=$('#sbUsage');if(sb)sb.textContent=usage.unlimited?'Unlimited ✦':`${usage.remaining} of ${usage.limit||5} free left`;}
async function refreshUsage(){try{const r=await fetch('api/usage.php?code='+encodeURIComponent(accessCode));const u=await r.json();if(u&&u.used!==undefined){usage=u;renderUsage();}}catch(e){}}
function renderPaywall(hard){
  const w=frame();
  const head=hard?`You’ve used your ${usage.limit||5} free listings`:'Plans & pricing';
  const sub=hard?'Upgrade to keep generating unlimited, Etsy-ready listings.':'Simple monthly pricing — cancel anytime.';
  w.innerHTML=`<div class="card"><div class="pay">
    <span class="kicker">✦ Etsy Listing Studio</span>
    <h1 style="font-family:var(--serif);font-weight:600;font-size:30px;margin:14px 0 8px">${esc(head)}</h1>
    <p class="muted" style="max-width:48ch;margin:0 auto">${esc(sub)}</p>
    <div class="plans">${PLANS.map(p=>`<div class="plan${p.feat?' feat':''}"><h3>${p.name}</h3><div class="amt">${p.price}<small>${p.per}</small></div><ul>${p.items.map(i=>`<li>${esc(i)}</li>`).join('')}</ul><button class="btn ${p.feat?'primary':'ghost'}" data-plan="${p.name}">${p.cta}</button></div>`).join('')}</div>
    <div class="codebox"><input id="codeInp" placeholder="Have an access code? Paste it here" value="${esc(accessCode)}"><button class="btn ghost" id="codeBtn">Unlock</button></div>
    <div class="hint" id="codeMsg" style="text-align:center;margin-top:10px"></div>
  </div></div>`;
  $$('.plan button[data-plan]',w).forEach(b=>b.onclick=()=>{
    if(b.dataset.plan==='Free'){showView('dashboard');return;}
    const m=$('#codeMsg',w);m.style.color='var(--muted)';m.textContent='💳 Online payment is coming soon. For now, get an access code and paste it below to unlock instantly.';});
  $('#codeBtn',w).onclick=async()=>{
    accessCode=$('#codeInp',w).value.trim();localStorage.setItem('els-code',accessCode);await refreshUsage();const m=$('#codeMsg',w);
    if(usage.unlimited){m.style.color='var(--ok-ink)';m.textContent='✓ Unlocked — unlimited listings enabled.';setTimeout(()=>showView('dashboard'),900);}
    else{m.style.color='var(--err-ink)';m.textContent='That code didn’t work — check it and try again.';}};
}
/* ---- routing + sidebar nav ---- */
function showView(v){
  document.body.classList.remove('showing-landing');
  document.querySelectorAll('.sb-item').forEach(b=>b.classList.toggle('active',b.dataset.view===v));
  closeSidebar();
  const ic=$('#inputCard');
  if(v==='generator'){if(ic)ic.style.display='';$('#state').innerHTML='';window.scrollTo({top:0,behavior:'smooth'});}
  else{if(ic)ic.style.display='none';}
  if(v==='dashboard')renderDashboard();
  else if(v==='admin')renderAdmin();
  else if(v==='keywords')renderKeywords();
  else if(v==='research')renderResearch();
  else if(v==='tools')renderTools();
  else if(v==='plans')renderPaywall(false);
}
function openSidebar(){const s=$('#sidebar');if(s)s.classList.add('open');const b=$('#sbBackdrop');if(b)b.classList.add('show');}
function closeSidebar(){const s=$('#sidebar');if(s)s.classList.remove('open');const b=$('#sbBackdrop');if(b)b.classList.remove('show');}
$$('.sb-item').forEach(b=>b.onclick=()=>showView(b.dataset.view));
const _hb=$('#hambBtn');if(_hb)_hb.onclick=openSidebar;
const _bd=$('#sbBackdrop');if(_bd)_bd.onclick=closeSidebar;
const _sbr=$('#sbBrand');if(_sbr)_sbr.onclick=()=>showView('dashboard');
$('#homeBtn').onclick=()=>{isAuthed()?showView('dashboard'):renderLanding();};
$('#signoutBtn').onclick=signOut;
const _ns=$('#navStart');if(_ns)_ns.onclick=()=>renderSignup(0,{});
const _nl=$('#navLogin');if(_nl)_nl.onclick=()=>renderLogin();

/* ---- account / auth (real server accounts via api/auth.php) ---- */
const getToken=()=>localStorage.getItem('els-token')||'';
function getAccount(){try{return JSON.parse(localStorage.getItem('els-account')||'null');}catch(e){return null;}}
function setAccount(a){localStorage.setItem('els-account',JSON.stringify(a));}
function isAuthed(){return localStorage.getItem('els-session')==='1'&&!!getToken()&&!!getAccount();}
function mountAccount(){const a=getAccount();if(!a)return;const ini=((a.name||a.email||'U').trim()[0]||'U').toUpperCase();
  const set=(id,v)=>{const el=$(id);if(el)el.textContent=v;};
  set('#sbName',a.name||(a.email||'').split('@')[0]);set('#sbEmail',a.email||'');set('#sbAva',ini);}
async function authApi(action,payload){
  try{const r=await fetch('api/auth.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.assign({action},payload))});return await r.json();}
  catch(e){return {error:'Network error — make sure the server is running.'};}
}
function applyAuth(resp){
  if(!resp||!resp.token||!resp.user)return;
  const u=resp.user;
  localStorage.setItem('els-token',resp.token);localStorage.setItem('els-session','1');
  setAccount({name:u.name,email:u.email,is_admin:!!u.is_admin,plan:u.plan});
  usage={used:u.used||0,limit:u.limit||5,unlimited:!!u.unlimited,remaining:u.remaining};renderUsage();
  document.body.classList.remove('noauth','showing-landing');
  document.body.classList.toggle('is-admin',!!u.is_admin);
  mountAccount();
  showView(u.is_admin?'admin':'dashboard');
}
function signOut(){localStorage.removeItem('els-session');localStorage.removeItem('els-token');
  document.body.classList.add('noauth');document.body.classList.remove('is-admin');closeSidebar();renderLanding();}

/* ---- landing (full-width marketing page) ---- */
function fIcon(n){const p={spark:'<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M18 6l-2.5 2.5M8.5 15.5 6 18"/>',
  search:'<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',gauge:'<path d="M12 13l4-4"/><path d="M4 20a8 8 0 1 1 16 0"/>',
  chart:'<path d="M3 17l6-6 4 4 7-7"/><path d="M17 8h4v4"/>',calc:'<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h8M8 12h.01M12 12h.01M16 12h.01M8 16h.01M12 16h.01M16 16h4"/>',
  shield:'<path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3Z"/><path d="m9 12 2 2 4-4"/>',photo:'<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="m21 17-5-5-4 4"/>'}[n]||'';
  return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">${p}</svg>`;}
function renderLanding(){
  document.body.classList.add('showing-landing');
  const el=$('#landing');
  const feats=[
    ['photo','Link, photo or paste → listing','Drop any product URL, upload a photo, or paste text. AI writes a full Etsy-ready listing in seconds — from any supplier or marketplace.'],
    ['search','Keyword Research','Find the exact long-tail phrases buyers type, with demand, competition & opportunity scores — save keyword lists and verify live on Etsy.'],
    ['gauge','Listing Quality Score','A transparent 0–100 score across title, tags, description, attributes, photos & policy — with quick wins to rank higher.'],
    ['chart','Etsy Competition','See how saturated a niche is, typical price range and how to stand out — plus a one-click link to the real Etsy results.'],
    ['calc','Profit & Fee Calculator','Know your true margin. Real Etsy fees (listing, transaction, payment processing, ads) → net profit and margin, instantly.'],
    ['shield','Trademark & Policy Safety','Every listing is scanned for brand/trademark risks and Etsy policy issues — protecting your shop from takedowns and suspension.'],
  ];
  el.innerHTML=`
  <section class="lhero"><div class="lin lhero-in">
    <div>
      <span class="lkick">✦ AI-powered · Etsy-compliant</span>
      <h1>The all-in-one <em>Etsy toolkit</em> — listings, keywords &amp; research.</h1>
      <p class="lead">Turn any product link or photo into a ready-to-sell listing — keyword-rich title, 13 tags, description, pricing, a quality score and a full compliance check. Plus keyword research and market insights.</p>
      <div class="lcta">
        <button class="btn primary xl" data-cta="start">Get started free →</button>
        <button class="btn ghost xl" data-cta="sample">See a sample</button>
      </div>
      <div class="ltrust"><span><b class="tick">✓</b> 5 free listings</span><span><b class="tick">✓</b> No card needed</span><span><b class="tick">✓</b> UK &amp; US ready</span></div>
    </div>
    <div class="mock">
      <div class="mock-bar"><i></i><i></i><i></i><div class="url">etsy-listing-studio · your listing</div></div>
      <div class="mock-body">
        <div class="mk-lbl">Title</div>
        <div class="mk-title">Personalised Name Necklace, Custom Gold Nameplate Pendant — Dainty Gift for Her</div>
        <div class="mk-lbl" style="margin-top:15px">13 Tags</div>
        <div class="mk-tags"><span>name necklace</span><span>custom necklace</span><span>gold nameplate</span><span>gift for her</span><span>personalised</span><span>bridesmaid gift</span><span>dainty necklace</span></div>
        <div class="mk-row">
          <div class="mk-ring"><b>92</b></div>
          <div><div class="mk-lbl" style="margin:0 0 3px">Quality score</div><div style="font-weight:700;font-size:12.5px;color:var(--ok-ink)">Excellent · Etsy-ready</div></div>
          <div class="mk-price"><div class="p">£28.99</div><div class="s">/ $36.50 · 41% margin</div></div>
        </div>
      </div>
    </div>
  </div></section>

  <div class="lstrip"><div class="lstrip-in">
    <div><b>Any</b><span>supplier or marketplace link</span></div>
    <div><b>13</b><span>optimised Etsy tags</span></div>
    <div><b>£+$</b><span>dual-market pricing</span></div>
    <div><b>0–100</b><span>listing quality score</span></div>
    <div><b>5 free</b><span>listings to start</span></div>
  </div></div>

  <section class="lsec"><div class="lin">
    <div class="lsec-h"><div class="lkick2">Everything you need</div><h2>One toolkit to research, write &amp; rank</h2>
      <p>Every tool an Etsy seller needs to go from a product idea to a live, optimised, policy-safe listing — clearly laid out, nothing to learn.</p></div>
    <div class="fgrid">${feats.map(f=>`<div class="fcard"><div class="fi">${fIcon(f[0])}</div><h3>${esc(f[1])}</h3><p>${esc(f[2])}</p></div>`).join('')}</div>
  </div></section>

  <section class="lsec" style="background:var(--surface-2);border-top:1px solid var(--border);border-bottom:1px solid var(--border)"><div class="lin">
    <div class="spot">
      <div class="spot-txt"><div class="lkick2">Listing generator</div><h3>Any link or photo becomes a ready-to-sell listing</h3>
        <p>Paste a product URL from AliExpress, Amazon, Alibaba or your own shop — or just upload a photo. The AI reads the product and writes everything Etsy asks for.</p>
        <ul><li>Keyword-rich title (≤140 chars) &amp; 13 tags</li><li>Description, materials, category &amp; attributes</li><li>GBP + USD pricing with real margin</li><li>All product variations, ready to copy</li></ul></div>
      <div class="mock"><div class="mock-bar"><i></i><i></i><i></i><div class="url">New listing</div></div>
        <div class="mock-body">
          <div class="mk-lbl">Product URL</div>
          <div style="border:1px solid var(--border-2);border-radius:10px;padding:10px 12px;font-size:12px;color:var(--muted)">https://www.aliexpress.com/item/…plush-toy</div>
          <div class="mk-lbl" style="margin-top:14px">Generated title</div>
          <div class="mk-title">Kawaii Blueberry Plush Toy, Soft Stuffed Fruit Pillow, Cute Cottagecore Gift</div>
          <div class="mk-tags" style="margin-top:12px"><span>plush toy</span><span>fruit pillow</span><span>kawaii plush</span><span>cottagecore</span><span>cute gift</span></div>
        </div></div>
    </div>
  </div></section>

  <section class="lsec"><div class="lin">
    <div class="spot rev">
      <div class="spot-txt"><div class="lkick2">Keyword research</div><h3>Find the phrases buyers actually type</h3>
        <p>Enter any keyword and get dozens of long-tail ideas with demand, competition and an opportunity score — then verify the real numbers on Etsy in one click.</p>
        <ul><li>Demand, competition &amp; opportunity per keyword</li><li>Save keyword lists &amp; export to CSV</li><li>“See on Etsy” link-outs to verify live</li></ul></div>
      <div class="mock"><div class="mock-bar"><i></i><i></i><i></i><div class="url">Keyword Research</div></div>
        <div class="mock-body">
          ${[['name necklace','g','86'],['custom necklace','g','78'],['bridesmaid gift','m','64'],['dainty gold necklace','g','71']].map(r=>`<div class="mk-krow"><span class="kw">${r[0]}</span><span class="mk-pill ${r[1]}">${r[1]==='g'?'low comp':'med comp'}</span><span class="mk-ob"><i style="width:${r[2]}%"></i></span><b style="font-size:11px;color:var(--muted)">${r[2]}</b></div>`).join('')}
        </div></div>
    </div>
  </div></section>

  <section class="lsec" style="background:var(--surface-2);border-top:1px solid var(--border);border-bottom:1px solid var(--border)"><div class="lin">
    <div class="lsec-h"><div class="lkick2">Dead simple</div><h2>Three steps to a live listing</h2></div>
    <div class="lsteps">
      <div class="lstep"><div class="num">1</div><h4>Paste or upload</h4><p>A product link, a photo, or pasted page text — from anywhere.</p></div>
      <div class="lstep"><div class="num">2</div><h4>Generate</h4><p>AI writes the full listing, scores it and checks it for policy risks.</p></div>
      <div class="lstep"><div class="num">3</div><h4>Copy &amp; sell</h4><p>Copy each field or export — paste straight into Etsy and publish.</p></div>
    </div>
  </div></section>

  <section class="lsec"><div class="lin"><div class="lcta-sec">
    <h2>Start free — 5 listings, no card</h2>
    <p>Create your account and generate your first Etsy-ready listing in under a minute.</p>
    <button class="btn xl" data-cta="start">Get started free →</button>
  </div></div></section>

  <footer class="lfoot"><div class="lin lfoot-in">
    <div>© Etsy Listing Studio · Not affiliated with Etsy, Inc.</div>
    <div style="display:flex;gap:18px"><a href="#" data-cta="sample" style="color:var(--muted);text-decoration:none">Sample</a><a href="#" data-cta="login" style="color:var(--muted);text-decoration:none">Log in</a><a href="#" data-cta="start" style="color:var(--accent);text-decoration:none;font-weight:650">Get started</a></div>
  </div></footer>`;
  $$('[data-cta="start"]',el).forEach(b=>b.onclick=e=>{e.preventDefault();renderSignup(0,{});});
  $$('[data-cta="login"]',el).forEach(b=>b.onclick=e=>{e.preventDefault();renderLogin();});
  $$('[data-cta="sample"]',el).forEach(b=>b.onclick=e=>{e.preventDefault();previewSample();});
  window.scrollTo({top:0,behavior:'smooth'});
}
function previewSample(){document.body.classList.remove('showing-landing');renderResult(SAMPLE);}
/* ---- signup (2 steps) ---- */
function renderSignup(step,data){
  document.body.classList.remove('showing-landing');
  step=step||0;data=data||{};const w=frame();
  if(step===0){
    w.innerHTML=`<div class="auth-wrap"><div class="auth-card">
      <div class="auth-steps"><span class="st on"></span><span class="st"></span></div>
      <h2>Create your free account</h2><div class="sub">5 free listings to start — no card needed.</div>
      <div class="gwrap" id="gsu"></div>
      <div class="authdiv"><span>or sign up with email</span></div>
      <div class="auth-field"><label>Your name</label><input id="suName" placeholder="Jane Maker" value="${esc(data.name||'')}"></div>
      <div class="auth-field"><label>Email</label><input id="suEmail" type="email" placeholder="you@email.com" value="${esc(data.email||'')}"></div>
      <div class="auth-field"><label>Password</label><input id="suPass" type="password" placeholder="Create a password"></div>
      <button class="btn primary" id="suNext" style="width:100%;justify-content:center;margin-top:6px">Continue →</button>
      <div class="auth-alt">Already have an account? <a id="toLogin">Log in</a></div>
    </div></div>`;
    $('#toLogin').onclick=()=>renderLogin();
    $('#suNext').onclick=()=>{
      const name=$('#suName').value.trim(),email=$('#suEmail').value.trim(),pass=$('#suPass').value;
      if(!name){$('#suName').focus();return;}
      if(!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)){$('#suEmail').focus();$('#suEmail').style.borderColor='var(--err,#c0392b)';return;}
      if((pass||'').length<4){$('#suPass').focus();$('#suPass').style.borderColor='var(--err,#c0392b)';return;}
      renderSignup(1,{name,email,pass});
    };
    mountGoogle('#gsu','signup_with');
    $('#suName').focus();
  } else {
    const opts=['Jewellery','Home & Living','Art & Prints','Clothing','Stickers & Digital','Wedding','Pet','Other'];
    w.innerHTML=`<div class="auth-wrap"><div class="auth-card">
      <div class="auth-steps"><span class="st on"></span><span class="st on"></span></div>
      <h2>What do you sell?</h2><div class="sub">Helps us tailor keyword &amp; niche suggestions. (Optional)</div>
      <div class="chipsel" id="sells">${opts.map(o=>`<button data-v="${esc(o)}">${esc(o)}</button>`).join('')}</div>
      <button class="btn primary" id="suDone" style="width:100%;justify-content:center;margin-top:22px">Create account &amp; start →</button>
      <div class="auth-alt"><a id="skipSell">Skip for now</a></div>
    </div></div>`;
    let sells='';
    $$('#sells button',w).forEach(b=>b.onclick=()=>{$$('#sells button',w).forEach(x=>x.classList.remove('on'));b.classList.add('on');sells=b.dataset.v;});
    const finish=async(e)=>{const btn=e&&e.currentTarget;if(btn){btn.disabled=true;btn.textContent='Creating…';}
      const r=await authApi('signup',{name:data.name,email:data.email,password:data.pass||'',sells});
      if(r.error){alert(r.error);if(btn){btn.disabled=false;btn.textContent='Create account & start →';}return;}
      applyAuth(r);};
    $('#suDone').onclick=finish;$('#skipSell').onclick=finish;
  }
}
/* ---- login (local) ---- */
function renderLogin(){
  document.body.classList.remove('showing-landing');
  const w=frame(),acc=getAccount();
  w.innerHTML=`<div class="auth-wrap"><div class="auth-card">
    <h2>Welcome back</h2><div class="sub">Log in to your Etsy Listing Studio.</div>
    <div class="gwrap" id="gli"></div>
    <div class="authdiv"><span>or log in with email</span></div>
    <div class="auth-field"><label>Email</label><input id="liEmail" type="email" placeholder="you@email.com" value="${esc(acc?acc.email:'')}"></div>
    <div class="auth-field"><label>Password</label><input id="liPass" type="password" placeholder="Your password"></div>
    <button class="btn primary" id="liGo" style="width:100%;justify-content:center;margin-top:6px">Log in →</button>
    <div class="auth-alt">New here? <a id="toSignup">Create a free account</a></div>
  </div></div>`;
  $('#toSignup').onclick=()=>renderSignup(0,{});
  $('#liGo').onclick=async(e)=>{const email=$('#liEmail').value.trim(),pass=$('#liPass').value;
    if(!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)){$('#liEmail').focus();$('#liEmail').style.borderColor='var(--err,#c0392b)';return;}
    const btn=e.currentTarget;btn.disabled=true;btn.textContent='Logging in…';
    const r=await authApi('login',{email,password:pass});
    if(r.error){alert(r.error);btn.disabled=false;btn.textContent='Log in →';return;}
    applyAuth(r);};
  mountGoogle('#gli','signin_with');
  $('#liEmail').focus();
}
/* ---- dashboard ---- */
function dashIcon(n){const p={edit:'<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
  search:'<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',trend:'<path d="M3 17l6-6 4 4 7-7"/><path d="M17 8h4v4"/>',
  tool:'<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>',
  star:'<path d="M12 2l3 6.3 6.9.9-5 4.8 1.2 6.9L12 18.6 5.9 20.9 7.1 14l-5-4.8 6.9-.9L12 2Z"/>'}[n]||'';
  return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${p}</svg>`;}
function renderDashboard(){
  const a=getAccount()||{},w=frame();
  const gen=+localStorage.getItem('els-gencount')||0,kw=kwListGet().length;
  const left=usage.unlimited?'∞':(usage.remaining!=null?usage.remaining:'—');
  const mini=[['generator','New Listing','edit'],['keywords','Keyword Research','search'],['research','Product Research','trend'],['tools','Calculators','tool']];
  w.innerHTML=`
  <div class="dash-hd"><h1>Welcome${a.name?', '+esc(String(a.name).split(' ')[0]):''} 👋</h1><p>Your Etsy command centre — analyse, research and create.</p></div>
  <section class="card pad anz">
    <div class="chead"><span class="lbl">🔎 Look up any Etsy listing or shop</span></div>
    <div class="urlrow"><div class="urlbox"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg><input id="anUrl" placeholder="Paste an Etsy listing or shop URL…" autocomplete="off"></div><button class="btn primary" id="anGo">Analyze</button></div>
    <p class="hint">Instant AI audit — score, tag-by-tag checklist, what to fix, and a better title + 13 tags + description.</p>
    <div class="disc anzdisc"><button class="disc-btn" id="anDiscBtn" aria-expanded="false"><span class="car">▸</span> Paste your listing details for a full audit</button>
      <div class="disc-panel" id="anDiscPanel" hidden><textarea id="anPaste" placeholder="Paste your Etsy listing — title, your 13 tags (comma separated) and the description — for a deep tag-by-tag audit (Etsy blocks reading it live)."></textarea></div>
    </div>
  </section>
  <div class="dash-stats">
    <div class="dash-stat"><div class="k">Free listings left</div><div class="v">${left}</div></div>
    <div class="dash-stat"><div class="k">Listings generated</div><div class="v">${gen}</div></div>
    <div class="dash-stat"><div class="k">Saved keywords</div><div class="v">${kw}</div></div>
  </div>
  <div class="dgrid2">
    <section class="card pad"><div class="chead"><span class="lbl">📈 Trending on Etsy <span class="engpill">AI</span></span><button class="btn ghost sm" id="trendMore">Research →</button></div><div id="trendBox"></div></section>
    <section class="card pad"><div class="chead"><span class="lbl">📅 Seasonal calendar</span></div><div id="calBox"></div></section>
    <section class="card pad"><div class="chead"><span class="lbl">🔑 Your keyword list</span><button class="btn ghost sm" id="kwMore">Open →</button></div><div id="kwBox"></div></section>
    <section class="card pad"><div class="chead"><span class="lbl">⚡ Quick tools</span></div><div class="dash-grid mini">${mini.map(t=>`<button class="dash-tile" data-go="${t[0]}"><div class="ti">${dashIcon(t[2])}</div><b>${esc(t[1])}</b></button>`).join('')}</div></section>
  </div>`;
  $('#anGo').onclick=analyzeRun;$('#anUrl').addEventListener('keydown',e=>{if(e.key==='Enter')analyzeRun();});
  $('#anDiscBtn').onclick=e=>{const on=e.currentTarget.getAttribute('aria-expanded')==='true';e.currentTarget.setAttribute('aria-expanded',String(!on));$('#anDiscPanel').hidden=on;if(!on)$('#anPaste').focus();};
  $('#trendMore').onclick=()=>showView('research');$('#kwMore').onclick=()=>showView('keywords');
  $$('.dash-tile',w).forEach(b=>b.onclick=()=>showView(b.dataset.go));
  dashTrending($('#trendBox'));dashCalendar($('#calBox'));dashKw($('#kwBox'));
}
/* Analyze / audit an Etsy listing (URL or pasted details) — shown in a popup */
function auditEsc(e){if(e.key==='Escape')closeAuditModal();}
function closeAuditModal(){const o=$('#auditOv');if(o)o.remove();document.removeEventListener('keydown',auditEsc);}
function openAuditModal(){
  closeAuditModal();
  const ov=document.createElement('div');ov.className='modal-ov';ov.id='auditOv';
  ov.innerHTML=`<div class="modal audit-modal"><div class="audit-hd"><span class="lbl">🔎 Listing Audit <span class="engpill">AI</span></span><button class="icobtn" id="auX" title="Close">✕</button></div><div class="audit-body" id="auBody"></div></div>`;
  document.body.appendChild(ov);
  ov.onclick=e=>{if(e.target===ov)closeAuditModal();};
  $('#auX',ov).onclick=closeAuditModal;
  document.addEventListener('keydown',auditEsc);
  return $('#auBody',ov);
}
function analyzeRun(){
  const url=$('#anUrl').value.trim(),paste=($('#anPaste')?$('#anPaste').value.trim():''),go=$('#anGo');
  if(!url&&!paste){$('#anUrl').focus();return;}
  go.disabled=true;go.textContent='Auditing…';
  const body=openAuditModal();
  body.innerHTML=`<div class="bar"><i></i></div><div class="skel" style="width:60%"></div><div class="skel" style="width:82%"></div><div class="skel" style="width:50%"></div><div class="muted" style="margin-top:12px;font-size:12.5px">Auditing the listing — title, tags, description &amp; issues… (~15–40s)</div>`;
  fetch('api/analyze.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({url,paste,token:getToken()})})
    .then(r=>r.json()).then(d=>{
      const b=$('#auBody');if(!b)return;
      if(d.error){b.innerHTML=`<div class="banner caution"><span class="ic">!</span><div>${esc(d.error)}</div></div>`;return;}
      fillAudit(b,d);
    }).catch(e=>{const b=$('#auBody');if(b)b.innerHTML=`<div class="banner caution"><span class="ic">!</span><div>Network error: ${esc(e.message)}</div></div>`;})
    .finally(()=>{go.disabled=false;go.textContent='Analyze';});
}
function fillAudit(body,d){
  const r=d.result||{},det=r.detected||{},sc=+r.seo_score||0;
  const col=sc>=70?'var(--ok)':sc>=45?'var(--warn,#DBAA46)':'var(--err,#c0392b)';
  const verdict=sc>=80?'Excellent':sc>=60?'Good':sc>=40?'Needs work':'Poor';
  const title=det.title||r.name||'',tags=Array.isArray(det.tags)?det.tags:[],desc=det.description||'';
  const tLen=title.length,tCount=tags.length;
  const single=tags.filter(t=>String(t).trim().split(/\s+/).filter(Boolean).length===1);
  const cleanMd=x=>esc(String(x).replace(/\*\*/g,''));
  const sev={error:['✕','err'],warn:['!','warn'],good:['✓','ok']};
  const issues=r.issues||[];
  const inSec=k=>issues.filter(i=>(i.section||'title')===k);
  const issList=arr=>arr.length?`<ul class="isslist">${arr.map(i=>{const s=sev[i.severity]||sev.warn;return `<li class="iss ${s[1]}"><span class="issi">${s[0]}</span><span>${cleanMd(i.text)}</span></li>`;}).join('')}</ul>`:'<div class="muted" style="font-size:12.5px">No issues here — looks good.</div>';
  const badge=arr=>{const e=arr.filter(i=>i.severity==='error').length,wn=arr.filter(i=>i.severity==='warn').length;return e?`<span class="secbadge err">${e} to fix</span>`:wn?`<span class="secbadge warn">${wn} to improve</span>`:`<span class="secbadge ok">✓ Good</span>`;};
  const chips=arr=>arr.map(t=>{const bad=String(t).trim().split(/\s+/).filter(Boolean).length===1||String(t).length>20;return `<span class="chip static${bad?' over':''}">${esc(t)}</span>`;}).join('');
  body.innerHTML=`
    ${d.from_title?'<div class="muted" style="font-size:12px;margin-bottom:14px;line-height:1.5">ℹ️ Etsy blocks live reads, so only the <b>title</b> was read. For a full audit incl. your tags/description, close this and use “Paste your listing details”.</div>':''}
    <div class="audit-top">
      <div class="ringwrap"><div class="ring" style="--v:${sc};--sc:${col}"><div class="num">${sc}<small>/100</small></div></div><div class="verdict" style="color:${col}">${verdict}</div></div>
      <div class="atiles">
        <div class="atile ${tLen>140?'bad':tLen>=70?'ok':'warn'}"><div class="k">Title</div><div class="v">${tLen}<small>/140</small></div></div>
        <div class="atile ${tCount===13?'ok':tCount?'warn':''}"><div class="k">Tags</div><div class="v">${tCount||'—'}<small>/13</small></div></div>
        <div class="atile ${single.length?'bad':(tCount?'ok':'')}"><div class="k">1-word tags</div><div class="v">${tCount?single.length:'—'}</div></div>
        <div class="atile ${desc.length?(desc.length>=250?'ok':'warn'):''}"><div class="k">Description</div><div class="v">${desc.length||'—'}<small>${desc.length?'ch':''}</small></div></div>
      </div>
    </div>
    <div class="rationale" style="margin-top:14px">${esc(r.summary||'')}</div>
    ${(r.strengths||[]).length?`<div class="strengths"><b>✓ Strengths:</b> ${r.strengths.map(cleanMd).join(' · ')}</div>`:''}

    <div class="asec"><div class="asec-hd"><span>📝 Title</span>${badge(inSec('title'))}</div>
      <div class="asec-cols"><div>${issList(inSec('title'))}</div>
        <div>${title?`<div class="sublbl">Current</div><div class="desc sm">${esc(title)}</div>`:''}
          <div class="sublbl" style="margin-top:12px">✦ Better title <span class="faint">(${(r.better_title||'').length}/140)</span></div><div class="desc sm" style="font-weight:600">${esc(r.better_title||'')}</div></div></div>
    </div>

    <div class="asec"><div class="asec-hd"><span>🏷️ Tags <span class="faint">${tCount}/13</span></span>${badge(inSec('tags'))}</div>
      ${issList(inSec('tags'))}
      ${tags.length?`<div class="sublbl" style="margin-top:12px">Current tags <span class="faint">(red = weak)</span></div><div class="chips">${chips(tags)}</div>`:''}
      <div class="sublbl anzrow" style="margin-top:14px">✦ Better 13 tags <button class="btn primary sm" id="auCopyTags">Copy all</button></div><div class="chips" id="auTags"></div>
    </div>

    <div class="asec"><div class="asec-hd"><span>📄 Description ${desc.length?`<span class="faint">${desc.length} ch</span>`:''}</span>${badge(inSec('description'))}</div>
      ${issList(inSec('description'))}
      ${r.better_description?`<div class="sublbl" style="margin-top:12px">✦ Better description</div><div class="desc sm">${esc(r.better_description)}</div>`:''}
    </div>

    <div class="asec"><div class="asec-hd"><span>🖼️ Media &amp; Images</span>${badge(inSec('media'))}</div>
      ${issList(inSec('media'))}
    </div>

    <div class="asec"><div class="asec-hd"><span>📋 Attributes &amp; Category</span>${badge(inSec('attributes'))}</div>
      ${issList(inSec('attributes'))}
    </div>`;
  const tg=$('#auTags',body);(r.better_tags||[]).forEach(t=>{const c=document.createElement('span');c.className='chip';c.textContent=t;c.title='Copy';c.onclick=async()=>{if(await copyText(t)){c.classList.add('flash');setTimeout(()=>c.classList.remove('flash'),700);}};tg.appendChild(c);});
  const cb=$('#auCopyTags',body);if(cb)cb.onclick=async()=>{if(await copyText((r.better_tags||[]).join(', '))){cb.textContent='Copied ✓';setTimeout(()=>cb.textContent='Copy all',1400);}};
  body.scrollTop=0;
}
/* dashboard widgets */
let trendCache=null;
function dashTrending(box){
  if(!box)return;
  if(trendCache){fillTrend(box,trendCache);return;}
  box.innerHTML=`<div class="skel" style="width:70%"></div><div class="skel" style="width:88%"></div><div class="skel" style="width:60%"></div>`;
  fetch('api/research.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({keyword:'',market})})
    .then(r=>r.json()).then(d=>{const cats=(d.research&&d.research.categories)||[];trendCache=cats;fillTrend(box,cats);})
    .catch(e=>{box.innerHTML='<div class="muted">Could not load trends — try Product Research.</div>';});
}
function fillTrend(box,cats){
  if(!cats||!cats.length){box.innerHTML='<div class="muted">No trends right now.</div>';return;}
  const cls={'very high':'good',high:'good',medium:'mid',low:'low'};
  box.innerHTML=cats.slice(0,6).map(c=>`<div class="trow"><a class="tkw" href="https://www.etsy.com/search?q=${encodeURIComponent(c.name||'')}" target="_blank" rel="noopener noreferrer">${esc(c.name||'')}</a><span class="kpill ${cls[String(c.demand).toLowerCase()]||'mid'}">${esc(c.demand||'')}</span></div>`).join('');
}
const CAL_EVENTS=[['Halloween','2026-10-31','halloween decor'],['Thanksgiving (US)','2026-11-26','thanksgiving decor'],['Black Friday','2026-11-27','black friday sale'],['Christmas','2026-12-25','christmas gift'],['New Year','2027-01-01','new year gift'],["Valentine's Day",'2027-02-14','valentines gift'],["Mother's Day (UK)",'2027-03-14','mothers day gift'],['Easter','2027-04-04','easter gift'],["Mother's Day (US)",'2027-05-09','mothers day gift'],["Father's Day",'2027-06-20','fathers day gift']];
function dashCalendar(box){
  if(!box)return;
  const now=new Date();now.setHours(0,0,0,0);
  const rows=CAL_EVENTS.map(e=>{const d=new Date(e[1]+'T00:00:00');return{n:e[0],kw:e[2],date:d,days:Math.ceil((d-now)/86400000)};}).filter(x=>x.days>=0).sort((a,b)=>a.days-b.days).slice(0,5);
  box.innerHTML=rows.map(r=>`<div class="crow"><div><b>${esc(r.n)}</b><span class="muted" style="display:block;font-size:11.5px;margin-top:1px">${r.date.toLocaleDateString(undefined,{month:'short',day:'numeric'})} · <a href="https://www.etsy.com/search?q=${encodeURIComponent(r.kw)}" target="_blank" rel="noopener noreferrer" style="color:var(--accent);text-decoration:none">${esc(r.kw)}</a></span></div><span class="cdays">${r.days}d</span></div>`).join('')||'<div class="muted">—</div>';
}
function dashKw(box){
  if(!box)return;const a=kwListGet();
  box.innerHTML=a.length?`<div class="chips">${a.slice(0,14).map(k=>`<span class="chip static">${esc(k)}</span>`).join('')}</div>${a.length>14?`<div class="muted" style="margin-top:8px;font-size:12px">+${a.length-14} more</div>`:''}`:'<div class="muted">No saved keywords yet — star ☆ keywords in <b>Keyword Research</b> to build your list.</div>';
}
/* ---- admin panel ---- */
function renderAdmin(){
  const w=frame();
  w.innerHTML=`<div class="dash-hd"><h1>Admin panel 🛡️</h1><p>All users, subscriptions &amp; search history — grant free access to anyone.</p></div>
    <div id="admBody"><div class="card pad"><div class="skel" style="width:55%"></div><div class="skel" style="width:80%"></div><div class="skel" style="width:65%"></div></div></div>`;
  adminLoad();
}
let admData={users:[]};
async function adminLoad(action,extra){
  const body=$('#admBody');if(!body)return;
  let r;try{r=await(await fetch('api/admin.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.assign({token:getToken(),action:action||'overview'},extra||{}))})).json();}
  catch(e){r={error:'Network error.'};}
  if(r.error){if(action&&action!=='overview'){alert(r.error);return adminLoad();}body.innerHTML=`<div class="banner caution"><span class="ic">!</span><div>${esc(r.error)}</div></div>`;return;}
  admData=r;
  const s=r.stats||{};
  const dt=t=>t?new Date(t*1000).toLocaleDateString():'—';
  const dtm=t=>t?new Date(t*1000).toLocaleString():'';
  const uRows=(r.users||[]).map(u=>`<tr>
    <td class="kkw">${esc(u.name||'—')}${u.is_admin?' <span class="engpill prem">admin</span>':''}</td>
    <td>${esc(u.email)}</td>
    <td>${u.unlimited?'<span class="kpill g">Pro</span>':'<span class="kpill m">Free</span>'}</td>
    <td class="tnum">${u.free_used}</td>
    <td>${u.google?'Google':'Email'}</td>
    <td>${dt(u.created)}</td>
    <td>${u.is_admin?'<span class="faint" style="font-size:12px">—</span>':`<div class="rowacts">
      <button class="btn ${u.granted?'ghost':'primary'} sm" data-grant="${esc(u.email)}" data-on="${u.granted?0:1}">${u.granted?'Revoke':'Grant free'}</button>
      <button class="icobtn" data-edit="${esc(u.email)}" title="Edit user">✎</button>
      <button class="icobtn del" data-del="${esc(u.email)}" title="Delete user">🗑</button></div>`}</td>
  </tr>`).join('');
  const hRows=(r.history||[]).map(h=>`<tr><td class="kkw">${esc(h.name||h.email||'—')}</td><td><span class="kpill ${h.type==='listing'?'g':'m'}">${esc(h.type)}</span></td><td>${esc(h.query||'')}</td><td class="muted" style="white-space:nowrap">${esc(dtm(h.ts))}</td></tr>`).join('');
  body.innerHTML=`<div class="stack">
    <div class="dash-stats">
      <div class="dash-stat"><div class="k">Total users</div><div class="v">${s.users||0}</div></div>
      <div class="dash-stat"><div class="k">Subscriptions (Pro)</div><div class="v">${s.pro||0}</div></div>
      <div class="dash-stat"><div class="k">Searches logged</div><div class="v">${s.searches||0}</div></div>
    </div>
    <section class="card pad"><div class="chead"><span class="lbl">Users<span class="n">${(r.users||[]).length}</span></span><button class="btn primary sm" id="admAdd">+ Add user</button></div>
      <div class="tablewrap"><div class="tscroll"><table class="kwtable"><thead><tr><th>Name</th><th>Email</th><th>Plan</th><th class="tnum">Used</th><th>Via</th><th>Joined</th><th>Actions</th></tr></thead><tbody>${uRows||'<tr><td colspan="7" class="muted">No users yet.</td></tr>'}</tbody></table></div></div></section>
    <section class="card pad"><div class="chead"><span class="lbl">Search &amp; listing history<span class="n">${(r.history||[]).length}</span></span></div>
      <div class="tablewrap"><div class="tscroll"><table class="kwtable"><thead><tr><th>User</th><th>Type</th><th>Query</th><th>When</th></tr></thead><tbody>${hRows||'<tr><td colspan="4" class="muted">No activity yet.</td></tr>'}</tbody></table></div></div></section>
  </div>`;
  $('#admAdd').onclick=()=>admUserForm('add',{});
  body.querySelectorAll('[data-grant]').forEach(b=>b.onclick=()=>{b.disabled=true;b.textContent='…';adminLoad('grant',{email:b.dataset.grant,grant:b.dataset.on==='1'});});
  body.querySelectorAll('[data-edit]').forEach(b=>b.onclick=()=>{const u=(admData.users||[]).find(x=>x.email===b.dataset.edit)||{email:b.dataset.edit};admUserForm('edit',u);});
  body.querySelectorAll('[data-del]').forEach(b=>b.onclick=()=>{if(confirm('Delete user '+b.dataset.del+'? This cannot be undone.'))adminLoad('delete',{email:b.dataset.del});});
}
function admUserForm(mode,u){
  u=u||{};const ov=document.createElement('div');ov.className='modal-ov';
  ov.innerHTML=`<div class="modal">
    <div class="chead"><span class="lbl">${mode==='add'?'Add user':'Edit user'}</span><button class="icobtn" id="mX">✕</button></div>
    <div class="auth-field"><label>Name</label><input id="mName" value="${esc(u.name||'')}" placeholder="Full name"></div>
    <div class="auth-field"><label>Email</label><input id="mEmail" type="email" value="${esc(u.email||'')}" ${mode==='edit'?'readonly style="opacity:.6;cursor:not-allowed"':'placeholder="user@email.com"'}></div>
    <div class="auth-field"><label>Password ${mode==='edit'?'<span class="faint">(blank = keep current)</span>':''}</label><input id="mPass" type="password" placeholder="${mode==='edit'?'••••••••':'Set a password'}"></div>
    <div class="auth-field"><label>Plan</label><select id="mPlan"><option value="free" ${u.unlimited?'':'selected'}>Free (5 listings)</option><option value="pro" ${u.unlimited?'selected':''}>Pro (unlimited)</option></select></div>
    <div style="display:flex;gap:9px;justify-content:flex-end;margin-top:14px"><button class="btn ghost sm" id="mCancel">Cancel</button><button class="btn primary sm" id="mSave">${mode==='add'?'Create user':'Save changes'}</button></div>
  </div>`;
  document.body.appendChild(ov);
  const close=()=>ov.remove();
  ov.onclick=e=>{if(e.target===ov)close();};$('#mX',ov).onclick=close;$('#mCancel',ov).onclick=close;
  $('#mSave',ov).onclick=async(e)=>{
    const name=$('#mName',ov).value.trim(),email=$('#mEmail',ov).value.trim(),pass=$('#mPass',ov).value,plan=$('#mPlan',ov).value;
    if(mode==='add'&&!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)){$('#mEmail',ov).focus();$('#mEmail',ov).style.borderColor='var(--err,#c0392b)';return;}
    e.currentTarget.disabled=true;e.currentTarget.textContent='Saving…';
    close();
    await adminLoad(mode==='add'?'add':'update',mode==='add'?{name,email,password:pass,plan}:{email:u.email,name,password:pass,plan});
  };
  setTimeout(()=>{const f=$('#mName',ov);if(f)f.focus();},40);
}

/* ---- tools ---- */
function renderTools(){
  const w=frame();
  const hd=document.createElement('div');hd.className='dash-hd';hd.innerHTML='<h1>Tools</h1><p>Handy Etsy calculators &amp; utilities.</p>';w.appendChild(hd);
  const stk=document.createElement('div');stk.className='stack';w.appendChild(stk);
  stk.appendChild(calcCard({pricing:{}},market));
  const soon=document.createElement('section');soon.className='card pad';
  soon.innerHTML=`<div class="chead"><span class="lbl">More tools — coming soon</span></div>
    <div class="chips">${['Hashtag Generator','Category Finder','Color Thesaurus','Etsy Ads ROI','Seasonal Calendar','Spell Checker'].map(t=>`<span class="chip static">${esc(t)}</span>`).join('')}</div>`;
  stk.appendChild(soon);
}

/* run boot AFTER the whole script has parsed, so every const (kwListGet, CAL_EVENTS…) is initialised */
setTimeout(function(){
  if(isAuthed()){
    const a=getAccount()||{};
    document.body.classList.remove('noauth');document.body.classList.toggle('is-admin',!!a.is_admin);
    mountAccount();showView(a.is_admin?'admin':'dashboard');
    authApi('me',{token:getToken()}).then(r=>{
      if(r&&r.user){usage={used:r.user.used||0,limit:r.user.limit||5,unlimited:!!r.user.unlimited,remaining:r.user.remaining};renderUsage();
        setAccount({name:r.user.name,email:r.user.email,is_admin:!!r.user.is_admin,plan:r.user.plan});document.body.classList.toggle('is-admin',!!r.user.is_admin);}
      else if(r&&r.error){signOut();}
    });
  }else{document.body.classList.add('noauth');renderLanding();}
},0);

/* research */
function renderResearch(){
  const w=frame();
  w.innerHTML=`<section class="hero-input">
    <div class="chead" style="margin-bottom:6px"><span class="lbl">✦ Etsy Product Research</span></div>
    <p class="hint" style="margin:0 0 14px 2px">See what’s selling on Etsy right now — pick a niche, or leave blank for the top categories.</p>
    <div class="urlrow">
      <div class="urlbox"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
        <input id="rq" placeholder="Niche or keyword (optional) — e.g. jewellery, home decor, pet, wedding" autocomplete="off"></div>
      <button class="btn primary" id="rgo">Find categories</button>
    </div>
  </section>
  <div id="rout" style="margin-top:20px"></div>`;
  $('#rgo').onclick=runResearch;$('#rq').addEventListener('keydown',e=>{if(e.key==='Enter')runResearch();});
}
function runResearch(){
  const keyword=$('#rq').value.trim(),out=$('#rout'),rgo=$('#rgo');
  rgo.disabled=true;rgo.textContent='Finding…';
  out.innerHTML=`<div class="card pad"><div class="skel" style="width:55%"></div><div class="skel" style="width:80%"></div><div class="skel" style="width:65%"></div><div class="skel" style="width:75%"></div></div>`;
  fetch('api/research.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({keyword,market})})
    .then(r=>r.json()).then(d=>{
      if(d.error){out.innerHTML=`<div class="banner caution"><span class="ic">!</span><div>${esc(d.error)}</div></div>`;return;}
      const cats=(d.research&&d.research.categories)||[],sug=(d.research&&d.research.suggestions)||[];
      const sugHtml=sug.length?`<section class="card pad sugcard"><div class="chead"><span class="lbl">✦ AI Suggestions</span></div><ul class="tips">${sug.map(x=>`<li>${esc(String(x).replace(/\*\*/g,''))}</li>`).join('')}</ul></section>`:'';
      out.innerHTML=cats.length?`<div class="stack">${sugHtml}${cats.map(researchCard).join('')}</div>`:'<div class="card pad muted">No categories returned — try a different keyword.</div>';
    }).catch(e=>{out.innerHTML=`<div class="banner caution"><span class="ic">!</span><div>Network error: ${esc(e.message)}</div></div>`;})
    .finally(()=>{rgo.disabled=false;rgo.textContent='Find categories';});
}
function researchCard(c){
  const warn=String(c.demand||'').toLowerCase()==='medium';
  return `<section class="card pad">
    <div class="chead"><span class="lbl">${esc(c.name||'')}</span><span class="counter${warn?' warnpill':''}">${esc(c.demand||'')} demand</span></div>
    <div class="muted" style="margin:-6px 0 12px">${esc(c.why||'')}</div>
    <div class="chips" style="margin-bottom:${Array.isArray(c.ideas)&&c.ideas.length?'14px':'0'}"><span class="crumb">Price: ${esc(c.price_range||'—')}</span></div>
    ${Array.isArray(c.ideas)&&c.ideas.length?`<div class="sugt">Product ideas</div><ul class="tips">${c.ideas.map(i=>`<li>${esc(i)}</li>`).join('')}</ul>`:''}
    ${Array.isArray(c.tags)&&c.tags.length?`<div class="chips" style="margin-top:12px">${c.tags.map(t=>`<span class="chip static">${esc(t)}</span>`).join('')}</div>`:''}
  </section>`;
}

/* ============ Keyword Research (eRank-style) ============ */
const KWLIST_KEY='els-kwlist';
const kwListGet=()=>{try{return JSON.parse(localStorage.getItem(KWLIST_KEY)||'[]');}catch(e){return[];}};
const kwListSet=a=>localStorage.setItem(KWLIST_KEY,JSON.stringify(a.slice(0,300)));
const kwSaved=k=>kwListGet().some(x=>x.toLowerCase()===k.toLowerCase());
function kwToggle(k){const a=kwListGet(),i=a.findIndex(x=>x.toLowerCase()===k.toLowerCase());if(i>=0)a.splice(i,1);else a.push(k);kwListSet(a);return a;}
const etsyUrl=q=>'https://www.etsy.com/search?q='+encodeURIComponent(q);
const LMH={low:'low',medium:'med',high:'high'};
const dPill=v=>`<span class="kpill ${{high:'good',medium:'mid',low:'low'}[v]||'mid'}">${esc(LMH[v]||v||'—')}</span>`;
const cPill=v=>`<span class="kpill ${{low:'good',medium:'mid',high:'low'}[v]||'mid'}">${esc(LMH[v]||v||'—')}</span>`;
const oppBar=v=>`<span class="oppwrap"><span class="oppbar"><i class="${v>=66?'good':v>=40?'mid':'low'}" style="width:${v}%"></i></span><b>${v}</b></span>`;

function renderKeywords(){
  const w=frame();
  w.innerHTML=`<section class="hero-input">
    <div class="chead" style="margin-bottom:6px"><span class="lbl">✦ Keyword Research</span><span class="counter" id="kwCount" style="cursor:pointer" title="View saved keyword list">${kwListGet().length} saved</span></div>
    <p class="hint" style="margin:0 0 14px 2px">Find the exact phrases Etsy buyers type. Demand &amp; competition are <b>AI estimates</b> — tap “See on Etsy” to verify the real numbers.</p>
    <div class="urlrow">
      <div class="urlbox"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
        <input id="kq" placeholder="A keyword or product — e.g. name necklace, soy candle, wall art" autocomplete="off"></div>
      <button class="btn primary" id="kgo">Research</button>
    </div>
  </section>
  <div id="kout" style="margin-top:20px"></div>`;
  $('#kgo').onclick=runKeywords;$('#kq').addEventListener('keydown',e=>{if(e.key==='Enter')runKeywords();});
  $('#kwCount').onclick=showKwList;$('#kq').focus();
}
function runKeywords(){
  const seed=$('#kq').value.trim(),out=$('#kout'),kgo=$('#kgo');
  if(!seed){$('#kq').focus();return;}
  kgo.disabled=true;kgo.textContent='Researching…';
  out.innerHTML=`<div class="card pad"><div class="bar"><i></i></div><div class="skel" style="width:50%"></div><div class="skel" style="width:78%"></div><div class="skel" style="width:64%"></div></div>`;
  fetch('api/keywords.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({seed,market,token:getToken()})})
    .then(r=>r.json()).then(d=>{
      if(d.error){out.innerHTML=`<div class="banner caution"><span class="ic">!</span><div>${esc(d.error)}</div></div>`;return;}
      renderKwResult(out,d);
    }).catch(e=>{out.innerHTML=`<div class="banner caution"><span class="ic">!</span><div>Network error: ${esc(e.message)}</div></div>`;})
    .finally(()=>{kgo.disabled=false;kgo.textContent='Research';});
}
function renderKwResult(out,d){
  const seed=d.seed,st=d.data.stats||{},kws=d.data.keywords||[],tags=d.data.top_tags||[],ideas=d.data.ideas||[];
  let sortKey='demand',sortDir=-1,filter='';const RANK={low:1,medium:2,high:3};
  const wrap=document.createElement('div');wrap.className='stack';out.innerHTML='';out.appendChild(wrap);
  const oppCol=st.opportunity>=66?'var(--ok)':st.opportunity>=40?'var(--warn,#DBAA46)':'var(--err,#c0392b)';
  const hb=document.createElement('section');hb.className='card pad';
  hb.innerHTML=`<div class="chead"><span class="lbl">“${esc(seed)}” <span class="engpill">AI estimate</span></span>
    <a class="btn primary sm" href="${etsyUrl(seed)}" target="_blank" rel="noopener noreferrer">See live on Etsy ↗</a></div>
   <div class="kstats">
     <div class="kstat"><div class="k">Buyer demand</div><div class="v">${dPill(st.demand)}</div></div>
     <div class="kstat"><div class="k">Competition</div><div class="v">${cPill(st.competition)}</div></div>
     <div class="kstat"><div class="k">Opportunity</div><div class="v" style="color:${oppCol};font-weight:800">${st.opportunity||0}/100</div></div>
     <div class="kstat"><div class="k">Trend</div><div class="v">${esc(pretty(st.trend||''))}</div></div>
     <div class="kstat"><div class="k">Best months</div><div class="v" style="font-size:12.5px;font-weight:600">${(st.best_months||[]).slice(0,4).map(esc).join(', ')||'—'}</div></div>
   </div>
   ${st.summary?`<div class="rationale" style="margin-top:12px">${esc(st.summary)}${st.trend_note?' '+esc(st.trend_note):''}</div>`:''}`;
  wrap.appendChild(hb);
  {const sug=(d.data.suggestions)||{};
   if((sug.best_bets&&sug.best_bets.length)||(sug.strategy&&sug.strategy.length)){
     const sc=document.createElement('section');sc.className='card pad sugcard';
     const bb=(sug.best_bets||[]).map(b=>`<div class="bbrow"><a class="tkw" href="${etsyUrl(b.keyword||'')}" target="_blank" rel="noopener noreferrer">${esc(b.keyword||'')}</a><span class="muted">${esc(b.why||'')}</span></div>`).join('');
     sc.innerHTML=`<div class="chead"><span class="lbl">✦ AI Suggestions</span></div>
       ${bb?`<div class="sugt">🎯 Target these first — best opportunity</div><div class="bblist">${bb}</div>`:''}
       ${sug.title_idea?`<div class="sugt" style="margin-top:16px">📝 Ready-to-use title</div><div class="desc" style="font-weight:600">${esc(sug.title_idea)}</div>`:''}
       ${(sug.strategy&&sug.strategy.length)?`<div class="sugt" style="margin-top:16px">💡 Strategy</div><ul class="tips">${sug.strategy.map(x=>`<li>${esc(String(x).replace(/\*\*/g,''))}</li>`).join('')}</ul>`:''}
       ${(sug.avoid&&sug.avoid.length)?`<div class="sugt" style="margin-top:14px">⚠️ Avoid — too saturated for a small shop</div><div class="chips">${sug.avoid.map(x=>`<span class="chip static">${esc(x)}</span>`).join('')}</div>`:''}`;
     if(sug.title_idea){const cb=copyBtn(()=>sug.title_idea);$('.chead',sc).appendChild(cb);}
     wrap.appendChild(sc);
   }}
  if(tags.length){const tc=document.createElement('section');tc.className='card pad';
    tc.innerHTML=`<div class="chead"><span class="lbl">Best 13 tags (ready to paste)</span></div>`;
    const ch=document.createElement('div');ch.className='chips';
    tags.forEach(t=>{const c=document.createElement('span');c.className='chip';c.textContent=t;c.title='Click to copy';
      c.onclick=async()=>{if(await copyText(t)){c.classList.add('flash');setTimeout(()=>c.classList.remove('flash'),700);}};ch.appendChild(c);});
    tc.appendChild(ch);$('.chead',tc).appendChild(copyBtn(()=>tags.join(', ')));wrap.appendChild(tc);}
  const tcard=document.createElement('section');tcard.className='card pad';
  tcard.innerHTML=`<div class="chead"><span class="lbl">Related keywords<span class="n">${kws.length}</span></span></div>
    <input class="kfilter" id="kfil" placeholder="Filter keywords…" autocomplete="off">
    <div class="tablewrap"><div class="tscroll"><table class="kwtable"><thead><tr>
      <th></th><th data-s="keyword">Keyword</th><th data-s="words" class="tnum">W</th><th data-s="chars" class="tnum">Ch</th>
      <th data-s="demand">Demand</th><th data-s="competition">Comp.</th><th data-s="opportunity" class="tnum">Opportunity</th><th></th>
    </tr></thead><tbody id="kbody"></tbody></table></div></div>`;
  wrap.appendChild(tcard);
  const body=$('#kbody',tcard);
  const draw=()=>{
    let rows=kws.filter(k=>!filter||k.keyword.toLowerCase().includes(filter));
    const rv=(k,v)=>(k==='demand'||k==='competition')?(RANK[v]||0):v;
    const cmp=(a,b,k,dir)=>{const x=rv(k,a[k]),y=rv(k,b[k]);return typeof x==='number'?(x-y)*dir:String(x).localeCompare(String(y))*dir;};
    rows=rows.slice().sort((a,b)=>{const p=cmp(a,b,sortKey,sortDir);return p!==0?p:cmp(a,b,'opportunity',-1);});
    body.innerHTML=rows.map(k=>{const sv=kwSaved(k.keyword);
      return `<tr><td class="ksave"><button class="star${sv?' on':''}" data-k="${esc(k.keyword)}" title="Save to list">${sv?'★':'☆'}</button></td>
        <td class="kkw">${esc(k.keyword)}${k.trending?' <span title="Trending">🔥</span>':''}</td>
        <td class="tnum">${k.words}</td><td class="tnum">${k.chars}</td>
        <td>${dPill(k.demand)}</td><td>${cPill(k.competition)}</td><td>${oppBar(k.opportunity)}</td>
        <td><a class="klink" href="${etsyUrl(k.keyword)}" target="_blank" rel="noopener noreferrer" title="See on Etsy">↗</a></td></tr>`;}).join('');
    body.querySelectorAll('.star').forEach(b=>b.onclick=()=>{kwToggle(b.dataset.k);const c=$('#kwCount');if(c)c.textContent=kwListGet().length+' saved';draw();});
  };
  $('#kfil',tcard).addEventListener('input',e=>{filter=e.target.value.trim().toLowerCase();draw();});
  tcard.querySelectorAll('th[data-s]').forEach(th=>{th.style.cursor='pointer';th.onclick=()=>{const k=th.dataset.s;if(sortKey===k)sortDir*=-1;else{sortKey=k;sortDir=k==='keyword'?1:-1;}draw();};});
  draw();
  if(ideas.length){const ic=document.createElement('section');ic.className='card pad';
    ic.innerHTML=`<div class="chead"><span class="lbl">Product ideas for this niche</span></div><ul class="tips">${ideas.map(i=>`<li>${esc(i)}</li>`).join('')}</ul>`;wrap.appendChild(ic);}
  window.scrollTo({top:0,behavior:'smooth'});
}
function showKwList(){
  const a=kwListGet(),w=frame();
  const rows=a.length?a.map(k=>`<div class="krow" data-k="${esc(k)}"><span>${esc(k)}</span><button class="rmk" title="Remove">&times;</button></div>`).join(''):'<div class="muted">No saved keywords yet. Star ☆ keywords in Research to build your list.</div>';
  w.innerHTML=`<section class="hero-input">
    <div class="chead" style="margin-bottom:12px"><span class="lbl">✦ Saved keyword list<span class="n">${a.length}</span></span>
      <button class="btn ghost sm" id="kback">← Back to Research</button></div>
    <div id="klist" class="stack" style="gap:6px">${rows}</div>
    ${a.length?`<div style="display:flex;gap:9px;margin-top:16px;flex-wrap:wrap"><button class="btn primary sm" id="kcopy">Copy all</button><button class="btn ghost sm" id="kcsv">Export CSV</button><button class="btn ghost sm" id="kclear">Clear list</button></div>`:''}
  </section>`;
  $('#kback').onclick=renderKeywords;
  w.querySelectorAll('.rmk').forEach(b=>b.onclick=e=>{kwToggle(e.target.closest('[data-k]').dataset.k);showKwList();});
  if(a.length){
    $('#kcopy').onclick=async()=>{if(await copyText(a.join(', ')))$('#kcopy').textContent='Copied ✓';};
    $('#kcsv').onclick=()=>dl('keywords.csv','text/csv','keyword\n'+a.map(k=>/[",]/.test(k)?'"'+k.replace(/"/g,'""')+'"':k).join('\n'));
    $('#kclear').onclick=()=>{kwListSet([]);showKwList();};
  }
}
</script>
</body>
</html>
