# TVAK Recommendation Engine – Technical QA & Diagnostic Report

> **Document Status:** Temporary Diagnostic & Code Audit Report  
> **Target System:** TVAK Beauty Kit Plugin (`tvak-beauty-kit`)  
> **Reference Documentation:** `docs/gemini.md`, `docs/rules.md`, `docs/error_solution.md`  
> **Code Modification Status:** **Zero Code Modifications Made** (Diagnostic Report Only)

---

## 1. Executive Summary

This report covers five investigated issues. Issues 1–4 were from the previous QA round. **Issues 5 and 6 are new**, raised from the screenshot showing all WooCommerce attributes appearing in Master Data even after filtering was introduced, and the WooCommerce compatibility warning banner appearing on the Variant Matrix admin page.

---

## 2. Deep-Dive Diagnostic & Root Cause Analysis

### 🔍 Issue 1: Shade color codes showing `#D4AF37` (Default Gold)

#### Root Cause:
1. WooCommerce local/custom product attributes don't store terms in `wp_terms` or `wp_termmeta` tables.
2. Custom swatch plugins save color data in parent product postmeta as serialized arrays (`_swatch_data`, `_wcvs_swatch_data`), not in term meta.
3. `Tvak_Shade_Sync::sync_wc_variation_to_tvak()` only looks up HEX from `get_term_meta()` and variation-level postmeta (`color`, `hex`, `swatch`). No parser for parent product serialized swatch arrays exists.
4. When nothing matches `^#[0-9A-Fa-f]{3,6}$`, the fallback returns `#D4AF37`.

---

### 🔍 Issue 2: Order column showing `0`

#### Root Cause:
1. `wp_tvak_product_shades.sort_order` defaults to `0` in the schema.
2. `Tvak_Shade_Sync::sync_wc_variation_to_tvak()` does not read `get_post($variation_id)->menu_order` (the WooCommerce drag-sort position for variations).
3. `Tvak_Product_Shade::save_shade()` receives no `sort_order` and writes `0` for all auto-synced rows.

---

### 🔍 Issue 3: Editing / Deleting in TVAK and WooCommerce sync behavior

#### Root Cause:
- Editing a shade in TVAK updates price/stock in WooCommerce via `sync_tvak_shade_to_wc()`, but does NOT rewrite variation post title or `_thumbnail_id`.
- Deleting a shade in TVAK removes the TVAK row only. WooCommerce variation post is untouched and will re-appear on the next catalog sync.

---

### 🔍 Issue 4: WooCommerce variation/image changes and recommendation image reflection

#### Root Cause:
- When a variation image changes in WooCommerce, `woocommerce_save_product_variation` hook fires, TVAK syncs the new image URL into `wp_tvak_product_shades`. Frontend cards swap images from this table dynamically.
- When image is changed in TVAK: updates `wp_tvak_product_shades` only. WooCommerce `_thumbnail_id` is not overwritten (1-way flow: WC → TVAK).

---

---
## ⚠️ NEW ISSUES (Raised 2026-07-31)
---

### 🔍 Issue 5: All WooCommerce Catalog Attributes Still Appearing in Master Data Admin Page

#### **Observed Behavior:**
The screenshot shows the `TVAK Engine → Master Data` admin page displaying ALL registered attributes — including catalog noise like `Color`, `Skin Type` (WC version), `Skin Tone`, `product-type`, `Size`, `Skin Concerns` (WC version), `skin-type`, `color` — not just the three quiz profile attributes (`Skin Type`, `Skin Tone`, `Skin Concerns` from TVAK master).

#### **Root Cause — Layer 1: `render_master_data_page()` calls `get_attributes(false)`**

**File:** `includes/admin/class-tvak-admin.php` **Line 113:**
```php
$attributes = Tvak_Master_Data::get_attributes(false);
```

The Master Data page uses `get_attributes(false)` — which fetches **all** records from `wp_tvak_master_attributes` with **no filtering** (`false` = include inactive). It does not call `get_quiz_attributes()` or `get_matrix_attributes()`. All rows seeded by `sync_wc_master_data()` during activation are retrieved and rendered.

#### **Root Cause — Layer 2: `sync_wc_master_data()` pumps WooCommerce catalog attributes into `wp_tvak_master_attributes`**

**File:** `includes/class-tvak-db.php` **Lines 385–476:**

Every time `seed_defaults()` is called (on activation and on every page load if DB version mismatches), the method `sync_wc_master_data()` loops through every registered WooCommerce global attribute taxonomy (`wc_get_attribute_taxonomies()`) and inserts each one into `wp_tvak_master_attributes` via `Tvak_Master_Data::save_attribute()`.

This is intentional for catalog reference, but the Master Data admin UI was never updated to filter catalog vs. quiz entries. The page renders everything without distinction.

#### **Root Cause — Layer 3: The `is_quiz_attribute()` skip guard only prevents re-insertion of quiz attributes**

**File:** `includes/class-tvak-db.php` **Lines 594–601:**
```php
private static function is_quiz_attribute(string $attribute_code): bool {
    return (bool) $wpdb->get_var(
        $wpdb->prepare("SELECT attribute_id FROM {$table} WHERE attribute_code = %s AND is_quiz_question = 1 LIMIT 1", ...)
    );
}
```

This only prevents quiz attributes from being overwritten during `sync_wc_master_data()`. It does NOT prevent the catalog attributes from landing in the master table. Catalog attributes (`Color`, `Size`, `product-type`, etc.) are written to `wp_tvak_master_attributes` with `is_quiz_question = 0`.

