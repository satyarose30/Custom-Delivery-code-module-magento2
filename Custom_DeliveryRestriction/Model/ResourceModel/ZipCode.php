<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * ZipCode ResourceModel — maps to table custom_dr_zipcode, primary key zipcode_id.
 */
class ZipCode extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('custom_dr_zipcode', 'zipcode_id');
    }
}
