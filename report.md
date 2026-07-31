# TVAK Recommendation Engine – Technical QA & Diagnostic Report

> **Document Status:** Temporary Diagnostic & Code Audit Report  
> **Target System:** TVAK Beauty Kit Plugin (`tvak-beauty-kit`)  
> **Reference Documentation:** `docs/gemini.md`, `docs/rules.md`, `docs/error_solution.md`  
> **Code Modification Status:** **Zero Code Modifications Made** (Diagnostic Report Only)

---

## 1. Executive Summary

Based on your inquiry and the provided screenshot empirical evidence, we conducted a code review across `Tvak_Shade_Sync`, `Tvak_Product_Shade`, `Tvak_Variant_Resolver`, and `Tvak_Admin`.

Below is the complete technical diagnostic report detailing the root causes, current system behaviors, image synchronization mechanics, and an action plan for your review.

---

## 2. Deep-Dive Diagnostic & Root Cause Analysis

### 🔍 Issue 1: Why do shade color codes show `#D4AF37` (Default Gold) instead of WooCommerce Swatches?

#### **Observation from Screenshots 1 & 2:**
In the *Configured Shades for this Product* table, shades `Biscuit`, `Soft Beige`, and `SPF 30 - Bisque` display `HEX Code: #D4AF37` (the default gold fallback color) instead of their cosmetic HEX colors.

#### **Root Cause Analysis:**
1. **Local/Custom Product Attributes vs. Global Taxonomy Attributes:**
   - In WooCommerce, variation attributes can be created in two ways:
     - **Global Taxonomy Attributes** (under *Products → Attributes*, e.g., `pa_color` or `pa_shade`), which store terms in WordPress database tables `wp_terms` and `wp_termmeta`.
     - **Custom / Local Attributes** (typed directly on the Product Edit screen under the Attributes tab as plain text like `"Biscuit | Soft Beige"`).
   - If a product uses local text attributes, WordPress **does not create `wp_terms` or `wp_termmeta` records**. 
2. **Plugin-Specific "Swatches" Tab Storage (Screenshot 3):**
   - Screenshot 3 shows a custom **"Swatches"** tab on the left sidebar of WooCommerce Product Data (*Linked Products, Attributes, Variations, Swatches*).
   - Swatch plugins storing data in this tab save serialized settings inside **parent product postmeta** (e.g., `_swatch_data` or `_wcvs_swatch_data`) or variation-level postmeta (e.g., `_swatch_color` or `_wcvs_color`), rather than taxonomy `wp_termmeta`.
3. **Lookup Pipeline Fallback:**
   - When `Tvak_Shade_Sync::sync_wc_variation_to_tvak()` executes, it attempts to resolve HEX colors via `get_term_meta()` and single variation postmeta (`color`, `hex`, `swatch`).
   - If the attribute is local or the swatch plugin uses a product-level serialized postmeta array, the term lookup returns empty.
   - Because no HEX string matching `^#[0-9A-Fa-f]{3,6}$` is found, the system defaults to `Tvak_Shade_Sync::get_default_hex()`, which returns `#D4AF37` for every shade.

---

### 🔍 Issue 2: Why is the Order column showing `0`?

#### **Observation from Screenshot 2:**
In the *Configured Shades for this Product* table, the `Order` column displays `0` for all auto-synced shades (`Biscuit`, `Soft Beige`, `SPF 30 - Bisque`).

#### **Root Cause Analysis:**
1. **Database Schema Default:** In the database table `wp_tvak_product_shades`, the `sort_order` column is defined as `INT NOT NULL DEFAULT 0`.
2. **Missing `menu_order` Extraction during Auto-Sync:**
   - When WooCommerce variations are imported via `Tvak_Shade_Sync::sync_wc_variation_to_tvak()`, the function extracts variation price, stock status, and image URL, but **does not read `get_post($variation_id)->menu_order`** (WooCommerce variation drag-and-drop position).
   - As a result, `Tvak_Product_Shade::save_shade()` receives no `sort_order` parameter and defaults to `0`.
