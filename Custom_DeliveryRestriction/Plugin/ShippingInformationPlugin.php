<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Plugin;

use Custom\DeliveryRestriction\Model\Config;
use Custom\DeliveryRestriction\Model\ZipValidator;
use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Model\ShippingInformationManagement;
use Magento\Framework\Exception\LocalizedException;
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
        private readonly Config                $config,
        private readonly ZipValidator          $zipValidator,
        private readonly StoreManagerInterface $storeManager
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
        ShippingInformationManagement  $subject,
        int                            $cartId,
        ShippingInformationInterface   $addressInformation
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

        if (!$this->zipValidator->isAvailable($zipCode, $storeId)) {
            $template = $this->config->getCheckoutErrorMessage($storeId);
            $message  = str_replace('%1', htmlspecialchars($zipCode, ENT_QUOTES, 'UTF-8'), $template);
            throw new LocalizedException(__($message));
        }

        return [$cartId, $addressInformation];
    }
}
