<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Model;

use Magento\Framework\Model\AbstractModel;
use Custom\DeliveryRestriction\Model\ResourceModel\ZipCode as ZipCodeResource;

/**
 * ZipCode ORM model.
 *
 * Maps to table custom_dr_zipcode (defined in etc/db_schema.xml).
 *
 * @method int    getZipcodeId()
 * @method string getZipCode()
 * @method string getRestrictionType()
 * @method int    getStatus()
 * @method string getCustomerGroupIds()
 * @method string getCategoryIds()
 * @method string getStoreIds()
 * @method string getDescription()
 */
class ZipCode extends AbstractModel
{
    public const STATUS_ENABLED  = 1;
    public const STATUS_DISABLED = 0;

    protected function _construct(): void
    {
        $this->_init(ZipCodeResource::class);
    }

    /**
     * Returns customer group IDs as an integer array.
     * Empty array = applies to ALL groups.
     *
     * @return int[]
     */
    public function getCustomerGroupIdsArray(): array
    {
        $raw = (string) $this->getData('customer_group_ids');
        if (trim($raw) === '') {
            return [];
        }
        return array_map('intval', array_filter(explode(',', $raw)));
    }

    /**
     * Returns category IDs as an integer array.
     * Empty array = applies to ALL categories.
     *
     * @return int[]
     */
    public function getCategoryIdsArray(): array
    {
        $raw = (string) $this->getData('category_ids');
        if (trim($raw) === '') {
            return [];
        }
        return array_map('intval', array_filter(explode(',', $raw)));
    }

    /**
     * Returns store IDs as an integer array.
     * Empty array = applies to ALL stores.
     *
     * @return int[]
     */
    public function getStoreIdsArray(): array
    {
        $raw = (string) $this->getData('store_ids');
        if (trim($raw) === '') {
            return [];
        }
        return array_map('intval', array_filter(explode(',', $raw)));
    }
}
