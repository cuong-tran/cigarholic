# Catalog data model

How product data is structured in Cigarholic and how the manager works with it. Decision: `docs/adr/0003-catalog-data-model.md`; URLs: `docs/adr/0002-urls.md`. Code: `web/app/mu-plugins/cigarholic-core/src/Catalog/`. Feature IDs: CAT-02, CAT-03, CAT-04, CAT-09, CAT-10, CMP-06, CMP-11, INV-01.

The same fields on every product feed the spec table (CAT-04), the filters (NAV-08), the comparison tool (CAT-20), the capacity guide (CAT-16), the customs paperwork (CMP-11) and a future Shopify export (SHO-02). Set them once, from the first product.

## 1. Categories and occasions

| Category | Slug (fixed) | URL | SKU type |
|---|---|---|---|
| Desktop Humidors | `desktop-humidors` | `/collections/desktop-humidors/` | `DESK` |
| Cabinet Humidors | `cabinet-humidors` | `/collections/cabinet-humidors/` | `CAB` |
| Travel Humidors | `travel-humidors` | `/collections/travel-humidors/` | `TRAV` |
| Cigar Boxes | `cigar-boxes` | `/collections/cigar-boxes/` | `BOX` |
| Humidor Accessories | `humidor-accessories` | `/collections/humidor-accessories/` | `ACC` |
| Gift Sets | `gift-sets` | `/collections/gift-sets/` | `GIFT` |

Categories are flat and their slugs must stay unique across the whole tree (WordPress would otherwise append `-2` or the parent slug, and the URL changes). The manager may add categories; `wp cigarholic catalog check` reports slug or name clashes. `/collections/` comes from the WooCommerce permalink setting `category_base = collections` (provisioning).

**Occasions** (`occasion` taxonomy, `/occasions/<slug>/`, shown in the product editor and the REST API) tag products for gift merchandising: Father's Day, Groomsmen, Corporate, Holiday, Anniversary, Retirement. Add more in Products > Occasions.

## 2. Global attributes (filters and spec table)

WooCommerce global attributes (`pa_*`), created idempotently on `init` and by `wp cigarholic catalog ensure`. Use **only these** on products (Attributes tab > pick the global attribute), never a custom per-product attribute, so filters work across the catalog.

| Attribute | Slug | Terms (placeholders until OWNER-ACTIONS B12 arrives) |
|---|---|---|
| Capacity range | `capacity-range` | Up to 25 cigars, 25-50 cigars, 50-100 cigars, 100-200 cigars, 200+ cigars |
| Exterior wood | `exterior-wood` | Walnut, Mahogany, Oak, Cherry, Maple, Acacia |
| Interior lining | `interior-lining` | Spanish cedar, Mahogany, Okoume |
| Humidification | `humidification` | Humidifier jar, Humidifier bar, Boveda packs, Electronic, None |
| Hygrometer | `hygrometer` | Analog, Digital, None |
| Finish | `finish` | Matte lacquer, Gloss lacquer, Oil, Unfinished |
| Lock | `lock` | None, Key lock, Combination |
| Seal | `seal` | Spanish cedar lip, Felt, Magnetic, Gasket |

**Variations.** Only attributes that change the SKU become variations: **finish** and **size**. Everything else stays a plain attribute ("Visible on the product page" checked, "Used for variations" unchecked). A product sold in two finishes is one variable product with a Finish variation per finish, each with its own SKU, price, stock and image (CAT-01). A product in two sizes with different capacity is two products (specifications are per product, see section 3).

The spec table lists every visible global attribute; untick "Visible on the product page" to hide one.

## 3. Specifications (product data tab "Specifications")

Exact values for the spec table. **Stored in inches**; the block shows inches + centimetres. Shown on the storefront.

| Field | Meta key | Type | Notes |
|---|---|---|---|
| Cigar capacity | `_ch_capacity` | integer | Count of 50 ring gauge cigars. Required for every humidor (desktop, cabinet, travel). |
| Max cigar length (in) | `_ch_max_cigar_length_in` | decimal | Longest cigar that fits. |
| Max ring gauge | `_ch_max_ring_gauge` | integer | |
| Interior length / width / height (in) | `_ch_interior_length_in`, `_ch_interior_width_in`, `_ch_interior_height_in` | decimal | Inside measurements. All three or the row is not shown. |

