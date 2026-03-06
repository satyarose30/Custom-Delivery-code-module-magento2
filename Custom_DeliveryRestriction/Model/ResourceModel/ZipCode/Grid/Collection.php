<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Model\ResourceModel\ZipCode\Grid;

use Custom\DeliveryRestriction\Model\ZipCode;
use Custom\DeliveryRestriction\Model\ResourceModel\ZipCode as ZipCodeResource;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Psr\Log\LoggerInterface;

/**
 * Grid collection — extends SearchResult so the UI component DataProvider
 * can use it directly with full filtering, sorting, and pagination support.
 */
class Collection extends SearchResult
{
    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface        $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface       $eventManager,
        string                 $mainTable      = 'custom_dr_zipcode',
        string                 $resourceModel  = ZipCodeResource::class,
        string                 $identifierName = 'zipcode_id',
        string                 $connectionName = 'default',
        ?AdapterInterface      $connection     = null,
        ?AbstractDb            $resource       = null
    ) {
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $mainTable,
            $resourceModel,
            $identifierName,
            $connectionName,
            $connection,
            $resource
        );
    }

    protected function _initSelect(): static
    {
        parent::_initSelect();
        $this->addFilterToMap('zipcode_id', 'main_table.zipcode_id');
        return $this;
    }
}
