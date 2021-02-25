<?php
namespace Toshi\Shipping\Setup\Patch\Data;

use Magento\Eav\Setup\EavSetup;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class ChangeToshiDatesConfig implements DataPatchInterface
{
    /**
     *  @var \Magento\Framework\App\Config\Storage\WriterInterface
     */
    protected $configWriter;

    /**
     * @param WriterInterface $configWriter
     */
    public function __construct(
        WriterInterface $configWriter
    ) {
        $this->configWriter = $configWriter;
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $this->configWriter->save('carriers/toshi/holidays',  '{"item1":{"date":"01\/01"},"item2":{"date":"25\/12"},"item3":{"date":"26\/12"}}');
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }
}