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
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;


class LoginSuccess implements ObserverInterface
{
    /**
     * @var \Funarbe\Helper\Helper\Data
     */
    protected Data $helper;
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private LoggerInterface $logger;

    public function __construct(
        LoggerInterface $logger,
        Data $helper
    ) {
        $this->logger = $logger;
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
        $idCustomer = $customer->getId();

        $objectManager = ObjectManager::getInstance();

        $resource = $objectManager->get(ResourceConnection::class);

        $connection = $resource->getConnection();
        $customer_entity = $resource->getTableName('customer_entity');
        $customer_grid_flat = $resource->getTableName('customer_grid_flat');

        if ($response) {
            try {
                $sql = "UPDATE $customer_entity ce JOIN $customer_grid_flat cgf ON ce.entity_id = cgf.entity_id";
                $sql .= " SET ce.colaborador = 1, cgf.colaborador = 1 WHERE ce.entity_id=$idCustomer";
                $connection->query($sql);
            } catch (LocalizedException $exception) {
                $this->logger->error($exception);
            }
        } else {
            try {
                $sql = "UPDATE $customer_entity ce JOIN $customer_grid_flat cgf ON ce.entity_id = cgf.entity_id";
                $sql .= " SET ce.colaborador = 0, cgf.colaborador = 0 WHERE ce.entity_id=$idCustomer";
                $connection->query($sql);
            } catch (LocalizedException $exception) {
                $this->logger->error($exception);
            }
        }
    }
}

