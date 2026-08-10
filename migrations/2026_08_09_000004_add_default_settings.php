<?php

use Flarum\Database\Migration;

$defaultPage = <<<'HTML'
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Access Restricted</title>
<style>
:root{color-scheme:dark}*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#111;color:#ddd;font:16px/1.55 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.card{width:min(680px,100%);padding:34px;border:1px solid #3d3d3d;border-radius:12px;background:#181818;box-shadow:0 18px 60px rgba(0,0,0,.35)}.code{font-size:54px;font-weight:800;letter-spacing:-2px;color:#fff}h1{margin:.2rem 0 .7rem;color:#fff}p{margin:.5rem 0;color:#bbb}.meta{margin-top:22px;padding-top:18px;border-top:1px solid #333;font:13px/1.6 ui-monospace,SFMono-Regular,Menlo,monospace;color:#999}.support{margin-top:20px}a{color:#7ab7ff}</style>
</head>
<body>
<main class="card">
<div class="code">{{STATUS}}</div>
<h1>Access Restricted</h1>
<p>{{REASON}}</p>
<div class="meta">Reference: {{BLOCK_ID}}<br>Type: {{BLOCK_TYPE}}<br>Time: {{DATE_UTC}}</div>
<p class="support">If you believe this restriction is an error, contact the forum administrator.</p>
</main>
</body>
</html>
HTML;

$vpnPage = str_replace(
    ['Access Restricted</h1>', '{{REASON}}'],
    ['VPN or Proxy Connection Restricted</h1>', 'This forum does not currently permit the detected anonymizing network connection. Disable the VPN/proxy and reload the page, or contact support if this detection is incorrect.'],
    $defaultPage
);

$manualPage = str_replace(
    ['Access Restricted</h1>', '{{REASON}}'],
    ['Network Access Blocked</h1>', '{{REASON}}'],
    $defaultPage
);

return Migration::addSettings([
    'markhitchk-traffic-guard.enabled' => '0',
    'markhitchk-traffic-guard.scope_forum' => '1',
    'markhitchk-traffic-guard.scope_api' => '1',
    'markhitchk-traffic-guard.scope_admin' => '0',
    'markhitchk-traffic-guard.api_response_mode' => 'json',

    'markhitchk-traffic-guard.provider' => 'none',
    'markhitchk-traffic-guard.proxycheck_key' => '',
    'markhitchk-traffic-guard.provider_timeout' => '3',
    'markhitchk-traffic-guard.fail_mode' => 'open',
    'markhitchk-traffic-guard.cache_enabled' => '1',
    'markhitchk-traffic-guard.cache_hours' => '24',

    'markhitchk-traffic-guard.block_vpn' => '0',
    'markhitchk-traffic-guard.block_proxy' => '0',
    'markhitchk-traffic-guard.block_tor' => '0',
    'markhitchk-traffic-guard.block_hosting' => '0',
    'markhitchk-traffic-guard.risk_threshold' => '0',

    'markhitchk-traffic-guard.trust_proxy_headers' => '0',
    'markhitchk-traffic-guard.proxy_header' => 'CF-Connecting-IP',
    'markhitchk-traffic-guard.trusted_proxy_cidrs' => '',

    'markhitchk-traffic-guard.log_blocks' => '1',
    'markhitchk-traffic-guard.log_allowed' => '0',
    'markhitchk-traffic-guard.log_ip_mode' => 'full',
    'markhitchk-traffic-guard.log_retention_days' => '30',

    'markhitchk-traffic-guard.support_url' => '',
    'markhitchk-traffic-guard.default_status' => '403',
    'markhitchk-traffic-guard.status_manual' => '403',
    'markhitchk-traffic-guard.status_vpn' => '403',
    'markhitchk-traffic-guard.status_proxy' => '403',
    'markhitchk-traffic-guard.status_tor' => '403',
    'markhitchk-traffic-guard.status_hosting' => '403',
    'markhitchk-traffic-guard.status_country' => '403',
    'markhitchk-traffic-guard.status_asn' => '403',
    'markhitchk-traffic-guard.status_path' => '403',
    'markhitchk-traffic-guard.status_user_agent' => '403',
    'markhitchk-traffic-guard.status_risk' => '403',
    'markhitchk-traffic-guard.status_provider_error' => '503',

    'markhitchk-traffic-guard.page_default' => $defaultPage,
    'markhitchk-traffic-guard.page_manual' => $manualPage,
    'markhitchk-traffic-guard.page_vpn' => $vpnPage,
    'markhitchk-traffic-guard.page_proxy' => $vpnPage,
    'markhitchk-traffic-guard.page_tor' => $vpnPage,
    'markhitchk-traffic-guard.page_hosting' => $defaultPage,
    'markhitchk-traffic-guard.page_country' => $defaultPage,
    'markhitchk-traffic-guard.page_asn' => $defaultPage,
    'markhitchk-traffic-guard.page_path' => $defaultPage,
    'markhitchk-traffic-guard.page_user_agent' => $defaultPage,
    'markhitchk-traffic-guard.page_risk' => $defaultPage,
    'markhitchk-traffic-guard.page_provider_error' => $defaultPage,
]);
