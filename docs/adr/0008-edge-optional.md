# ADR 0008: The edge (Cloudflare) is additive

Status: accepted, 2026-10-30. Feature IDs: OPS-02, OPS-03, OPS-09, SEO-03.

## Context

The brief puts Cloudflare in front of both zones (DNS, TLS Full strict, WAF, redirects, Turnstile, later APO). The owner deferred Cloudflare on 2026-10-30; the domains will first point straight at the VPS. The redirect, TLS and rate-limit requirements still have to hold.

## Decision

The origin is complete on its own, and Cloudflare adds a layer without code changes:

- **TLS:** Let's Encrypt on the origin (HTTP-01 while DNS points at the VPS directly; DNS-01 or a Cloudflare Origin CA certificate once proxied, with SSL mode Full strict). HTTPS-only with HSTS after launch.
- **Canonical host and redirects:** Nginx server blocks for `www.cigarholic.com`, `max2lab.com`, `www.max2lab.com` return 301 to `https://cigarholic.com` with path and query preserved, one hop; HTTP → HTTPS likewise. When Cloudflare arrives, its redirect rules do the same at the edge and the Nginx blocks stay as a fallback.
- **Protection:** Nginx blocks `xmlrpc.php` and rate-limits `wp-login.php` and checkout; fail2ban watches Nginx and SSH; ufw allows 22, 80, 443 only. Cloudflare WAF rules later duplicate this at the edge and can then restrict origin access to Cloudflare IPs.
- **Turnstile** works without Cloudflare DNS (any site can use it); the sandbox and CI use Cloudflare's public always-pass test keys.
- **Caching:** full-page cache is an origin concern (Nginx FastCGI cache, bypassed for cart, checkout, account and logged-in users); APO only after cart/checkout bypass is verified.

## Consequences

- `infra/cloudflare/` scripts and `cloudflare.yml` exist and are idempotent, but nothing in the deploy or provisioning path requires them.
- When the owner adds the zones, the order is: lift the registrar hold (A5), add zones, API token and ids (A2), run `cloudflare.yml`, switch nameservers; the Nginx redirects keep working throughout.
