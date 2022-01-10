<?php

/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Funarbe\Frete\Observer\Frontend\Customer;

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

        if ($response) {
            try {
                $customer->setGroupId(1)->save();
            } catch (LocalizedException $exception) {
                $this->logger->error($exception);
            }
        } else {
            try {
                $customer->setGroupId(1)->save();
            } catch (LocalizedException $exception) {
                $this->logger->error($exception);
            }
        }
    }
}