**Exterior size and shipping weight** are WooCommerce's own fields on the Shipping tab (Weight in **lbs**, Length / Width / Height in **in**; the store units are set by provisioning). GTIN/UPC goes in the Inventory tab field "GTIN, UPC, EAN, or ISBN" (CAT-09). The spec table converts these to lb + kg and in + cm.

## 4. Compliance fields (private, never shown on the storefront)

Import paperwork for the customs broker (Lacey Act declaration, TSCA Title VI, HTS classification). Same tab, second group. CMP-11.

| Field | Meta key | Type | How to fill it |
|---|---|---|---|
| Wood species | `_ch_wood_species` | text, one line per wood | `Part: Genus species, Country`, for **every** wood including lining, veneer and trays. Example: `Exterior: Juglans nigra, United States` / `Lining and dividers: Cedrela odorata, Brazil`. |
| Country of harvest | `_ch_harvest_country` | text | Where the wood grew, not where the piece was made. Several: `United States; Brazil`. |
| Contains composite wood | `_ch_composite_wood` | yes / no | Plywood, MDF or particleboard anywhere in the piece (TSCA Title VI formaldehyde rules). |
| HTS code | `_ch_hts_code` | digits and dots | US Harmonized Tariff Schedule code, for example `4420.90.6500` for a lined humidor. The broker confirms the code; the samples are placeholders. |
| California Prop 65 warning | `_ch_prop65` | yes / no | **Off by default (D-08).** When on, the standard Prop 65 warning paragraph is printed below the price on the product page: "WARNING: This product can expose you to chemicals including wood dust, which is known to the State of California to cause cancer. For more information go to www.P65Warnings.ca.gov." The chemical defaults to wood dust; the filter `cigarholic_prop65_chemicals` changes it once the workshop's determination (L-09) is in. |

Species and harvest country are required on every product except accessories without an exterior wood or interior lining attribute (a hygrometer). Export them for the broker with WooCommerce > Products > Export, "Export custom meta" ticked.

## 5. SKU scheme (INV-01)

`CH-{TYPE}-{CAPACITY}-{WOOD}-{FINISH}`, unique per product **and** per variation.

| Segment | Values |
|---|---|
| TYPE | `DESK`, `CAB`, `TRAV`, `BOX`, `ACC`, `GIFT` (section 1) |
| CAPACITY | three digits, cigars: `050`, `100`, `300`; `000` for accessories |
| WOOD | 3 to 5 capital letters: `WAL`, `MAH`, `OAK`, `CHE`, `MAPLE`, `ACA`, `CED`; `NONE` when there is no wood |
| FINISH | 3 to 5 capital letters: `MATTE`, `GLOSS`, `OIL`, `UNFIN`; `NONE` |

Examples: `CH-DESK-050-WAL-MATTE`, `CH-CAB-300-OAK-MATTE`, `CH-ACC-000-NONE-NONE`. The parent of a variable product uses `VAR` for the segment that varies (`CH-DESK-050-WAL-VAR`); its variations carry their own value (`CH-DESK-050-WAL-MATTE`, `CH-DESK-050-WAL-GLOSS`).

Sample data uses `SAMPLE-` in place of `CH-` (`SAMPLE-DESK-050-WAL-MATTE`) and "Sample" in the title. It is valid everywhere except production, where `wp cigarholic catalog check` fails while any exists.

The product form validates on save: an invalid or already used SKU shows an error notice and the previously saved SKU is kept (the rest of the product saves normally). The CSV importer and the REST API only enforce uniqueness; the check command catches the scheme afterwards.

## 6. Spec table block (CAT-04)

