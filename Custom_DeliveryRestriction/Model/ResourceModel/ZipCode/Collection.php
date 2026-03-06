<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Model\ResourceModel\ZipCode;

use Custom\DeliveryRestriction\Model\ZipCode;
use Custom\DeliveryRestriction\Model\ResourceModel\ZipCode as ZipCodeResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * ZipCode Collection — provides filtered queries over custom_dr_zipcode.
 */
class Collection extends AbstractCollection
{
    /** @var string */
    protected $_idFieldName = 'zipcode_id';

    protected function _construct(): void
    {
        $this->_init(ZipCode::class, ZipCodeResource::class);
    }

    /**
     * Return only active (status=1) records.
     */
    public function addActiveFilter(): static
    {
        $this->addFieldToFilter('status', ZipCode::STATUS_ENABLED);
        return $this;
    }

    /**
     * Optionally scope to a specific store.
     * Includes records where store_ids IS NULL (= all stores).
     */
    public function addStoreFilter(int $storeId): static
    {
        $this->getSelect()->where(
            'store_ids IS NULL OR store_ids = "" OR FIND_IN_SET(?, store_ids)',
            $storeId
        );
        return $this;
    }

    /**
     * Optionally scope to a specific customer group.
     * Includes records where customer_group_ids IS NULL (= all groups).
     */
    public function addCustomerGroupFilter(int $customerGroupId): static
    {
        $this->getSelect()->where(
            'customer_group_ids IS NULL OR customer_group_ids = "" OR FIND_IN_SET(?, customer_group_ids)',
            $customerGroupId
        );
        return $this;
    }
}
