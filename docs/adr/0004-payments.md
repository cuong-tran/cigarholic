# ADR 0004: Payments, tax and order integrity

Status: accepted, 2026-10-30. Feature IDs: PAY-01 to PAY-06, CMP-01, CHK-01, ORD-01.

## Context

Humidors are high-ticket, the business sells no tobacco but is tobacco-adjacent, and the owner wants Stripe and PayPal with the official WooCommerce plugins, not WooPayments. Payment and order code must survive duplicate webhooks, retries and partial failures.

## Decision

- **Gateways:** WooCommerce Stripe Payment Gateway (hosted fields, 3DS, Apple Pay, Google Pay, Link) and WooCommerce PayPal Payments (PayPal, Venmo, Pay Later). Test keys only on staging; live credentials are connected by the owner in production WP admin and never pass through GitHub or an agent. In the sandbox and CI, cart → order → email is exercised with WooCommerce's built-in Cash on delivery / Check gateways.
- **Payment truth comes from gateway webhooks** verified by the official plugins, never from the browser redirect. Webhooks are configured and monitored (PAY-05).
- **Tax:** Stripe Tax for WooCommerce (D-07) with the general tangible-goods tax code on every product; nexus TX (stock) and FL (business address), registrations confirmed by the owner with a CPA. TaxJar is the fallback if staging tests fail.
- **Order code in `cigarholic-core` is idempotent:** idempotency keys, Action Scheduler for anything with side effects, WooCommerce CRUD only, no raw SQL on commerce tables, no `WP_Query` for orders, HPOS compatibility declared. Status transitions and stock reservations are respected, never forced.
- **Checkout:** block cart and checkout, guest checkout on, US-only addresses, phone required, terms checkbox, coupons on, Order Attribution on.
- **Fraud basics:** Stripe Radar defaults, AVS/CVC checks, manual review above a value set by the owner (PAY-06).

## Consequences

- No "gateway-independent payment layer": the two official plugins are used as they are; custom code only reacts to their hooks.
- Refunds full and partial are issued from WP admin and synced to the gateway (PAY-04); tested on staging with the test matrix in the brief (section 9), including a duplicate webhook delivery that must not double-process.
