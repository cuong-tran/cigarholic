# ADR 0003: Catalog data model

Status: accepted, 2026-10-30. Feature IDs: CAT-01, CAT-02, CAT-03, CAT-04, CAT-09, CAT-10, CMP-06, CMP-11, INV-01.

## Context

Spec tables, filters, feeds, inventory sync, customs paperwork and a future Shopify export all read the same product data. It has to be set once, before product entry, with the same attribute names on every product. Sample data must never be mistaken for the real catalog.

## Decision

- **Categories** (`product_cat`, slugs fixed): Desktop Humidors, Cabinet Humidors, Travel Humidors, Cigar Boxes, Humidor Accessories, Gift Sets. Custom taxonomy **`occasion`** (Father's Day, Groomsmen, Corporate, Holiday, ...) for gift merchandising.
- **Global attributes** (WooCommerce `pa_*`), for everything filterable: capacity range, exterior wood, interior lining, humidification, hygrometer, finish, lock, seal. Only attributes that change the SKU (finish, size) become variations.
- **Numeric meta** for exact values: cigar capacity, maximum cigar length, maximum ring gauge, interior L × W × H; core weight and dimensions hold the exterior size and the shipping weight; core SKU and GTIN; package dimensions in core shipping fields.
- **Compliance fields (CMP-11), private:** wood species as genus + species for every wood including lining and veneer, country of harvest, composite-wood flag (plywood, MDF, particleboard: TSCA Title VI), HTS code. Plus the per-product Prop 65 toggle (CMP-06, D-08).
- **Units:** stored in inches and pounds (the warehouse and carriers are US), displayed as inches + cm and lb + kg by the spec-table block (CAT-04).
- **SKU scheme** `CH-{type}-{capacity}-{wood}-{finish}` (for example `CH-DESK-050-WAL-MATTE`), unique per product or variation, validated on save and by `wp cigarholic catalog:check`.
- **CSV import template** (CAT-10) with every column above, so the manager edits in bulk with the WooCommerce importer.
- **Sample data** carries the SKU prefix `SAMPLE-` and "Sample" in the title; `wp cigarholic catalog:check` refuses to pass on production while any exists.

## Consequences

- Filters (NAV-08), comparison (CAT-20), capacity guide (CAT-16) and AI recommendations (AI-09) need no new data later.
- The customs broker gets a per-product export of species, harvest country, composite flag and HTS code from the same fields (Lacey Act, TSCA, L-11).
- Product page content follows one pattern: summary → spec table → what's in the box → care & seasoning → shipping & returns → "Handcrafted in Vietnam".
