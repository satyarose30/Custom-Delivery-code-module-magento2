<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Observer;

use Custom\DeliveryRestriction\Helper\Email as EmailHelper;
use Custom\DeliveryRestriction\Logger\Logger;
use Custom\DeliveryRestriction\Model\Config;
use Custom\DeliveryRestriction\Model\ZipValidator;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;

/**
 * Fires on `sales_model_service_quote_submit_before` — the very last moment
 * before the quote becomes an order.
 *
 * LAYER 2 of dual protection (Layer 1 = ShippingInformationPlugin).
 * This observer also catches headless / REST API orders that bypass
 * the checkout shipping step.
 *
 * Additional features from MageDelight:
 *  - Customer group awareness (quote carries the customer group)
 *  - Category-level scoping (reads category IDs from cart items)
 *  - Admin email alert on block
 *  - Dedicated logger
 */
class ValidateZipOnOrderPlace implements ObserverInterface
{
    public function __construct(
        private readonly Config       $config,
        private readonly ZipValidator $zipValidator,
        private readonly EmailHelper  $emailHelper,
        private readonly Logger       $logger
    ) {}

    /**
     * @throws LocalizedException
     */
    public function execute(Observer $observer): void
    {
        /** @var Quote|null $quote */
        $quote = $observer->getEvent()->getData('quote');

        if (!($quote instanceof Quote)) {
            return;
        }

        $storeId = (int) $quote->getStoreId();

        if (!$this->config->isEnabled($storeId) || !$this->config->isBlockOrderEnabled($storeId)) {
            return;
        }

        if ($quote->isVirtual()) {
            return;
        }

        $shippingAddress = $quote->getShippingAddress();
        if ($shippingAddress === null) {
            return;
        }

        $zipCode = trim((string) $shippingAddress->getPostcode());
        if ($zipCode === '') {
            return;
        }

        // Resolve customer group from quote (works for guest and logged-in)
        $customerGroupId = (int) $quote->getCustomerGroupId();

        // Collect category IDs from all cart items for category-level scoping
        $categoryIds = $this->extractCategoryIds($quote);

        $isAvailable = $this->zipValidator->isAvailable(
            $zipCode,
            $storeId,
            $customerGroupId,
            $categoryIds
        );

        if (!$isAvailable) {
            // ── Log the block ──────────────────────────────────────────────────
            if ($this->config->isLoggingEnabled($storeId)) {
                $this->logger->info('[DeliveryRestriction] Order blocked — restricted zip', [
                    'zip'            => $zipCode,
                    'store_id'       => $storeId,
                    'customer_group' => $customerGroupId,
                    'customer_email' => (string) $quote->getCustomerEmail(),
                ]);
            }

            // ── Send admin email alert (MageDelight feature) ───────────────────
            $customerFirstName = (string) $quote->getCustomerFirstname();
            $customerLastName  = (string) $quote->getCustomerLastname();
            $customerName      = trim("$customerFirstName $customerLastName") ?: 'Guest';
            $customerEmail     = (string) $quote->getCustomerEmail();

            $this->emailHelper->sendRestrictedZipAlert(
                $zipCode,
                $customerName,
                $customerEmail,
                $storeId
            );

            // ── Block the order ────────────────────────────────────────────────
            $template = $this->config->getCheckoutErrorMessage($storeId);
            $message  = str_replace('%1', htmlspecialchars($zipCode, ENT_QUOTES, 'UTF-8'), $template);
            throw new LocalizedException(__($message));
        }
    }

    /**
     * Extract all unique category IDs from items in the quote.
     *
     * @return int[]
     */
    private function extractCategoryIds(Quote $quote): array
    {
        $categoryIds = [];

        foreach ($quote->getAllVisibleItems() as $item) {
            $product = $item->getProduct();
            if ($product === null) {
                continue;
            }
            $cats = $product->getCategoryIds();
            if (is_array($cats)) {
                foreach ($cats as $catId) {
                    $categoryIds[(int) $catId] = true;
                }
            }
        }

        return array_keys($categoryIds);
    }
}
