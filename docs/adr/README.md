# Architecture decision records

One file per decision, numbered, never rewritten: a superseded decision gets a new ADR that links back. Format: context, decision, consequences. Feature IDs from `docs/FEATURES.md` where they apply.

| # | Decision | Status |
|---|---|---|
| [0001](0001-platform.md) | WordPress + WooCommerce on Bedrock, PHP 8.5 VPS with 8.3-compatible code, Compose runtime, raw VPS, Composer-managed updates | accepted |
| [0002](0002-urls.md) | URL structure: `/products/`, `/collections/`, `/journal/` | accepted |
| [0003](0003-catalog-data-model.md) | Catalog data model, SKU scheme, compliance fields, sample-data rule | accepted |
| [0004](0004-payments.md) | Stripe + PayPal official plugins, webhook truth, idempotent order code, Stripe Tax | accepted |
| [0005](0005-shopify-independence.md) | WooCommerce is the system of record; Shopify never a runtime dependency | accepted |
| [0006](0006-secrets-channel.md) | One secrets channel: GitHub secrets → provisioning and deploy workflows → server `.env` | accepted |
| [0007](0007-dev-tools-as-phars.md) | PHPStan, PHPUnit and Deployer as pinned phars, not Composer packages | accepted |
| [0008](0008-edge-optional.md) | Cloudflare is additive: the origin alone must serve TLS, redirects and rate limits | accepted |
| [0009](0009-brand-system.md) | Brand system as semantic theme tokens with contrast rules, a Forest section style, self-hosted Young Serif and Hanken Grotesk | accepted |
| [0010](0010-architecture-review-2026-11.md) | Architecture review of 2026-11-13: keep the architecture and the design, fix the listed gaps in the order of `docs/PLAN.md` | accepted |
