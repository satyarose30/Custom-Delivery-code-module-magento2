<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Model;

use Custom\DeliveryRestriction\Logger\Logger;
use Custom\DeliveryRestriction\Model\ResourceModel\ZipCode\CollectionFactory as ZipCollectionFactory;
use Magento\Customer\Model\Session as CustomerSession;

/**
 * Core zip-code validation engine — upgraded with:
 *  - DB-based zip code records (admin grid) as the primary source
 *  - Fallback to system-config textarea
 *  - Customer group scoping (from MageDelight approach)
 *  - Per-record category scoping
 *  - Dedicated logger
 *
 * Matching capabilities:
 *  - Exact       : 10001
 *  - Wildcard    : 100*
 *  - Range       : 10000-10099 (numeric or alphanumeric)
 *  - Case-insensitive always
 *
 * Safe-guard: empty zip list → allow all (prevents accidental store lock-out).
 */
class ZipValidator
{
    public function __construct(
        private readonly Config               $config,
        private readonly ZipCollectionFactory $zipCollectionFactory,
        private readonly CustomerSession      $customerSession,
        private readonly Logger               $logger
    ) {}

    // ── Public API ──────────────────────────────────────────────────────────────

    /**
     * Main entry point — returns true when delivery IS available.
     *
     * @param string   $zipCode
     * @param int|null $storeId
     * @param int|null $customerGroupId  Pass explicitly in non-session contexts (e.g. observer)
     * @param int[]    $categoryIds      Category IDs in the current cart (for category scoping)
     */
    public function isAvailable(
        string $zipCode,
        ?int   $storeId = null,
        ?int   $customerGroupId = null,
        array  $categoryIds = []
    ): bool {
        if (!$this->config->isEnabled($storeId)) {
            return true;
        }

        $zipCode = trim($zipCode);
        if ($zipCode === '') {
            return true;
        }

        // ── Customer-group guard (MageDelight feature) ─────────────────────────
        if ($this->config->isCustomerGroupRestrictionEnabled($storeId)) {
            $effectiveGroupId = $customerGroupId ?? $this->resolveCustomerGroupId();
            $restrictedGroups = $this->config->getRestrictedCustomerGroupIds($storeId);

            if ($restrictedGroups !== [] && !in_array($effectiveGroupId, $restrictedGroups, true)) {
                // Customer group is NOT in the restricted list → allow unconditionally
                if ($this->config->isLoggingEnabled($storeId)) {
                    $this->logger->info('[DeliveryRestriction] Skipped: customer group not restricted', [
                        'group_id' => $effectiveGroupId,
                        'zip'      => $zipCode,
                    ]);
                }
                return true;
            }
        }

        // ── Determine zip source ───────────────────────────────────────────────
        if ($this->config->useDbZipCodes($storeId)) {
            $effectiveGroupId = $customerGroupId ?? $this->resolveCustomerGroupId();
            return $this->checkAgainstDb($zipCode, $storeId, $effectiveGroupId, $categoryIds);
        }

        return $this->checkAgainstConfig($zipCode, $storeId);
    }

    /**
     * Calculate estimated delivery date range, skipping weekends.
     *
     * @return array{min_date:string, max_date:string, min_days:int, max_days:int}|null
     */
    public function getEstimatedDelivery(?int $storeId = null): ?array
    {
        if (!$this->config->isDeliveryEstimateEnabled($storeId)) {
            return null;
        }

        $minDays = $this->config->getMinDays($storeId);
        $maxDays = $this->config->getMaxDays($storeId);

        if ($maxDays < $minDays) {
            $maxDays = $minDays;
        }

        $now     = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $minDate = $this->addBusinessDays($now, $minDays);
        $maxDate = $this->addBusinessDays($now, $maxDays);

        return [
            'min_date' => $minDate->format('D, M j'),
            'max_date' => $maxDate->format('D, M j'),
            'min_days' => $minDays,
            'max_days' => $maxDays,
        ];
    }

    /**
     * Parse the admin textarea into a clean, deduplicated array of patterns.
     * Accepts newlines AND commas as separators.
     *
     * @return string[]
     */
    public function parseZipCodes(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        $parts = preg_split('/[\r\n,]+/', $raw) ?: [];
        $zips  = [];

        foreach ($parts as $part) {
            $zip = strtoupper(trim($part));
            if ($zip !== '') {
                $zips[] = $zip;
            }
        }

        return array_values(array_unique($zips));
    }

