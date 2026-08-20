# Security Policy

## Supported Versions
Xibo supports and maintains the current and prior major releases, with updates being provided to the latest minor
release within that.

A full list of support is available on our website: https://xibosignage.com/docs/setup/supported-versions-and-environments

## Reporting a Vulnerability
Please report (suspected) security vulnerabilities using the Security tab in this repository. 

You will receive a response from us within 48 hours. If the issue is confirmed, we will release a patch as soon as
possible depending on complexity but historically within a few days.

## Operator notes — security-relevant settings

These notes cover deployment-time configuration that affects the CMS's security posture.
For developer-facing security architecture (boundary sanitisers, the widget sandbox model,
the SafeClient HTTP wrapper, etc.), see the "Security-sensitive patterns" section in
`CLAUDE.md`.

### `$allowLocalNetworkRequests` — leave at the default (`false`)

The `SafeClient` HTTP wrapper (`lib/Helper/Guzzle/SafeClient.php`) blocks outbound HTTP
requests to RFC 1918 / link-local / IPv6 ULA / cloud-metadata IPs to defend the CMS
against SSRF attacks (e.g. malicious connector URLs, malicious upload URLs).

You can disable that protection by setting `$allowLocalNetworkRequests = true;` in
`web/settings.php` or `web/settings-custom.php`. **Do not do this in production unless
you fully understand the consequences** — it allows the CMS to issue HTTP requests to
internal IP ranges, the AWS / GCP / Azure metadata endpoints, and other internal services
that an attacker controlling any admin-settable URL (connector service URL, news feed
URL, XMR address, etc.) could pivot to.

The setting is intentionally not exposed via any CMS admin UI — flipping it requires
filesystem access to the CMS host. There is no DB-backed override.

### `$whitelistHosts` — strongly recommended for production

When the CMS sends URLs off-system (the password-reset email link, the
`cmsAddress` registration call to the Xibo auth service, the PWA player manifest
embedded in player software packages, etc.), it constructs those URLs from the
incoming request's `Host` header. Without an allow-list, an attacker who can set
that header — e.g. by sending a `POST /login/forgotten-password` with
`Host: attacker.com` — can poison the URL that ends up in the victim's inbox,
turning a password-reset email into a phishing vector that leaks the reset
nonce to attacker-controlled infrastructure.

Set `$whitelistHosts` in `web/settings.php` (or `web/settings-custom.php`) to a
comma-separated list of canonical hostnames that the CMS is reachable under:

```php
$whitelistHosts = 'cms.example.com,cms-staging.example.com';
```

When set, `HttpsDetect::getHost()` rejects any `Host` header that isn't on the
list and falls back to the first listed hostname instead. When unset (the
default), the legacy behaviour is preserved for backward compatibility — but
this leaves the off-system URL construction sites open to Host-header
injection, so production deployments should treat this as a required setting.

Like `$allowLocalNetworkRequests`, this setting is deployment-time only and is
not exposed via any CMS admin UI — an attacker who compromises an admin account
would otherwise simply whitelist their own host.

### `$trustedProxyIps` — required if the CMS sits behind a reverse proxy

The client IP address (used to key login/2FA/password-reset rate limiting, and
recorded in audit logs) is normally just the TCP peer address. By default the
CMS does **not** trust the `X-Forwarded-For` / `Forwarded` / `X-Real-IP`-style
headers at all, so it cannot be spoofed by a directly-connecting client — this
matches the officially-supported Docker deployment, which has no reverse proxy
in front of the app.

If you put your own TLS-terminating reverse proxy, load balancer, or CDN in
front of the CMS, set `$trustedProxyIps` in or `web/settings-custom.php` to
the exact IP address(es)/CIDR range(s) of that proxy — the only hop(s)
whose `X-Forwarded-For` header you want the CMS to trust:

```php
$trustedProxyIps = '10.0.0.5,172.18.0.0/16';
```

When set, the CMS trusts proxy headers **only** from those addresses and reads
the real client IP from the header value they forward. **Never** set this to a
wildcard or a public IP range — anything listed here is implicitly trusted to
assert an arbitrary client IP, which would let an attacker who can reach that
trusted hop (or who spoofs a source IP that matches it) bypass rate limiting
entirely.

Like `$allowLocalNetworkRequests` and `$whitelistHosts`, this setting is
deployment-time only and is not exposed via any CMS admin UI.

This is combined with (not overridden by) the admin-editable **Settings >
Network > Whitelist Load Balancers** field (`WHITELIST_LOAD_BALANCERS`),
which serves the same purpose — the IPs from both are trusted together. If
you already set that field (e.g. so HSTS headers are issued correctly behind
your proxy), it now also covers client-IP resolution for rate limiting with
no extra action.

**Upgrading an existing install that sits behind a reverse proxy?** If you
haven't set either of these, every user behind that proxy will share a
single resolved IP (the proxy's own address) after upgrading, which means
they'll also share one rate-limit bucket — one user's failed logins could
cause others behind the same proxy to see login/2FA/password-reset requests
rate-limited. This isn't an outage and resolves itself as soon as you set
`WHITELIST_LOAD_BALANCERS` (Settings > Network) or `$trustedProxyIps` to
your proxy's address.