#### **Root Cause — Layer 4: `get_matrix_attributes()` filter is applied to Variant Matrix — but NOT to Master Data**

The filtering added last session (`get_matrix_attributes()`) was correctly wired to the Variant Matrix page (line 746), but `render_master_data_page()` on line 113 was not updated. So:
- Variant Matrix page ✅ now filters correctly
- Master Data page ❌ still calls `get_attributes(false)` — shows everything including WooCommerce catalog clutter

#### **What Needs to Change (plan only, no fix yet):**
1. `render_master_data_page()` should call `get_attributes(false)` to show ALL for admin management (this is actually intentional — admin needs to see all to manage them). The real problem is the **UI presentation**: it groups everything without clearly separating "Quiz Profile Attributes" from "WooCommerce Catalog Attributes". The badge label `CATALOG ATTRIBUTE` vs `QUIZ QUESTION` exists (line 258 in admin), but the page still overwhelms the admin.
2. The ideal solution is to split the Master Data page into two sections: a top section "Quiz Profile Attributes" and a collapsed/secondary section "WooCommerce Catalog Reference Attributes (read-only)".
3. The `get_attributes(false)` call is correct for the full admin view. The filtering (`get_quiz_attributes`, `get_matrix_attributes`) is correctly reserved for frontend quiz and variant matrix forms.

---

### 🔍 Issue 6: WooCommerce Compatibility Warning Banner on Variant Matrix Page

#### **Observed Behavior:**
A red warning banner appears at the top of `TVAK Engine → Variant Matrix`:  
> *"WooCommerce has detected that some of your active plugins are incompatible with currently enabled WooCommerce features. Please review the details."*

#### **Root Cause — HPOS (High-Performance Order Storage) Compatibility Not Declared**

WooCommerce since version 7.1 introduced **HPOS (High-Performance Order Storage)** — a new database architecture where orders are stored in dedicated custom tables (`wc_orders`, `wc_order_items`, etc.) instead of WordPress `wp_posts` and `wp_postmeta`.

WooCommerce requires any plugin that interacts with orders to explicitly declare compatibility using:
```php
\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
```

**Evidence of the gap — searching entire plugin codebase for `FeaturesUtil`, `declare_compatibility`, `custom_order_tables`:**  
→ **Zero results found.** The plugin does not declare HPOS compatibility anywhere.

#### **Why This Triggers the Warning:**
When WooCommerce HPOS is enabled on the live site but a plugin has not declared `declare_compatibility('custom_order_tables', ...)`, WooCommerce shows this global incompatibility banner across ALL admin pages — including TVAK admin sub-pages like Variant Matrix.

#### **Secondary Root Cause — `attach_line_item_meta` and `attach_order_meta` Use Legacy `$order` Object**

**File:** `includes/class-tvak-woocommerce.php` **Lines 284–318:**

```php
add_action('woocommerce_checkout_create_order_line_item', [__CLASS__, 'attach_line_item_meta'], 10, 4);
add_action('woocommerce_checkout_order_processed', [__CLASS__, 'attach_order_meta'], 10, 3);
```

These hooks receive `$order` as a `WC_Order` object and call `$order->update_meta_data()` and `$order->save()`. This pattern is compatible with HPOS in principle, but **WooCommerce still requires the explicit declaration** regardless — the banner fires purely from the absence of the `declare_compatibility()` call, not necessarily from actual incompatible code.

#### **Tertiary Root Cause — Plugin Header `WC tested up to: 8.9` May Be Outdated**

**File:** `tvak-beauty-kit.php` **Line 13:**
```
* WC tested up to: 8.9
```

If the live server is running WooCommerce 9.x, the `WC tested up to` header is out of date, which also feeds into WooCommerce's incompatibility flag system and contributes to the warning banner appearing.

#### **What Needs to Change (plan only, no fix yet):**
1. Add an `after_setup_theme` or `before_woocommerce_init` hook in `tvak-beauty-kit.php` to call:
   ```php
   add_action('before_woocommerce_init', function() {
       if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
           \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
               'custom_order_tables',
               __FILE__,
               true
           );
       }
   });
   ```
2. Update plugin header `WC tested up to:` to match the actual installed WooCommerce version on live.

---

## 3. Summary Table

| # | Issue | Location | Root Cause Type | Status |
|---|-------|----------|-----------------|--------|
| 1 | Shade hex defaults to `#D4AF37` | `Tvak_Shade_Sync` | Missing swatch postmeta parser | Reported |
| 2 | Order column shows `0` | `Tvak_Shade_Sync` | `menu_order` not extracted on sync | Reported |
| 3 | TVAK edit/delete vs WooCommerce | `Tvak_Shade_Sync` | 1-way sync only | Reported |
| 4 | Variation image sync behavior | `Tvak_Shade_Sync` | 1-way flow WC → TVAK | Reported |
| 5 | All WC attributes in Master Data | `class-tvak-admin.php` L113, `class-tvak-db.php` L385 | `render_master_data_page` uses `get_attributes(false)` — no quiz filter; `sync_wc_master_data()` writes all WC taxonomies into master table | **NEW** |
| 6 | WooCommerce compatibility warning | `tvak-beauty-kit.php` | No `FeaturesUtil::declare_compatibility('custom_order_tables')` call; outdated `WC tested up to` header | **NEW** |

---

## 4. No Code Changes Made

This report is purely investigative. All findings above are root cause identification only. No source files have been modified as part of this investigation.
