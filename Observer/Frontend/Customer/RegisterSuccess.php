<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Funarbe\Frete\Observer\Frontend\Customer;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Funarbe\Helper\Helper\Data;

class RegisterSuccess implements ObserverInterface
{
    /**
     * @var \Funarbe\Helper\Helper\Data
     */
    protected Data $helper;

    public function __construct(
        Data $helper
    ) {
        $this->helper = $helper;
    }

    /**
     * Execute observer
     *
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(Observer $observer): void
    {
        $customer = $observer->getEvent()->getCustomer();
        $cpf = $customer->getTaxvat();
        $response = $this->helper->getIntegratorRmClienteFornecedor($cpf);

        if ($response) {
            $idCustomer = $customer->getId();

            $objectManager = ObjectManager::getInstance();
            $resource = $objectManager->get(ResourceConnection::class);

            $connection = $resource->getConnection();
            $customer_entity = $resource->getTableName('customer_entity');

            $sql = "UPDATE $customer_entity ce SET ce.colaborador = 1 WHERE ce.entity_id=$idCustomer";

            $connection->query($sql);
        }
    }
}
