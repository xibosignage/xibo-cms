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

Deployment-time configuration that affects the CMS's security posture.

Every setting below is a PHP variable, set at deployment time and never in the admin UI:

- **Docker** (the supported install): `/var/www/cms/custom/settings-custom.php`. The
  `custom/` directory is a persisted volume. `web/settings.php` lives inside the container
  and is recreated on a fresh one, so edits there are lost.
- **Manual installs**: `web/settings.php`, or `web/settings-custom.php` if your
  `settings.php` includes it — see `MANUAL_INSTALL.md`.

### `$allowLocalNetworkRequests` — leave at the default (`false`)

`SafeClient` (`lib/Helper/Guzzle/SafeClient.php`) blocks outbound HTTP to RFC 1918,
link-local, IPv6 ULA and cloud-metadata addresses. That is the CMS's SSRF defence.

Setting `$allowLocalNetworkRequests = true;` removes it for every outbound request. The CMS
can then reach internal services and the AWS/GCP/Azure metadata endpoints, which makes any
admin-settable URL — connector service URL, news feed URL, XMR address — a pivot into your
internal network. Don't enable it in production.

### `$whitelistHosts` — strongly recommended for production

The CMS builds off-system URLs from the request's `Host` header: password-reset links, the
`cmsAddress` sent to the Xibo auth service, the PWA player manifest. A forged `Host` gives a
forged link.

Set it to the hostnames the CMS is reachable under:

```php
$whitelistHosts = 'cms.example.com,cms-staging.example.com';
```

`HttpsDetect::getHost()` then rejects any other `Host` and substitutes the first entry. Treat
this as required in production.

### `$trustedProxyIps` — required if the CMS sits behind a reverse proxy

The client IP keys login/2FA/password-reset rate limiting and is recorded in audit logs,
session history and XMDS display logs. By default it is the TCP peer address; no
forwarded-for header is trusted, so a direct client cannot spoof it.

Behind a TLS-terminating reverse proxy, load balancer or CDN, set `$trustedProxyIps` to
**the address your proxy connects to the CMS from** — its source address, not the backend
addresses configured in the proxy:

```php
$trustedProxyIps = '10.0.0.5,172.18.0.0/16';
```

Exact IPs, CIDR ranges and wildcards all work, comma-separated, matched by
`Xibo\Helper\IpTrust`. The first field (`%h`) of the CMS web server's access log is the
address to use.

Never use a wildcard or a public range. Every listed address may assert any client IP, so
widening the list hands out an unlimited rate-limit bypass and lets audit IPs be chosen
freely.

Only `X-Forwarded-For` is trusted — not `Forwarded`, `X-Real-IP` or `X-Cluster-Client-Ip`.
Make sure your proxy sets `X-Forwarded-For` itself rather than relaying the client's value.

This is the only setting that grants trust for rate limiting and audit IPs.
`WHITELIST_LOAD_BALANCERS` does not — see below.

**Unset, behind a proxy:** every user resolves to the proxy's address and shares one
rate-limit bucket, so one user's failed logins can rate-limit the rest. Setting the correct
value fixes it. `WHITELIST_LOAD_BALANCERS` makes no difference here.

**Wrong value, with `FORCE_HTTPS` on:** the CMS reads the request as plain HTTP and
redirects to `https://`, the proxy forwards that back over HTTP, and the browser loops.
Correct the address rather than widening the list.

### Why `WHITELIST_LOAD_BALANCERS` is HTTPS-detection only

`WHITELIST_LOAD_BALANCERS` (Settings > Network) predates `$trustedProxyIps` and only ever
decided whether to trust `X-Forwarded-Proto` for HSTS. It still feeds only
`getHttpsDetectionTrustedProxyIpList()`, never `getTrustedProxyIpList()`, rate limiting or
audit IPs.

It is editable by any super-admin.

### How `X-Forwarded-Proto` (HTTPS detection) is trusted

`HttpsDetect::isHttpsTrusted()` decides the CSRF cookie's `Secure` flag, off-system URL
generation (`getScheme()`/`getPort()`/`getRootUrl()`/`getBaseUrl()`), HSTS issuance and the
`FORCE_HTTPS` redirect in `State.php`.

A real `$_SERVER['HTTPS']` always wins. Otherwise `X-Forwarded-Proto: https` is honoured
only from an address on `$trustedProxyIps` **or** `WHITELIST_LOAD_BALANCERS`. Set either,
and no other address can assert `https`.

**With neither set** there is nothing to check a claim against, so `https` is trusted from
any address rather than the install being read as plain HTTP. Each caller fails safe if that
claim is forged: browsers reject a `Secure` cookie over non-TLS, an `https://` link is never
weaker than `http://`, browsers ignore HSTS over non-TLS, and skipping the `FORCE_HTTPS`
redirect only affects the attacker's own request.

This fallback covers HTTPS detection only.