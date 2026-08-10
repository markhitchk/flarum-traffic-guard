# Security Notes

## Before enabling

1. Keep the Traffic Guard master switch off while configuring it.
2. Add an allow rule for a trusted administrator network when practical.
3. Test rules with the built-in IP test tool.
4. Configure forwarded IP headers only after adding the real proxy/CDN ranges.
5. Keep provider failure mode set to `open` unless denial during provider outages is intentional.
6. Keep admin-scope protection disabled until CLI recovery has been tested.

## Forwarded IP headers

Never trust a client-controlled `X-Forwarded-For`, `X-Real-IP`, or `CF-Connecting-IP` header directly. Traffic Guard ignores the configured forwarded header unless `REMOTE_ADDR` first matches a configured trusted proxy IP/CIDR.

The X-Forwarded-For resolver walks from the trusted side toward the client and selects the first valid address not belonging to a trusted proxy range.

## Custom HTML

Block-page HTML is trusted administrator code. An administrator can intentionally add scripts, external resources, forms, redirects, or any other browser behavior to that HTML.

Dynamic Traffic Guard variables such as `{{IP}}` and `{{REASON}}` are HTML-escaped before substitution. The preview uses a sandboxed iframe.

For the smallest attack surface, use static HTML/CSS only and avoid third-party scripts on denial pages.

## Reputation providers

When a provider is enabled, the client's public IP is sent to that provider. Traffic Guard caches results to reduce repeated requests. Review the provider's terms/privacy policy before enabling it.

## Logs

Traffic Guard intentionally does not log request bodies, passwords, cookies, authorization headers, or form payloads. It can store full, masked, or hashed client IPs.

Treat full IP addresses and access logs as security/privacy data. Limit retention and database access appropriately.

## Recovery

Disable all enforcement:

```bash
php flarum traffic-guard:disable
```

Remove local IP/CIDR block rules that match one IP:

```bash
php flarum traffic-guard:unban 203.0.113.25
```

Check state:

```bash
php flarum traffic-guard:status
```
