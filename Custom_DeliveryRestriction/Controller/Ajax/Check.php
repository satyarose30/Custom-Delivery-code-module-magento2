<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Controller\Ajax;

use Custom\DeliveryRestriction\Model\Config;
use Custom\DeliveryRestriction\Model\ZipValidator;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Store\Model\StoreManagerInterface;
use Custom\DeliveryRestriction\Logger\Logger;

/**
 * POST /deliveryrestriction/ajax/check
 *
 * Accepts: { zip_code: string, form_key: string }
 * Returns: JSON — see execute() docblock.
 *
 * Implements CsrfAwareActionInterface so Magento does NOT auto-reject
 * the POST before we get a chance to validate the form key ourselves.
 */
class Check implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /** Allowed characters in a zip / postal code */
    private const ZIP_PATTERN = '/^[a-zA-Z0-9\s\-]{2,10}$/';

    public function __construct(
        private readonly RequestInterface      $request,
        private readonly JsonFactory           $jsonFactory,
        private readonly ZipValidator          $zipValidator,
        private readonly Config                $config,
        private readonly FormKeyValidator      $formKeyValidator,
        private readonly StoreManagerInterface $storeManager,
        private readonly Logger                $logger
    ) {}

    // ── CsrfAwareActionInterface ────────────────────────────────────────────────

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        // We handle CSRF via Magento form_key, not the built-in CSRF validator
        return true;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    // ── Action ──────────────────────────────────────────────────────────────────

    /**
     * @return Json
     *  On error:   { error: true,  message: string }
     *  On success: { error: false, available: bool, zip_code: string,
     *                message: string, delivery_message?: string,
     *                delivery?: { min_date, max_date, min_days, max_days } }
     */
    public function execute(): Json
    {
        $result = $this->jsonFactory->create();

        try {
            // 1. Validate form key (CSRF protection)
            if (!$this->formKeyValidator->validate($this->request)) {
                return $result->setData($this->errorPayload(
                    (string) __('Invalid security token. Please refresh the page.')
                ));
            }

            // 2. Sanitise input
            $rawZip = trim((string) $this->request->getParam('zip_code', ''));

            if ($rawZip === '') {
                return $result->setData($this->errorPayload(
                    (string) __('Please enter a zip code.')
                ));
            }

            if (!preg_match(self::ZIP_PATTERN, $rawZip)) {
                return $result->setData($this->errorPayload(
                    (string) __('Please enter a valid zip / postal code (letters, digits, spaces, hyphens; 2–10 characters).')
                ));
            }

            // 3. Validate
            $storeId     = (int) $this->storeManager->getStore()->getId();
            $isAvailable = $this->zipValidator->isAvailable($rawZip, $storeId);

            $payload = [
                'error'     => false,
                'available' => $isAvailable,
                'zip_code'  => $rawZip,
            ];

            if ($isAvailable) {
                $payload['message'] = $this->config->getAvailableMessage($storeId);

                $delivery = $this->zipValidator->getEstimatedDelivery($storeId);
                if ($delivery !== null) {
                    $payload['delivery']         = $delivery;
                    $payload['delivery_message'] = (string) __(
                        'Estimated delivery: %1 – %2',
                        $delivery['min_date'],
                        $delivery['max_date']
                    );
                }
            } else {
                $payload['message'] = $this->config->getUnavailableMessage($storeId);
            }

            return $result->setData($payload);

        } catch (\Throwable $e) {
            $this->logger->error(
                '[Custom_DeliveryRestriction] AJAX check failed: ' . $e->getMessage(),
                ['exception' => $e]
            );

            return $result->setData($this->errorPayload(
                (string) __('An unexpected error occurred. Please try again.')
            ));
        }
    }

    /** @return array{error:true, message:string} */
    private function errorPayload(string $message): array
    {
        return ['error' => true, 'message' => $message];
    }
}
