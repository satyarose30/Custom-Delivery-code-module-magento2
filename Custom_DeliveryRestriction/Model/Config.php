<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Typed system-config reader for Custom_DeliveryRestriction.
 * PHP 8.4 compatible — explicit nullable types, readonly constructor promotion.
 *
 * Sections:
 *   General       — module toggle, restriction mode, fallback zip textarea
 *   Product Page  — widget UI settings
 *   Delivery Est  — business-day estimation
 *   Checkout      — order block settings
 *   Customer Grp  — restrict by customer group (from MageDelight approach)
 *   Logging       — dedicated log file toggle
 *   Email         — admin notification on restricted zip attempt
 */
class Config
{
    // ── General ────────────────────────────────────────────────────────────────
    private const XML_ENABLED           = 'delivery_restriction/general/enabled';
    private const XML_RESTRICTION_TYPE  = 'delivery_restriction/general/restriction_type';
    private const XML_ZIP_CODES         = 'delivery_restriction/general/zip_codes';
    private const XML_ERROR_MSG         = 'delivery_restriction/general/error_message';
    private const XML_USE_DB_ZIPCODES   = 'delivery_restriction/general/use_db_zipcodes';

    // ── Product page ───────────────────────────────────────────────────────────
    private const XML_PRODUCT_ENABLED   = 'delivery_restriction/product_page/enabled';
    private const XML_WIDGET_TITLE      = 'delivery_restriction/product_page/widget_title';
    private const XML_AVAIL_MSG         = 'delivery_restriction/product_page/available_message';
    private const XML_UNAVAIL_MSG       = 'delivery_restriction/product_page/unavailable_message';
    private const XML_PLACEHOLDER       = 'delivery_restriction/product_page/input_placeholder';

    // ── Delivery estimate ──────────────────────────────────────────────────────
    private const XML_ESTIMATE_ENABLED  = 'delivery_restriction/delivery_estimate/enabled';
    private const XML_MIN_DAYS          = 'delivery_restriction/delivery_estimate/min_days';
    private const XML_MAX_DAYS          = 'delivery_restriction/delivery_estimate/max_days';

    // ── Checkout ───────────────────────────────────────────────────────────────
    private const XML_BLOCK_ORDER       = 'delivery_restriction/checkout/block_order';
    private const XML_CHECKOUT_ERR_MSG  = 'delivery_restriction/checkout/checkout_error_message';

    // ── Customer Groups (MageDelight feature) ──────────────────────────────────
    private const XML_CUST_GROUPS_ENABLED  = 'delivery_restriction/customer_groups/enabled';
    private const XML_CUST_GROUPS_LIST     = 'delivery_restriction/customer_groups/group_ids';

    // ── Logging ────────────────────────────────────────────────────────────────
    private const XML_LOGGING_ENABLED   = 'delivery_restriction/logging/enabled';

    // ── Admin Email Notification (MageDelight feature) ─────────────────────────
    private const XML_EMAIL_ENABLED     = 'delivery_restriction/email/enabled';
    private const XML_EMAIL_SENDER      = 'delivery_restriction/email/sender';
    private const XML_EMAIL_RECIPIENT   = 'delivery_restriction/email/recipient';
    private const XML_EMAIL_CC          = 'delivery_restriction/email/cc';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {}

