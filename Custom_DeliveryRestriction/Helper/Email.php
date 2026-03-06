<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Helper;

use Custom\DeliveryRestriction\Logger\Logger;
use Custom\DeliveryRestriction\Model\Config;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Email notification helper.
 *
 * Sends an alert to the store admin whenever a customer attempts to
 * place an order to a restricted zip code.
 *
 * Email template: Custom_DeliveryRestriction::email/restricted_zip_alert.html
 * (registered in etc/email_templates.xml)
 */
class Email
{
    /** Template identifier registered in email_templates.xml */
    private const TEMPLATE_RESTRICTED_ZIP = 'custom_dr_restricted_zip_alert';

    public function __construct(
        private readonly Config               $config,
        private readonly TransportBuilder     $transportBuilder,
        private readonly StateInterface       $inlineTranslation,
        private readonly StoreManagerInterface $storeManager,
        private readonly Logger              $logger
    ) {}

    /**
     * Send admin notification email when a restricted zip code is caught.
     *
     * @param string $zipCode     The blocked zip code
     * @param string $customerName Customer's full name (or "Guest")
     * @param string $customerEmail Customer's email address
     * @param int|null $storeId
     */
    public function sendRestrictedZipAlert(
        string $zipCode,
        string $customerName,
        string $customerEmail,
        ?int   $storeId = null
    ): void {
        if (!$this->config->isAdminEmailEnabled($storeId)) {
            return;
        }

        $adminEmail = $this->config->getAdminEmailRecipient($storeId);
        if ($adminEmail === '') {
            return;
        }

        try {
            $store        = $this->storeManager->getStore($storeId);
            $storeName    = (string) $store->getName();
            $senderEmail  = $this->config->getAdminEmailSender($storeId);

            $this->inlineTranslation->suspend();

            $this->transportBuilder
                ->setTemplateIdentifier(self::TEMPLATE_RESTRICTED_ZIP)
                ->setTemplateOptions([
                    'area'  => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $store->getId(),
                ])
                ->setTemplateVars([
                    'zip_code'       => htmlspecialchars($zipCode, ENT_QUOTES, 'UTF-8'),
                    'customer_name'  => htmlspecialchars($customerName,  ENT_QUOTES, 'UTF-8'),
                    'customer_email' => htmlspecialchars($customerEmail, ENT_QUOTES, 'UTF-8'),
                    'store_name'     => $storeName,
                ])
                ->setFromByScope($senderEmail, $storeId);

            // Support CC field (optional, comma-separated in config)
            $transport = $this->transportBuilder
                ->addTo($adminEmail)
                ->getTransport();

            $transport->sendMessage();
            $this->inlineTranslation->resume();

            $this->logger->info('[DeliveryRestriction] Admin alert sent', [
                'zip'   => $zipCode,
                'email' => $adminEmail,
            ]);

        } catch (\Throwable $e) {
            $this->inlineTranslation->resume();
            $this->logger->error(
                '[DeliveryRestriction] Failed to send admin alert: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }
}
