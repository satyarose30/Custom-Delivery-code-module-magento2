# Code Review Recommendations (File-wise)

## Custom_DeliveryRestriction/etc/adminhtml/system.xml
- **High:** Replace the `<s>` root child with Magento’s expected `<system>` node. Current XML is well-formed but does not match the standard `system_file.xsd` structure and may cause config section loading issues in strict environments.
- **Low:** Normalize special punctuation in comments (e.g., en-dash in `Mon–Fri`) to avoid encoding inconsistencies across tooling.

## Custom_DeliveryRestriction/Helper/Email.php
- **High:** Implement CC handling (`getAdminEmailCc`) since config exposes a CC field but the helper never calls `addCc()`. This creates a configuration/runtime mismatch.
- **Medium:** Guard `inlineTranslation->resume()` with a `finally`-style flag so resume is only called after a successful `suspend()`. Today, a pre-suspend exception can still call `resume()`.
- **Medium:** Validate/split recipient and CC addresses defensively (trim, skip empties, optional validation) before calling transport methods.

## Custom_DeliveryRestriction/Model/ZipValidator.php
- **High:** In `checkAgainstDb`, category-scoped records are currently applied globally when `$categoryIds` is empty. For product-page AJAX checks (where categories are not passed), this can produce false blocks. Skip category-scoped rules unless cart/product categories are provided.
- **High:** DB filtering by customer group only runs when `$customerGroupId !== null`. Callers like checkout plugin and AJAX pass `null`, so per-record customer-group restrictions can be ignored. Resolve effective group ID before DB filtering (or always filter by resolved group).
- **Medium:** Add unit tests around wildcard/range/category/customer-group precedence to lock behavior and prevent regressions.

## Custom_DeliveryRestriction/Plugin/ShippingInformationPlugin.php
- **Medium:** Pass explicit customer group (and optionally category IDs) into `ZipValidator::isAvailable()` to keep shipping-step validation consistent with order-submit validation.
- **Low:** Avoid manual `htmlspecialchars()` before exception message substitution; rely on Magento escaping at render-time to prevent double-escaping in some flows.

## Custom_DeliveryRestriction/Observer/ValidateZipOnOrderPlace.php
- **Medium:** Consider centralizing message creation (same logic appears in plugin + observer) to avoid drift in future updates.
- **Low:** Category extraction from quote items can be expensive if products are partially loaded; consider fallback/repository strategy if catalog loading inconsistencies appear in production.

## Custom_DeliveryRestriction/Controller/Ajax/Check.php
- **Medium:** Prefer module logger (`Custom\DeliveryRestriction\Logger\Logger`) for consistent log routing; this class currently logs to generic PSR logger.
- **Low:** The controller currently returns escaped zip in payload; ensure frontend never re-escapes displayed values to avoid showing entities.

## Custom_DeliveryRestriction/Model/Config.php
- **Medium:** Add explicit fallback defaults in getters (`getAvailableMessage`, `getUnavailableMessage`, `getWidgetTitle`, etc.) to avoid empty UI strings when admin values are blank.
- **Low:** `getErrorMessage()` appears unused in active flow; remove or wire it to reduce dead API surface.

## Custom_DeliveryRestriction/etc/di.xml
- **Low:** The `CollectionFactory` mapping for admin grid should be verified against Magento version conventions; in newer versions, virtual types are often preferred for grid collections.

## Custom_DeliveryRestriction/etc/module.xml
- **Low:** `setup_version` is legacy for declarative schema modules; consider removing it for Magento versions where it’s unnecessary.

## Custom_DeliveryRestriction/composer.json
- **Low:** Version is `1.0.0` while `module.xml` says `1.1.0`; keep them aligned to avoid release/package confusion.
- **Low:** Replace wildcard `*` Magento module constraints with compatible ranges where possible for safer dependency resolution.

## Custom_DeliveryRestriction/view/frontend/web/js/zip-checker.js
- **Medium:** Add explicit client-side handling for network timeouts/retries and a disabled-state reset path to prevent stuck UI states.
- **Low:** Ensure all user-facing strings come from backend-config/localized sources for translation completeness.

## Custom_DeliveryRestriction/view/frontend/templates/product/zip_checker.phtml
- **Medium:** Verify all dynamic strings/values are escaped with the correct Magento escaper context (`escapeHtml`, `escapeHtmlAttr`, `escapeUrl`) and avoid raw output in data attributes.

## Custom_DeliveryRestriction/etc/db_schema.xml + ResourceModel/Model files
- **Medium:** Add DB indexes for high-frequency lookup columns (zip_code, status, store/customer_group relation fields) if not already present to reduce checkout-path query latency.
- **Low:** Consider unique constraint strategy (when applicable) to prevent duplicate zip patterns creating ambiguous precedence.

## Cross-cutting
- Add integration tests for:
  - Shipping-step block behavior.
  - Order-submit block behavior.
  - DB source vs textarea source selection.
  - Customer-group scoping.
  - Category-scoped zip rules.
  - Email notification + CC behavior.