    // ── General ────────────────────────────────────────────────────────────────

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /** Returns 'blacklist' or 'whitelist' */
    public function getRestrictionType(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_RESTRICTION_TYPE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /** Raw textarea value — newline / comma separated zip codes (fallback source) */
    public function getRawZipCodes(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_ZIP_CODES, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getErrorMessage(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_ERROR_MSG, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /** When true, zip codes are read from the DB table (admin grid) instead of the textarea */
    public function useDbZipCodes(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_USE_DB_ZIPCODES, ScopeInterface::SCOPE_STORE, $storeId);
    }

    // ── Product page ───────────────────────────────────────────────────────────

    public function isProductPageEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PRODUCT_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getWidgetTitle(?int $storeId = null): string
    {
        $value = trim((string) $this->scopeConfig->getValue(self::XML_WIDGET_TITLE, ScopeInterface::SCOPE_STORE, $storeId));
        return $value !== '' ? $value : (string) __('Check Delivery Availability');
    }

    public function getAvailableMessage(?int $storeId = null): string
    {
        $value = trim((string) $this->scopeConfig->getValue(self::XML_AVAIL_MSG, ScopeInterface::SCOPE_STORE, $storeId));
        return $value !== '' ? $value : (string) __('Delivery is available for this zip code.');
    }

    public function getUnavailableMessage(?int $storeId = null): string
    {
        $value = trim((string) $this->scopeConfig->getValue(self::XML_UNAVAIL_MSG, ScopeInterface::SCOPE_STORE, $storeId));
        return $value !== '' ? $value : (string) __('Sorry, delivery is not available for this zip code.');
    }

    public function getInputPlaceholder(?int $storeId = null): string
    {
        $value = trim((string) $this->scopeConfig->getValue(self::XML_PLACEHOLDER, ScopeInterface::SCOPE_STORE, $storeId));
        return $value !== '' ? $value : (string) __('Enter ZIP / Postal Code');
    }

    // ── Delivery estimate ──────────────────────────────────────────────────────

    public function isDeliveryEstimateEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_ESTIMATE_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getMinDays(?int $storeId = null): int
    {
        return max(1, (int) $this->scopeConfig->getValue(self::XML_MIN_DAYS, ScopeInterface::SCOPE_STORE, $storeId));
    }

    public function getMaxDays(?int $storeId = null): int
    {
        return max(1, (int) $this->scopeConfig->getValue(self::XML_MAX_DAYS, ScopeInterface::SCOPE_STORE, $storeId));
    }

    // ── Checkout ───────────────────────────────────────────────────────────────

    public function isBlockOrderEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_BLOCK_ORDER, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getCheckoutErrorMessage(?int $storeId = null): string
    {
        $value = trim((string) $this->scopeConfig->getValue(self::XML_CHECKOUT_ERR_MSG, ScopeInterface::SCOPE_STORE, $storeId));
        return $value !== '' ? $value : (string) __('Sorry, delivery is not available for zip code %1.');
    }

    public function getCheckoutErrorMessageForZip(string $zipCode, ?int $storeId = null): string
    {
        return str_replace('%1', $zipCode, $this->getCheckoutErrorMessage($storeId));
    }

    // ── Customer Groups ────────────────────────────────────────────────────────

    /**
     * Whether customer-group scoping is active.
     * When false, restrictions apply to ALL customers regardless of group.
     */
    public function isCustomerGroupRestrictionEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_CUST_GROUPS_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Returns the list of customer group IDs that ARE affected by the restriction.
     * An empty array means the feature is off or misconfigured — fall back to "all".
     *
     * @return int[]
     */
    public function getRestrictedCustomerGroupIds(?int $storeId = null): array
    {
        $raw = (string) $this->scopeConfig->getValue(self::XML_CUST_GROUPS_LIST, ScopeInterface::SCOPE_STORE, $storeId);
        if (trim($raw) === '') {
            return [];
        }
        return array_map('intval', array_filter(explode(',', $raw)));
    }

    // ── Logging ────────────────────────────────────────────────────────────────

    public function isLoggingEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_LOGGING_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    // ── Admin Email Notification ───────────────────────────────────────────────

    public function isAdminEmailEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_EMAIL_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /** Returns identity string like 'general', 'sales', 'support', etc. */
    public function getAdminEmailSender(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_EMAIL_SENDER, ScopeInterface::SCOPE_STORE, $storeId) ?: 'general';
    }

    public function getAdminEmailRecipient(?int $storeId = null): string
    {
        return trim((string) $this->scopeConfig->getValue(self::XML_EMAIL_RECIPIENT, ScopeInterface::SCOPE_STORE, $storeId));
    }

    /** Comma-separated CC list (optional) */
    public function getAdminEmailCc(?int $storeId = null): string
    {
        return trim((string) $this->scopeConfig->getValue(self::XML_EMAIL_CC, ScopeInterface::SCOPE_STORE, $storeId));
    }
}