3. **Admin Rendering:** The admin template renders `<td><?php echo esc_html($sh['sort_order']); ?></td>`, which outputs `0` for all auto-synced rows until manually edited in TVAK.

---

### 🔍 Issue 3: If I edit or delete here, will it reflect in WooCommerce products?

#### **1. Editing in TVAK Shades (`TVAK Engine -> Product Shades`):**
- **What reflects to WooCommerce:** When you edit a shade price or stock status in TVAK, `Tvak_Shade_Sync::sync_tvak_shade_to_wc()` updates WooCommerce variation postmeta (`_price`, `_stock_status`).
- **Current Limitation:** If you rename the shade or change its image in TVAK, it updates the TVAK database table (`wp_tvak_product_shades`), but currently **does not rewrite the parent WooCommerce Variation Post Title or `_thumbnail_id`** in `wp_posts`.

#### **2. Deleting in TVAK Shades (`TVAK Engine -> Product Shades`):**
- **What happens in TVAK:** Clicking **Delete** removes the shade row from the TVAK database table `wp_tvak_product_shades`.
- **What happens in WooCommerce:** It **does NOT delete** the variation post from WooCommerce core tables (`wp_posts`). The variation remains in WooCommerce.
- **Auto-Sync Re-Import Behavior:** If you click *"Auto-Sync WooCommerce Catalog & Swatches"* again in the future, TVAK will detect the active WooCommerce variation and re-import it back into the shades table.

---

### 🔍 Issue 4: If WooCommerce variations or images change, do recommendation images also change? What if I change images in TVAK?

#### **1. If WooCommerce variation images change in WooCommerce:**
- **Behavior:** YES! When you upload or change a variation image in WooCommerce (*Products → Edit Product → Variations → Variation Thumbnail*) and save:
  1. WooCommerce triggers the `woocommerce_save_product_variation` action hook.
  2. `Tvak_Shade_Sync` catches this event, extracts `$img_id = get_post_meta($variation_id, '_thumbnail_id', true);`, fetches the image URL, and updates `wp_tvak_product_shades`.
  3. When recommendations render on the frontend quiz results, `Tvak_Variant_Resolver::resolve()` sends the updated `image_url` to the API.
  4. In `tvak-builder.js`, when a customer clicks a shade swatch circle on a recommendation card, the card image (`.tvak-card-img`) dynamically swaps to that shade's exact variation image in real time.

#### **2. If you change the image in TVAK (`Product Shades` manager):**
- **Behavior:** The updated image URL is saved to `wp_tvak_product_shades` and will immediately display on frontend recommendation cards when that shade is selected.
- **Limitation:** It will not overwrite the original WooCommerce variation post thumbnail in `wp_posts` (image flow is 1-way from WC → TVAK or internal to TVAK).

---

## 3. Proposed Technical Fix & Refinement Plan

```
┌────────────────────────────────────────────────────────────────────────┐
│                          PROPOSED FIX PLAN                             │
├────────────────────────────────────────────────────────────────────────┤
│ 1. Swatch Color Extraction Enhancement:                                │
│    Add parser for product-level serialized swatch postmeta             │
│    (_swatch_data, _wcvs_swatch_data) so custom "Swatches" tab colors   │
│    are extracted automatically during Auto-Sync without defaulting.    │
│                                                                        │
│ 2. Order Column Menu Sync:                                             │
│    Update Tvak_Shade_Sync to extract $variation_post->menu_order       │
│    during Auto-Sync so the Order column displays 1, 2, 3, 4, 5...      │
│                                                                        │
│ 3. True Bi-Directional Edit & Delete Sync:                             │
│    - When editing a shade image in TVAK, update WC _thumbnail_id.      │
│    - When deleting a shade in TVAK, offer a toggle: "Delete from TVAK  │
│      only" OR "Delete from WooCommerce as well".                       │
└────────────────────────────────────────────────────────────────────────┘
```
