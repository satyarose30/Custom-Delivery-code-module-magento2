<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Block\Product;

use Custom\DeliveryRestriction\Model\Config;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Block for the "Check Delivery Availability" widget on the product page.
 *
 * Template: Custom_DeliveryRestriction::product/zip_checker.phtml
 */
class ZipChecker extends Template
{
    protected $_template = 'Custom_DeliveryRestriction::product/zip_checker.phtml';

    public function __construct(
        Context $context,
        private readonly Config  $config,
        private readonly FormKey $formKey,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Whether to render the widget at all.
     */
    public function isEnabled(): bool
    {
        return $this->config->isEnabled() && $this->config->isProductPageEnabled();
    }

    public function getWidgetTitle(): string
    {
        return $this->escapeHtml($this->config->getWidgetTitle());
    }

    public function getInputPlaceholder(): string
    {
        return $this->escapeHtml($this->config->getInputPlaceholder());
    }

    /**
     * Returns the URL for the AJAX check controller.
     * Uses Magento's URL builder so it respects store base URL, HTTPS, etc.
     */
    public function getAjaxUrl(): string
    {
        return $this->getUrl('deliveryrestriction/ajax/check');
    }

    /**
     * JSON-encoded config blob for the RequireJS component.
     * All values are safely escaped for embedding in HTML attributes.
     */
    public function getComponentConfigJson(): string
    {
        $data = [
            'ajaxUrl' => $this->getAjaxUrl(),
            'formKey' => $this->formKey->getFormKey(),
        ];

        return (string) json_encode(
            $data,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES
        );
    }
}