    // ── Private: config-based check ────────────────────────────────────────────

    private function checkAgainstConfig(string $zipCode, ?int $storeId): bool
    {
        $configuredZips = $this->parseZipCodes($this->config->getRawZipCodes($storeId));

        if ($configuredZips === []) {
            return true;
        }

        $isInList        = $this->isZipInList($zipCode, $configuredZips);
        $restrictionType = $this->config->getRestrictionType($storeId);

        return match ($restrictionType) {
            'whitelist' => $isInList,
            'blacklist' => !$isInList,
            default     => true,
        };
    }

    // ── Private: DB-based check ────────────────────────────────────────────────

    private function checkAgainstDb(
        string $zipCode,
        ?int   $storeId,
        int    $customerGroupId,
        array  $categoryIds
    ): bool {
        $collection = $this->zipCollectionFactory->create();
        $collection->addActiveFilter();

        if ($storeId !== null) {
            $collection->addStoreFilter($storeId);
        }

        $collection->addCustomerGroupFilter($customerGroupId);

        $records = $collection->getItems();

        if (empty($records)) {
            return true;
        }

        $blacklistPatterns = [];
        $whitelistPatterns = [];

        foreach ($records as $record) {
            /** @var \Custom\DeliveryRestriction\Model\ZipCode $record */
            $recordCategories = $record->getCategoryIdsArray();

            // Apply category-scoped rules only when category context is available
            if ($recordCategories !== []) {
                if ($categoryIds === [] || array_intersect($recordCategories, $categoryIds) === []) {
                    continue;
                }
            }

            $pattern = strtoupper(trim((string) $record->getZipCode()));
            if ($pattern === '') {
                continue;
            }

            if ($record->getRestrictionType() === 'whitelist') {
                $whitelistPatterns[] = $pattern;
            } else {
                $blacklistPatterns[] = $pattern;
            }
        }

        // Whitelist takes precedence
        if ($whitelistPatterns !== []) {
            return $this->isZipInList($zipCode, $whitelistPatterns);
        }

        if ($blacklistPatterns !== []) {
            return !$this->isZipInList($zipCode, $blacklistPatterns);
        }

        return true;
    }

    // ── Private: matching helpers ──────────────────────────────────────────────

    /** @param string[] $zipList */
    private function isZipInList(string $zipCode, array $zipList): bool
    {
        $needle = strtoupper(trim($zipCode));

        foreach ($zipList as $pattern) {
            if ($this->matchesPattern($needle, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function matchesPattern(string $zipCode, string $pattern): bool
    {
        // Wildcard
        if (str_contains($pattern, '*')) {
            $regex = '/^' . str_replace('\*', '[A-Z0-9\s]*', preg_quote($pattern, '/')) . '$/i';
            return (bool) preg_match($regex, $zipCode);
        }

        // Range
        if (substr_count($pattern, '-') === 1) {
            [$start, $end] = explode('-', $pattern, 2);
            $start = trim($start);
            $end   = trim($end);

            if ($start !== '' && $end !== '') {
                if (ctype_digit($start) && ctype_digit($end) && ctype_digit($zipCode)) {
                    return (int) $zipCode >= (int) $start && (int) $zipCode <= (int) $end;
                }
                $zip = strtoupper($zipCode);
                return strcmp($zip, strtoupper($start)) >= 0
                    && strcmp($zip, strtoupper($end))   <= 0;
            }
        }

        // Exact
        return strtoupper($zipCode) === $pattern;
    }

    private function addBusinessDays(\DateTimeImmutable $date, int $days): \DateTimeImmutable
    {
        $added   = 0;
        $current = $date;

        while ($added < $days) {
            $current   = $current->modify('+1 day');
            $dayOfWeek = (int) $current->format('N');
            if ($dayOfWeek <= 5) {
                $added++;
            }
        }

        return $current;
    }

    private function resolveCustomerGroupId(): int
    {
        try {
            return (int) $this->customerSession->getCustomerGroupId();
        } catch (\Throwable) {
            return 0; // NOT LOGGED IN
        }
    }
}
