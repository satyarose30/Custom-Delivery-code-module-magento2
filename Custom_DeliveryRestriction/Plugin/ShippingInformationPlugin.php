<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Plugin;

use Custom\DeliveryRestriction\Model\Config;
use Custom\DeliveryRestriction\Model\ZipValidator;
use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Model\ShippingInformationManagement;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Fires when the customer clicks "Next" on the Shipping step in checkout.
 * If the shipping zip is restricted, we throw a LocalizedException which
 * Magento's checkout JS displays as an inline error — the user stays on
 * the shipping step and cannot proceed to payment.
 *
 * This is the FIRST of two protection layers:
 *   Layer 1 → This plugin  (shipping step)
 *   Layer 2 → ValidateZipOnOrderPlace observer  (order submission)
 */
class ShippingInformationPlugin
{
    public function __construct(
        private readonly Config $config,
        private readonly ZipValidator $zipValidator,
        private readonly StoreManagerInterface $storeManager,
        private readonly CartRepositoryInterface $cartRepository
    ) {}

    /**
     * @param ShippingInformationManagement $subject
     * @param int                           $cartId
     * @param ShippingInformationInterface  $addressInformation
     * @return array{0:int, 1:ShippingInformationInterface}
     *
     * @throws LocalizedException
     */
    public function beforeSaveAddressInformation(
        ShippingInformationManagement $subject,
        int $cartId,
        ShippingInformationInterface $addressInformation
    ): array {
        $storeId = (int) $this->storeManager->getStore()->getId();

        if (!$this->config->isEnabled($storeId) || !$this->config->isBlockOrderEnabled($storeId)) {
            return [$cartId, $addressInformation];
        }

        $shippingAddress = $addressInformation->getShippingAddress();
        if ($shippingAddress === null) {
            return [$cartId, $addressInformation];
        }

        $zipCode = trim((string) $shippingAddress->getPostcode());
        if ($zipCode === '') {
            return [$cartId, $addressInformation];
        }

        $quote = $this->cartRepository->getActive($cartId);
        $customerGroupId = (int) $quote->getCustomerGroupId();
        $categoryIds = $this->extractCategoryIds($quote);

        if (!$this->zipValidator->isAvailable($zipCode, $storeId, $customerGroupId, $categoryIds)) {
            $message = $this->config->getCheckoutErrorMessageForZip($zipCode, $storeId);
            throw new LocalizedException(__($message));
        }

        return [$cartId, $addressInformation];
    }

    /**
     * @return int[]
     */
    private function extractCategoryIds(\Magento\Quote\Model\Quote $quote): array
    {
        $categoryIds = [];

        foreach ($quote->getAllVisibleItems() as $item) {
            $product = $item->getProduct();
            if ($product === null) {
                continue;
            }

            $cats = $product->getCategoryIds();
            if (!is_array($cats)) {
                continue;
            }

            foreach ($cats as $catId) {
                $categoryIds[(int) $catId] = true;
            }
        }

        return array_keys($categoryIds);
    }
}
