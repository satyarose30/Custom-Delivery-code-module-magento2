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
        private readonly Config                $config,
        private readonly TransportBuilder      $transportBuilder,
        private readonly StateInterface        $inlineTranslation,
        private readonly StoreManagerInterface $storeManager,
        private readonly Logger                $logger
    ) {}

    /**
     * Send admin notification email when a restricted zip code is caught.
     */
    public function sendRestrictedZipAlert(
        string $zipCode,
        string $customerName,
        string $customerEmail,
        ?int $storeId = null
    ): void {
        if (!$this->config->isAdminEmailEnabled($storeId)) {
            return;
        }

        $adminEmail = trim($this->config->getAdminEmailRecipient($storeId));
        if ($adminEmail === '') {
            return;
        }

        $translationSuspended = false;

        try {
            $store = $this->storeManager->getStore($storeId);

            $this->inlineTranslation->suspend();
            $translationSuspended = true;

            $builder = $this->transportBuilder
                ->setTemplateIdentifier(self::TEMPLATE_RESTRICTED_ZIP)
                ->setTemplateOptions([
                    'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $store->getId(),
                ])
                ->setTemplateVars([
                    'zip_code' => htmlspecialchars($zipCode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                    'customer_name' => htmlspecialchars($customerName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                    'customer_email' => htmlspecialchars($customerEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                    'store_name' => (string) $store->getName(),
                ])
                ->setFromByScope($this->config->getAdminEmailSender($storeId), $storeId)
                ->addTo($adminEmail);

            foreach ($this->getCcRecipients($storeId) as $ccEmail) {
                $builder->addCc($ccEmail);
            }

            $builder->getTransport()->sendMessage();

            $this->logger->info('[DeliveryRestriction] Admin alert sent', [
                'zip' => $zipCode,
                'email' => $adminEmail,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error(
                '[DeliveryRestriction] Failed to send admin alert: ' . $e->getMessage(),
                ['exception' => $e]
            );
        } finally {
            if ($translationSuspended) {
                $this->inlineTranslation->resume();
            }
        }
    }

    /**
     * @return string[]
     */
    private function getCcRecipients(?int $storeId = null): array
    {
        $rawCc = trim($this->config->getAdminEmailCc($storeId));
        if ($rawCc === '') {
            return [];
        }

        $emails = array_map('trim', explode(',', $rawCc));
        $emails = array_filter($emails, static fn(string $email): bool => $email !== '');

        $validEmails = [];
        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $validEmails[] = $email;
            } else {
                $this->logger->warning('[DeliveryRestriction] Skipping invalid CC email address', [
                    'email' => $email,
                ]);
            }
        }

        return array_values(array_unique($validEmails));
    }
}
