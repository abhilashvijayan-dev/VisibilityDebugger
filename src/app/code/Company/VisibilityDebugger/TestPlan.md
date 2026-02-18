# Test Plan — Company Visibility Debugger

## Unit tests

### 1. Check classes

- **ProductStatusCheck**
  - Product enabled in store → one issue, status pass.
  - Product disabled in store → one issue, status fail, suggestion present.
  - Product not found → one issue, status fail.
  - Store invalid → one issue, status warn.
  - Mock: ProductRepositoryInterface, StoreManagerInterface.

- **VisibilityAttributeCheck**
  - Visibility "Not Visible Individually" → fail, suggestion.
  - Visibility "Catalog" only → warn.
  - Visibility "Search" only → warn.
  - Visibility "Catalog, Search" → pass.
  - Mock: ProductRepositoryInterface.

- **WebsiteAssignmentCheck**
  - Product assigned to store’s website → pass.
  - Product not assigned → fail, suggestion.
  - Store invalid → warn.
  - Mock: ProductRepositoryInterface, StoreManagerInterface.

- **CategoryAssignmentCheck**
  - Product has category IDs → pass.
  - Product has no categories → warn, suggestion.
  - Product not found → fail.
  - Mock: ProductRepositoryInterface.

- **StockSalableCheck**
  - Product salable → pass.
  - Product not salable → fail, suggestion (no reindex).
  - Store invalid / product not found → appropriate status.
  - Mock: ProductRepositoryInterface, StoreManagerInterface, product `isSalable()`.

- **IndexerStateCheck**
  - All key indexers valid → pass.
  - One or more invalid → warn, suggestion contains reindex command string.
  - Mock: Indexer Collection (or CollectionFactory) with controlled indexer states.

### 2. VisibilityDiagnosticsService

- **Sorting**: All checks return issues; result order is fail first, then warn, then pass.
  - Mock: array of check mocks; each returns one or more VisibilityIssueInterface with known status.
  - Assert: order of returned issues by status.
- **Aggregation**: Multiple checks → all issues present in result.
- **Exception handling**: One check throws → one generic "check error" issue (warn), no sensitive data in message; other checks still run.

### 3. Data / API

- **VisibilityIssue (Model/Data/VisibilityIssue)**: getters/setters for code, title, status, message, suggestion, docs_url; implements VisibilityIssueInterface.

## Integration tests

### 4. Controller (skeleton)

- **Debug action (GET)**
  - Valid product_id and store_id → HTTP 200, JSON with `success: true`, `product_id`, `store_id`, `issues` array; each issue has code, title, status, message, suggestion.
  - Invalid product_id (e.g. 0 or non-existent) → 200, JSON with `success: false`, `error` message, `issues: []`.
  - Invalid store_id → 200, JSON with `success: false`, `error`.
  - ACL: request without permission → 403 (or redirect to login) as per Magento admin.
  - No sensitive data in response (no stack traces, no internal paths).

### 5. Recommended commands

- **IndexerStateCheck::getRecommendedReindexCommand**
  - Given `['category_assignment']` → string contains `catalog_category_product` (and optionally `catalog_product_category`).
  - Given `['visibility_attribute']` → string contains `catalog_product_attribute`, `catalogsearch_fulltext`, `catalog_product_price`.
  - Given `['stock_salable']` → do NOT add reindex for stock; suggestion is stock/source only (covered in check suggestion text).
  - Unit test this static method with various arrays.

## Test commands

From Magento root (e.g. `src/`):

```bash
# Unit tests (VisibilityDebugger only)
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Company/VisibilityDebugger/Test/Unit

# Integration tests (from dev/tests/integration; requires DB)
cd dev/tests/integration
../../../vendor/bin/phpunit -c phpunit.xml.dist ../../../app/code/Company/VisibilityDebugger/Test/Integration
```

## Dependencies to mock

- ProductRepositoryInterface
- StoreManagerInterface
- Indexer Collection/CollectionFactory and IndexerInterface/StateInterface
- VisibilityDiagnosticsService (for controller test): can use real service with mocked checks or full integration DB

## Marketplace readiness (verification)

- No ObjectManager usage.
- No core file overrides; only plugins/observers/services and layout.
- ACL resource used in controller and menu.
- No auto-fix; only diagnostic output and copyable command suggestions.
- No sensitive data in logs or JSON responses.
