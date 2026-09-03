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
front of the CMS, set `$trustedProxyIps` in `web/settings.php` (or
`web/settings-custom.php`) to the exact IP address(es)/CIDR range(s) of that
proxy — the only hop(s) whose `X-Forwarded-For` header you want the CMS to
trust:

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

**This is the *only* setting that grants trust for rate limiting and
audit-IP resolution.** The admin-editable **Settings > Network > Whitelist
Load Balancers** field (`WHITELIST_LOAD_BALANCERS`) is deliberately *not*
combined into this trust list — see "Why `WHITELIST_LOAD_BALANCERS` is
HTTPS-detection only" below. If you're relying on rate limiting actually
limiting anything behind your reverse proxy, `$trustedProxyIps` is the
setting you need — configuring `WHITELIST_LOAD_BALANCERS` alone does not
cover it, even though it did in a prior revision of this fix.

Exact IPs, CIDR ranges, and wildcard entries all work consistently wherever
a trust list is consulted (`Xibo\Helper\IpTrust`) — rate limiting, HSTS
issuance, and the client IP recorded in session/display audit logs all use
the same matcher, just against different source lists (see below).

**Only `X-Forwarded-For` is trusted for client-IP resolution — no other
forwarded-for style header.** `TrustedProxyIpAddress`, `Session::getIp()`,
and `Xmds\Soap::getIp()` deliberately check exactly this one header, not a
priority list of several (`Forwarded`, `X-Forwarded`, `X-Cluster-Client-Ip`,
`Client-Ip`, etc.). Checking several and using whichever is present first
would let a client whose traffic genuinely passes through your real,
correctly-configured trusted proxy still fully control the resolved IP —
by simply also sending a raw header your proxy doesn't happen to manage
(e.g. `Forwarded: for=<anything>`; most proxies never touch that header even
when they correctly manage `X-Forwarded-For`). That header reaches the app
unmodified, gets picked ahead of the correctly-set `X-Forwarded-For`, and
is indistinguishable in shape from a genuine single-hop chain — the exploit
doesn't require bypassing your proxy at all. Ensure your reverse proxy sets
`X-Forwarded-For` correctly (appends, doesn't just relay the client's own
value) — that's the only header this trust list needs to protect.

**Upgrading an existing install that sits behind a reverse proxy?** If you
haven't set `$trustedProxyIps`, every user behind that proxy will share a
single resolved IP (the proxy's own address), which means they'll also
share one rate-limit bucket — one user's failed logins could cause others
behind the same proxy to see login/2FA/password-reset requests
rate-limited. This isn't an outage and resolves itself as soon as you set
`$trustedProxyIps` to your proxy's address — **this is true even if you
already have `WHITELIST_LOAD_BALANCERS` set**, since that setting no longer
covers rate limiting (see below).

### Why `WHITELIST_LOAD_BALANCERS` is HTTPS-detection only

`WHITELIST_LOAD_BALANCERS` (Settings > Network) predates this whole
`$trustedProxyIps` mechanism and was originally used only to decide whether
to trust `X-Forwarded-Proto` for HSTS header issuance. A prior revision of
this fix folded it into the same trust list as `$trustedProxyIps`, so that
an operator who'd already set it for HSTS got rate-limiting protection for
free. That was reverted — `WHITELIST_LOAD_BALANCERS` now feeds only the
HTTPS-detection trust list described below (`ConfigServiceInterface::
getHttpsDetectionTrustedProxyIpList()`), never
`getTrustedProxyIpList()`/rate limiting/audit-IP resolution.

The reason is the same one that already keeps `$allowLocalNetworkRequests`
and `$whitelistHosts` settings.php-only, stated explicitly above for
`$whitelistHosts`: *"an attacker who compromises an admin account would
otherwise simply whitelist their own host."* `WHITELIST_LOAD_BALANCERS` is
editable from the Settings UI by any super-admin, with no filesystem access
needed and no obvious connection — to an admin auditing it later — to
rate-limit bypass. A compromised or malicious super-admin session (a stolen
cookie, a since-patched privilege-escalation bug) is often transient, but
setting this field is a one-time, quiet, durable action: once set, it grants
a standing capability — bypass login/2FA/password-reset rate limiting and
spoof the audit-IP for every user, usable at leisure from the attacker's own
machine, independent of still holding the admin session. That turns a
possibly-transient compromise into a persistent backdoor, and — since this
is the exact protection this mechanism exists to provide — undermines it via
a second, quieter, already-existing path. `$trustedProxyIps` doesn't have
this problem: it requires filesystem access to the CMS host, the same bar
already applied to `$allowLocalNetworkRequests`/`$whitelistHosts`.

### How `X-Forwarded-Proto` (HTTPS detection) is trusted

`Xibo\Helper\HttpsDetect::isHttpsTrusted()` decides the CSRF cookie's
`Secure` flag (`CsrfGuard::issueToken()`), off-system URL generation
(`getScheme()`/`getPort()`/`getRootUrl()`/`getBaseUrl()`, e.g. the
password-reset link), HSTS issuance (`isShouldIssueSts()`), and
`State.php`'s `FORCE_HTTPS` redirect. A real `$_SERVER['HTTPS']` always wins
outright — that's not client-controlled. Otherwise, an `X-Forwarded-Proto:
https` claim is honoured **only if the connecting address is on the
HTTPS-detection trust list** — `$trustedProxyIps` *and*
`WHITELIST_LOAD_BALANCERS` combined (unlike the rate-limiting/audit-IP list
above, which excludes `WHITELIST_LOAD_BALANCERS` — see "Why
`WHITELIST_LOAD_BALANCERS` is HTTPS-detection only"), matched via
`Xibo\Helper\IpTrust`. Once you configure either setting, a source that
isn't on it can no longer assert "https" here at all — the same hardening
as the `X-Forwarded-For` case above, just with a slightly broader trust
list appropriate to this lower-stakes purpose.

**The one deliberate exception:** when neither setting is configured yet —
there's nothing to verify a claim against — this falls back to trusting
`X-Forwarded-Proto` from any address (`HttpsDetect::isHttps()`), rather than
treating an unconfigured install as plain HTTP. That fallback is safe
specifically because every caller of it fails safe when a spoofed claim
pushes detection *towards* "https" on a connection that's really plain
HTTP:

- **CSRF cookie's `Secure` flag**: a browser refuses to store a cookie
  marked `Secure` over a connection it knows isn't TLS. Spoofing can only
  make the cookie fail to be set — never cause it to be sent insecurely.
- **URL generation**: an `https://` link is never a weaker choice than an
  `http://` one. Spoofing can only make a generated link *more* secure than
  it needed to be.
- **HSTS issuance**: browsers ignore a `Strict-Transport-Security` header
  received over a connection they know isn't TLS, so a spoofed claim over a
  genuinely-plain-HTTP connection has no effect on that connection.
- **`FORCE_HTTPS` redirect**: an attacker can only spoof this on their own
  request, skipping the redirect for themselves.

Self-hosted instances running <=4.5.1 without trustedProxyIps configured are
either behind a reverse proxy (usually for TLS termination) and will fail safe
(no HTTP downgrade allowed). Instances running without a reverse proxy are
already running non-recommended and allow HTTP, so no additional vulnerability
remains.
