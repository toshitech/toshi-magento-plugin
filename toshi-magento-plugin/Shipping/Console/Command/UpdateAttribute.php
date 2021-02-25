<?php
namespace Toshi\Shipping\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\State;

/**
 * Class UpdateAttribute
 */
class UpdateAttribute extends Command {

    /**
     * @var CollectionFactory
     */
    private $productFactory;

    private $defaultStore = 0;

    /**
     * @param LoggerInterface  $logger
     */
    public function __construct(
        CollectionFactory $productFactory,
        State $state
        
    ) {
        parent::__construct();
        $this->productFactory = $productFactory;
        $this->state = $state;
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('toshi:update:attribute');
        $this->setDescription('Will update all products to set the default value of the available_for_toshi attribute');

        parent::configure();
    }

    /**
     * Execute the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return null|int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);

        $products = $this->productFactory->create()->getItems();

        foreach ($products as $product) {
            $product->addAttributeUpdate('available_for_toshi', 1, $this->defaultStore);
            $output->writeln('<comment>Updated product ' . $product->getId() . '</comment>');
        }

        $output->writeln('<info>All products have been updated.</info>');
    }
}