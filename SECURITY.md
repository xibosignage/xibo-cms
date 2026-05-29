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