`cigarholic/spec-table` (in the theme's `product-content` pattern) renders one accessible `<table>` with a hidden caption and `<th scope="row">` labels, in this order: Capacity, Fits up to (length in + cm, ring gauge), Exterior dimensions (L × W × H, in + cm, from the Shipping tab), Interior dimensions (in + cm), Weight (lb + kg), then every visible global attribute. Both unit systems always; nothing is rendered for a product without data. WooCommerce's "Additional information" tab is hidden on products where the table has content.

## 7. Bulk import and export (CAT-10)

Template: **`docs/templates/products-import-template.csv`** (one row per sample product type plus the two variation rows of the variable product). Open it in a spreadsheet, replace the rows, save as CSV (UTF-8), then **WooCommerce > Products > Import**, upload, keep the automatic column mapping (every template column is recognised), run. Tick "Update existing products" to change products that already exist (matched by SKU or ID). To export, **WooCommerce > Products > Export** with "Export custom meta" ticked; the file re-imports with the same mapping.

Column names that import automatically (verified by `tests/phpunit/CatalogCsvTest.php` against `WC_Product_CSV_Importer` with the importer's own automatic mapping):

| Column | Field | Value |
|---|---|---|
| `ID` | product ID | blank for new products |
| `Type` | type | `simple`, `variable`, `variation` |
| `SKU` | SKU | section 5 |
| `GTIN, UPC, EAN, or ISBN` | GTIN | optional |
| `Name`, `Published`, `Is featured?`, `Visibility in catalog`, `Short description`, `Description` | core fields | `Published` 1, `Visibility in catalog` `visible` |
| `Tax status`, `Tax class` | tax | `taxable`, blank |
| `In stock?`, `Stock`, `Backorders allowed?`, `Low stock amount`, `Sold individually?` | inventory | `1` / `0`, numbers |
| `Weight (lbs)`, `Length (in)`, `Width (in)`, `Height (in)` | shipping weight and exterior size | numbers; the unit in the header must match the store units (lbs, in) |
| `Allow customer reviews?`, `Regular price`, `Sale price` | | |
| `Categories` | product category | the category **name**: `Desktop Humidors` |
| `Tags` | product tags | optional |
| `Occasions` | occasion taxonomy | term names, comma-separated: `Father's Day, Groomsmen` (added by cigarholic-core) |
| `Shipping class` | shipping class | the class **name**: `Humidor`, `Oversized humidor`, `Accessory` |
| `Images` | images | URLs (or file names already in the media library), comma-separated; blank in the template |
| `Parent` | parent of a variation | the parent's SKU (or `id:123`) |
| `Position` | menu order | |
| `Attribute N name`, `Attribute N value(s)`, `Attribute N visible`, `Attribute N global`, `Attribute N default` (N = 1 to 8) | global attributes | name as in section 2 (`Capacity range`), term names comma-separated (`Matte lacquer, Gloss lacquer`), `visible` 1, `global` 1. On a variable parent, fill `Attribute N default` for the attribute that varies: that marks it as the variation attribute. On a variation row, give only that attribute with its single value. |
| `Meta: _ch_capacity`, `Meta: _ch_max_cigar_length_in`, `Meta: _ch_max_ring_gauge`, `Meta: _ch_interior_length_in`, `Meta: _ch_interior_width_in`, `Meta: _ch_interior_height_in` | specifications (section 3) | numbers in inches |
| `Meta: _ch_wood_species`, `Meta: _ch_harvest_country`, `Meta: _ch_composite_wood`, `Meta: _ch_hts_code`, `Meta: _ch_prop65` | compliance (section 4) | species: one wood per line inside the quoted cell; `yes` / `no` |

Variations: one row per variation after the parent, `Type` `variation`, `Parent` the parent SKU, own `SKU`, `Regular price`, `Stock`; weight and dimensions blank unless they differ from the parent.

## 8. Checks and sample data

```bash
wp cigarholic catalog check            # exit 1 on any problem; --format=json|csv|yaml|count
wp cigarholic catalog ensure           # categories, occasions, attributes and terms
wp cigarholic seed                     # sample catalog (development and staging only)
```

`catalog check` (the brief's `catalog:check`) reports: `missing_sku`, `invalid_sku`, `duplicate_sku`, `missing_weight`, `missing_dimensions`, `missing_wood_species`, `missing_harvest_country`, `missing_image`, `missing_image_alt`, `missing_capacity` (humidors), `duplicate_category_slug`, `duplicate_category_name`, and on production `sample_data`. Store units other than lbs/in are warnings. Products of every status except trash are checked, variations through their parent (a variation falls back to the parent's weight and dimensions).

`seed` creates seven `SAMPLE-` products across the six categories, one variable (two finish variations), with specifications, attributes, occasions, shipping classes (`humidor`, `oversized-humidor`, `accessory`), compliance fields and a generated placeholder image with alt text. Running it again updates the same products (matched by SKU) and uploads nothing new. One sample (the oak cabinet) has the Prop 65 toggle on to show the warning.

## 9. Open items for the owner

- Attribute term lists (section 2) are placeholders until the material sheet (OWNER-ACTIONS B12) arrives; the manager can add terms in Products > Attributes at any time, and the lists in `DataModel::ATTRIBUTES` are then updated to match.
- HTS codes and the wood species lines of the samples are illustrative; the customs broker confirms the real ones (L-11).
- Prop 65 (D-08): which chemical, if any, the warning must name, per the workshop's finish and material data (L-09).
