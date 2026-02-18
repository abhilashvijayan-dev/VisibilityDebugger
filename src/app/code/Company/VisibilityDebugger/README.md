# Company Visibility Debugger

Smart Product Visibility Debugger for Adobe Commerce / Magento 2. Explains why a product is not visible on the storefront for a selected store view, with pass/fail checks and actionable suggestions. **No auto-fix** — diagnostic only.

## What problem it solves

Products sometimes do not appear on category pages, search, or the storefront. Causes include:

- Product disabled (store-specific)
- Visibility set to "Not Visible Individually"
- Product not assigned to the store’s website
- No category assignment
- Out of stock / not salable (MSI or legacy)
- Invalid or stale indexers

This module runs a set of **checks** for a product and store view and returns a clear list of **issues** (pass / fail / warn) with **messages** and **suggestions**, so you can fix the cause manually.

## How to use in Admin

1. **Catalog > Products** — Edit a product.
2. On the product edit page, find the section **"Why isn't this product visible?"** (near the top of the content area).
3. Select the **Store View** you want to check (or leave the current one).
4. Click **Run Debug**.
5. Review the table: **Status** (✓ pass / ✗ fail / ⚠ warn), **Check**, **Message**, **Suggestion**.
6. If **Recommended commands** appears, copy the suggested `bin/magento indexer:reindex ...` or `indexer:status` / `cache:status` commands (no auto-execution).

No page reload: the request is AJAX and results render in place.

## What each check means

| Check | Pass | Fail | Warn |
|-------|------|------|------|
| **Product Status** | Product is enabled in this store view. | Product is disabled. | Store invalid or product not found. |
| **Visibility** | Visibility allows storefront (e.g. Catalog, Search). | "Not Visible Individually". | "Catalog" only (no search) or "Search" only (no category). |
| **Website Assignment** | Product is assigned to the website of this store. | Not assigned to this store’s website. | Store invalid. |
| **Category Assignment** | Product is in at least one category. | — | Not in any category (required for catalog listing). |
| **Stock / Salability** | Product is salable for this store. | Not salable (out of stock or source). | Store invalid. |
| **Indexer Status** | Key catalog indexers are valid. | — | Some key indexers invalid (reindex suggested). |

Key indexers considered: `catalog_product_attribute`, `catalog_category_product`, `catalog_product_price`, `catalogsearch_fulltext`.

## Troubleshooting

- **"Invalid product ID" / "Product not found"** — Ensure you are editing a valid product and the ID is passed correctly (e.g. from the product edit page).
- **"Invalid store"** — Choose a valid store view; the store must exist and be active.
- **"Diagnostics could not be completed"** — A check threw an exception; no sensitive data is logged. Retry or check server logs (exception only).
- **Empty or missing section** — Ensure the module is enabled, ACL resource `Company_VisibilityDebugger::debug` is allowed for your admin role, and cache is flushed after install.

## Recommended commands (no auto-execution)

The module only suggests copyable commands, for example:

- `bin/magento indexer:reindex catalog_product_attribute catalog_category_product`
- `bin/magento indexer:status`
- `bin/magento cache:status`

Do **not** run reindex for stock/salability issues — fix sources and quantity in Inventory instead.

## Marketplace readiness notes

- **ACL**: Resource `Company_VisibilityDebugger::debug` restricts access to the debug feature.
- **No auto-fix**: The module only reports issues and suggests actions; it does not change product data, indexers, or cache.
- **No core overrides**: Implemented via services, plugins, and layout; no core files are modified.
- **Strict types, DI**: PHP 8.2, `declare(strict_types=1);`, dependency injection only; no ObjectManager.
- **No sensitive data logged**: Logs do not include product IDs in a way that could leak business data; exceptions are generic.

## Requirements

- Adobe Commerce / Magento 2.4.x (tested on 2.4.8-p3)
- PHP 8.2+

## Installation

1. Copy the module into `app/code/Company/VisibilityDebugger/`.
2. Enable: `bin/magento module:enable Company_VisibilityDebugger`
3. Setup: `bin/magento setup:upgrade`
4. Compile: `bin/magento setup:di:compile`
5. Flush cache: `bin/magento cache:flush`
6. Grant ACL: **System > Permissions > User Roles** (or equivalent) — allow **Catalog > Visibility Debugger** for the desired role.

## File overview

- **API**: `Api/Data/VisibilityIssueInterface.php`, `Api/VisibilityCheckInterface.php`
- **Model**: `Model/Data/VisibilityIssue.php`, `Model/Service/VisibilityDiagnosticsService.php`
- **Checks**: `Model/Check/` — ProductStatusCheck, VisibilityAttributeCheck, WebsiteAssignmentCheck, CategoryAssignmentCheck, StockSalableCheck, IndexerStateCheck
- **Controller**: `Controller/Adminhtml/Product/Debug.php` (JSON)
- **Block/Template**: `Block/Adminhtml/Product/Edit/VisibilityDebugSection.php`, `view/adminhtml/templates/product/edit/visibility_debug_section.phtml`
- **JS**: `view/adminhtml/web/js/visibility-debug.js`
- **Layout**: `view/adminhtml/layout/catalog_product_edit.xml`
- **Config**: `etc/module.xml`, `etc/acl.xml`, `etc/di.xml`, `etc/adminhtml/menu.xml`, `etc/adminhtml/routes.xml`
- **Tests**: `Test/Unit/`, `Test/Integration/`

## Running tests

From the Magento root (e.g. `src/`):

```bash
# Unit tests (VisibilityDebugger only)
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Company/VisibilityDebugger/Test/Unit

# Integration tests (requires DB; run from dev/tests/integration)
cd dev/tests/integration
../../../vendor/bin/phpunit -c phpunit.xml.dist ../../../app/code/Company/VisibilityDebugger/Test/Integration
```
