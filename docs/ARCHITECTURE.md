# Traffic Guard Architecture

## Request flow

```text
HTTP request
    |
    v
Flarum forum / api / admin middleware stack
    |
    v
ClientIpResolver
    |-- REMOTE_ADDR by default
    `-- trusted forwarded header only when the immediate proxy is trusted
    |
    v
RuleEngine: local rules
    |-- IP
    |-- CIDR
    |-- path glob
    `-- user-agent substring
    |
    +--> matching allow => allow immediately
    +--> matching block => return block decision
    |
    v
ThreatLookupService (only when required)
    |-- skip private/reserved IPs
    |-- read cache
    |-- optional proxycheck.io lookup
    `-- normalize provider data
    |
    v
RuleEngine: metadata rules
    |-- country
    `-- ASN
    |
    v
Automatic checks
    |-- Tor
    |-- VPN
    |-- proxy
    |-- hosting/datacenter
    `-- risk score
    |
    v
Decision
    |-- allow => continue Flarum request
    `-- block => JSON error for API or custom HTML response
```

## Why local rules run first

Manual IP/CIDR/path/user-agent decisions do not require a third-party lookup. This keeps manual bans fast and makes allow rules useful as explicit safety overrides.

## Rule precedence

Within a rule phase, matching allow rules win over matching block rules. Rules are loaded by priority descending and then ID ascending. This intentionally makes a specific administrator allowlist a reliable override even when automatic VPN detection would otherwise block the network.

## Provider isolation

Provider-specific response parsing is contained in `src/Provider`. `ThreatLookupService` exposes only normalized fields to the rule engine. Additional providers can be added without changing middleware or template rendering.

Normalized fields are:

- provider
- proxy
- vpn
- tor
- hosting
- risk
- country_code
- country
- asn
- organisation
- type
- cached

## Custom responses

`TemplateRenderer` chooses a response page by decision category or per-rule response override. Dynamic template variables are HTML-escaped. The page HTML itself is administrator-trusted source and is returned without wrapping it in the Flarum frontend.

## Failure behavior

Traffic Guard is designed to avoid taking down Flarum when its own non-policy code fails:

- Unexpected middleware errors fail open.
- Logging errors are ignored for request availability.
- Provider failures default to fail open.
- Provider fail-closed mode is explicitly configurable.
- Admin middleware protection is disabled by default.
- CLI recovery commands remain available from the server shell.

## Static assets

Flarum application middleware only sees requests that enter Flarum/PHP. A CDN or web server may serve `/assets/*`, images, favicons, or other files directly. Whole-domain denial therefore belongs at both layers when required:

```text
CDN/WAF or origin firewall     whole-domain network enforcement
              +
Traffic Guard middleware       Flarum-aware rules/custom responses/logging
```
