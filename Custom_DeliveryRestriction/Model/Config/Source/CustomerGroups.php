<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Model\Config\Source;

use Magento\Customer\Model\GroupFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Provides all customer groups as options for the multiselect in system config.
 * Adapted from Magedelight PartialPayment — includes NOT LOGGED IN (group 0)
 * so admins can explicitly allow / block guest shoppers.
 */
class CustomerGroups implements OptionSourceInterface
{
    public function __construct(
        private readonly GroupFactory $groupFactory
    ) {}

    public function toOptionArray(): array
    {
        $items   = $this->groupFactory->create()->getCollection()->toOptionHash();
        $options = [];

        foreach ($items as $value => $label) {
            $options[] = ['value' => (int) $value, 'label' => (string) $label];
        }

        return $options;
    }
}
