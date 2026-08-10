# Traffic Guard for Flarum

Admin-configurable request blocking for **Flarum 1.8.x** with custom HTML responses.

## What it does

Traffic Guard evaluates a visitor before Flarum renders the forum. It supports:

- Exact IPv4 and IPv6 allow/block rules
- IPv4/IPv6 CIDR ranges
- Country and ASN rules when a network reputation provider is enabled
- URL path glob rules such as `/private/*`
- User-Agent substring rules
- VPN, proxy, Tor and hosting/datacenter detection through an optional provider
- Configurable risk threshold
- Per-rule reason, priority, expiration, response page and HTTP status
- Separate custom HTML pages for manual bans, VPN, proxy, Tor, hosting, country, ASN, path, User-Agent, risk and provider failures
- Safe HTML preview in the admin panel
- Request logs with full, masked or hashed IP storage
- Reputation lookup cache
- Trusted reverse-proxy/CDN client-IP handling
- Test-IP tool
- CLI emergency recovery

## Target Flarum version

This package targets the current stable Flarum 1.x API:

```json
"flarum/core": "^1.8"
```

A separate port should be made for Flarum 2.x because its admin and API layers changed substantially.

## Install from GitHub

Once the repository is published, a Flarum installation can add it directly as a Composer VCS repository:

```bash
composer config repositories.traffic-guard vcs https://github.com/markhitchk/flarum-traffic-guard
composer require markhitchk/flarum-traffic-guard:dev-main
php flarum migrate
php flarum cache:clear
```

For a tagged stable release, use the tagged Composer version instead of `dev-main`.

## Install from a local package

Copy this directory to your Flarum installation, for example:

```text
packages/flarum-traffic-guard
```

Then from your Flarum root:

```bash
composer config repositories.traffic-guard path "packages/flarum-traffic-guard"
composer require markhitchk/flarum-traffic-guard:@dev
php flarum migrate
php flarum cache:clear
```

Enable **Traffic Guard** in Administration > Extensions.

The extension starts with the internal master switch **OFF** even after the extension itself is enabled. Configure and test your allow/block rules first, then enable Traffic Guard from its admin page.

## Safe first-time setup

1. Enable the Flarum extension.
2. Leave **Enable Traffic Guard** off.
3. Add an `allow` rule for your administrator IP if it is reasonably stable.
4. Add one test block rule for an unused/test IP.
5. Use the **Tools > Test an IP** feature.
6. Configure your custom HTML pages.
7. If using a CDN/reverse proxy, configure trusted proxy ranges before enabling forwarded headers.
8. Turn on the master switch.
9. Leave **Protect admin HTML** off until you have tested recovery.

## Rule precedence

Traffic Guard intentionally makes explicit allow rules a safety override.

High-level evaluation order:

1. Extension/scope enabled check
2. Resolve real client IP
3. Exact-IP/CIDR/path/User-Agent rules
   - matching `allow` wins
   - otherwise highest-priority matching `block` wins
4. Optional cached provider lookup
5. Country/ASN rules
   - matching `allow` wins
   - otherwise matching `block` wins
6. Automatic Tor block
7. Automatic VPN block
8. Automatic proxy block
9. Automatic hosting/datacenter block
10. Risk threshold
11. Allow

## Custom HTML variables

Every configured block page can use:

```text
{{IP}}
{{STATUS}}
{{REASON}}
{{BLOCK_ID}}
{{BLOCK_TYPE}}
{{RULE_ID}}
{{EXPIRES}}
{{DATE_UTC}}
{{PATH}}
{{COUNTRY}}
{{COUNTRY_CODE}}
{{ASN}}
{{RISK}}
{{FORUM_NAME}}
{{FORUM_URL}}
{{SUPPORT_URL}}
```

Dynamic values are HTML-escaped before insertion. The HTML itself is administrator-trusted and is returned as a complete raw document to blocked visitors.

## HTTP status codes

The default is `403 Forbidden`. You can set a response page or individual rule to any 4xx/5xx status from 400-599.

Useful examples:

- `403` — normal access denial
- `404` — custom not-found style response
- `410` — intentionally unavailable
- `429` — only if you later implement/use a rate-limit rule
- `451` — only when actually appropriate for a legal restriction
- `503` — provider-error page when configured to fail closed

