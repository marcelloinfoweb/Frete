<?php

namespace Funarbe\Frete\Model\Carrier;

use Funarbe\Helper\Helper\Data;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\ResultFactory;
use Psr\Log\LoggerInterface;

/**
 * Custom shipping model
 */
class FreteShipping extends AbstractCarrier implements CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'fretefunarbe';

    /**
     * @var bool
     */
    protected $_isFixed = true;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected Session $_checkoutSession;

    /**
     * @var \Magento\Shipping\Model\Rate\ResultFactory
     */
    private ResultFactory $rateResultFactory;

    /**
     * @var \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory
     */
    private MethodFactory $rateMethodFactory;

    /**
     * @var \Funarbe\Helper\Helper\Data
     */
    private Data $helper;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Funarbe\Helper\Helper\Data $helper
     * @param array $data
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        ResultFactory $rateResultFactory,
        MethodFactory $rateMethodFactory,
        Session $checkoutSession,
        Data $helper,
        array $data = []
    ) {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
        $this->rateResultFactory = $rateResultFactory;
        $this->rateMethodFactory = $rateMethodFactory;
        $this->_checkoutSession = $checkoutSession;
        $this->helper = $helper;
    }

    /**
     * Custom Shipping Rates Collector
     *
     * @param RateRequest $request
     * @return \Magento\Shipping\Model\Rate\Result
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Safe\Exceptions\JsonException
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $result = $this->rateResultFactory->create();

        $cep = $this->_checkoutSession->getQuote()->getShippingAddress()->getPostcode();
        $cepNumeros = preg_replace('/[\D]/', '', $cep);
        $cpfCliente = $this->_checkoutSession->getQuote()->getCustomer()->getTaxvat();
        $funcionario = $this->helper->getIntegratorRmClienteFornecedor($cpfCliente);

        /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
        $method = $this->rateMethodFactory->create();
        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->getConfigData('title'));
        $method->setMethod($this->_code);

        // Verificar se é funcionario Funarbe
        if (!$funcionario) { // false
            $method->setMethodTitle($this->getConfigData('name'));
            $taxaEntrega = '';
            $valorTotal = (float)$request->getBaseSubtotalInclTax();
            $cep = $this->helper->curlGet(
                "http://dev.marcelo.controle-super/ecommerce/onde-entregamos/frete?cep=$cepNumeros"
            );
            if ($cep['taxa'] === 30) {
                $taxaEntrega = 30.0;
            } elseif ($valorTotal > 0.01 && $valorTotal < 100.0) {
                $taxaEntrega = $cep['taxa'];
            } elseif ($valorTotal > 100.01 && $valorTotal < 149.99) {
                $taxaEntrega = 10.0;
            }
            $shippingCost = (float)$taxaEntrega;
        } else { // true
            $method->setMethodTitle($this->getConfigData('text_shipping_free'));
            $shippingCost = (float)0.0;
        }

        $method->setPrice($shippingCost);
        $method->setCost($shippingCost);
        $result->append($method);

        return $result;
    }

    /**
     * @return array
     */
    public function getAllowedMethods(): array
    {
        return [$this->_code => $this->getConfigData('name')];
    }
}
