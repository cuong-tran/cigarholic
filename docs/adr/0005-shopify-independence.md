# ADR 0005: Shopify independence

Status: accepted, 2026-10-30. Feature IDs: SHO-01 to SHO-04, INV-03.

## Context

The owner may one day want a Shopify storefront or sync, but the store must never depend on it.

## Decision

WooCommerce is the system of record for products, stock, orders and customers. Any Shopify, inventory-platform or 3PL sync is its own module in `cigarholic-core` behind a feature toggle, built on the shared integration layer (INV-03: queued jobs, retries, idempotency keys, logs, admin status page). The site runs normally with every sync off. The cheapest first step is the Shopify-compatible product CSV export (SHO-02, P1), enabled by ADR 0002's URL structure and ADR 0003's data model.

## Consequences

- Code review rejects any runtime call to Shopify outside a sync module.
- Every sync module has a kill switch and an idempotent replay.
