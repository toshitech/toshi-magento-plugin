<?php

namespace Toshi\Shipping\Model\Config\Source;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as EavAttribute;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Eav\Model\Config;
use Toshi\Shipping\Logger\Logger;

class Attribute implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var Config
     */
    private $eavConfig;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct(
        CollectionFactory $collectionFactory,
        Config $eavConfig,
        Logger $logger
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->eavConfig = $eavConfig;
        $this->logger = $logger;
    }

    public function toOptionArray()
    {
        try {
            $productEntity = $this->eavConfig->getEntityType(Product::ENTITY);
        } catch (\Exception $e) {
            $this->logger->addError('[OPTION ARRAY FAILED]['. $e->getMessage() .']');
            return [];
        }

        /** @var Collection $collection */
        $collection = $this->collectionFactory->create()->addFieldToFilter('entity_type_id', $productEntity->getId());

        $result = [];

        /** @var EavAttribute $eavAttribute  */
        foreach ($collection->getItems() as $attribute) {
            $result[] = [
                'label' => $attribute->getFrontendLabel(),
                'value' => $attribute->getAttributeCode(),
            ];
        }

        return $result;
    }
}