## VPN/proxy provider

The included provider adapter supports proxycheck.io v2. Provider use is optional and defaults to `none`.

When enabled, the extension requests network metadata on the backend and caches the normalized result. Your API key is a Flarum admin setting and is never serialized to the normal forum frontend by this extension.

Default provider behavior:

- 3 second timeout
- 24 hour cache
- fail open
- provider calls skipped for private/reserved IP addresses

### Fail-open versus fail-closed

**Fail open** is recommended. If the external reputation provider fails, the request continues unless a local/manual rule blocks it.

**Fail closed** returns your configured provider-error block page. This can deny legitimate traffic during a provider outage.

## Reverse proxies and Cloudflare

Do not trust `CF-Connecting-IP`, `X-Forwarded-For`, or `X-Real-IP` merely because the header exists.

Traffic Guard only reads the configured forwarded header after `REMOTE_ADDR` matches one of the configured trusted proxy IP/CIDR ranges.

For `X-Forwarded-For`, the resolver walks the chain from the trusted proxy side toward the client and selects the first untrusted valid address.

If you use Cloudflare, keep its published IP ranges current in **Trusted proxy IPs / CIDRs** or enforce that your origin accepts connections only from Cloudflare.

## Logs and privacy

Traffic Guard logs no request bodies, cookies, passwords, form data, or authorization headers.

Log IP modes:

- `full` — stores the full client IP
- `masked` — IPv4 `/24`, IPv6 `/64`
- `hashed` — SHA-256 of the IP

The default is block-only logging with a 30-day retention period.

## CLI recovery

If a rule locks you out of the site or admin panel, use the Flarum server shell:

```bash
php flarum traffic-guard:disable
```

Remove block rules matching one address:

```bash
php flarum traffic-guard:unban 203.0.113.25
```

Inspect status:

```bash
php flarum traffic-guard:status
```

Re-enable:

```bash
php flarum traffic-guard:enable
```

Manual maintenance:

```bash
php flarum traffic-guard:prune
```

The prune command is also registered with Flarum's scheduler. Your normal Flarum/Laravel scheduler must actually be running for scheduled pruning to occur.

## What Flarum middleware cannot block

This is application-level protection. It applies to the Flarum `forum`, `api`, and `admin` stacks.

A static file such as:

```text
/assets/forum.css
/assets/logo.png
/favicon.ico
```

may be served by Nginx, Apache, a reverse proxy or a CDN without entering PHP/Flarum at all. If the requirement is **"this IP must not retrieve anything from the domain"**, mirror important blocks at the CDN/web-server firewall layer.

## Security design notes

- All Traffic Guard management endpoints require a Flarum administrator.
- Manual allow rules override automated provider detections.
- Forwarded client-IP headers are ignored unless the immediate proxy is trusted.
- Threat lookups are cached to reduce latency and external data disclosure.
- Provider outages fail open by default.
- Logging failures do not block forum traffic.
- Unexpected Traffic Guard internal errors fail open rather than taking Flarum offline.
- Custom HTML preview is rendered in a sandboxed iframe.
- Dynamic page variables are escaped before substitution.
- The admin scope is off by default.
- CLI recovery does not depend on web access.

## Development

The shipped `js/dist/admin.js` is a directly runnable Flarum 1.x admin bundle and does not require a Node build step for installation. A copy is retained under `js/src/admin/index.js` for editing.

After backend changes:

```bash
composer dump-autoload
php flarum migrate
php flarum cache:clear
```

After editing the shipped admin JS or Less, clear Flarum's cache:

```bash
php flarum cache:clear
```

## Production recommendations

For a public forum, combine this extension with:

- Cloudflare/WAF or web-server IP blocks for whole-domain enforcement
- a reliable origin firewall
- rate limiting at the edge
- HTTPS
- current Flarum and PHP security updates
- conservative VPN/datacenter blocking rules to reduce false positives

Traffic Guard should not be treated as a replacement for a network firewall or DDoS service.

## Additional documentation

- `docs/ARCHITECTURE.md` — request flow, rule precedence and component design
- `docs/SECURITY.md` — safe deployment, proxy headers, custom HTML, logging and recovery
- `examples/` — ready-to-paste manual-ban, VPN-block and custom 404 HTML pages
