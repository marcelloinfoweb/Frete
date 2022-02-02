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
    protected Session $checkoutSession;

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

    private \Magento\Checkout\Model\Cart $cart;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Funarbe\Helper\Helper\Data $helper
     * @param \Magento\Checkout\Model\Cart $cart
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
        \Magento\Checkout\Model\Cart $cart,
        array $data = []
    ) {
        $this->cart = $cart;
        $this->rateResultFactory = $rateResultFactory;
        $this->rateMethodFactory = $rateMethodFactory;
        $this->checkoutSession = $checkoutSession;
        $this->helper = $helper;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
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

        $result = $this->rateResultFactory->create();
        $couponFunc = 'descontoColaboradores';
        $couponZera = 'zeraodesconto';

        $cep = $this->checkoutSession->getQuote()->getShippingAddress()->getPostcode();
        if ($cep) {
            $cepNumeros = preg_replace('/\D/', '', $cep);
            $cpfCliente = $this->checkoutSession->getQuote()->getCustomer()->getTaxvat();
            $funcionario = $this->helper->getIntegratorRmClienteFornecedor($cpfCliente);

            $method = $this->rateMethodFactory->create();
            $method->setCarrier($this->_code);
            $method->setCarrierTitle($this->getConfigData('title'));
            $method->setMethod($this->_code);

            // Verificar se é funcionário Funarbe
            if (!$funcionario) {// false
//                $this->checkoutSession->getQuote()->setCouponCode($couponZera)->collectTotals()->save();
                $method->setMethodTitle($this->getConfigData('name'));
                $taxaEntrega = '';
                $valorTotal = (float)$request->getBaseSubtotalInclTax();
                $cep = $this->helper->curlGet(
                    "https://controle.supermercadoescola.org.br/ecommerce/onde-entregamos/frete?cep=$cepNumeros"
                );

                if (isset($cep)) {
                    if ($cep['taxa'] === 30) {
                        if ($valorTotal > 0.01 && $valorTotal < 170.0) {
                            $taxaEntrega = $cep['taxa'];
                        }
                        if ($valorTotal > 170.01 && $valorTotal < 250.00) {
                            $taxaEntrega = 20.0;
                        }
                        if ($valorTotal > 250.01) {
                            $taxaEntrega = 10.0;
                        }
                    }
                    if ($cep['taxa'] === 15) {
                        if ($valorTotal > 0.01 && $valorTotal < 100.0) {
                            $taxaEntrega = $cep['taxa'];
                        }
                        if ($valorTotal > 100.01 && $valorTotal < 150.00) {
                            $taxaEntrega = 10.0;
                        }
                        if ($valorTotal > 150.01) {
                            $method->setMethodTitle($this->getConfigData('text_shipping_free'));
                            $taxaEntrega = 0.0;
                        }
                    }
//                    var_dump($taxaEntrega);

                    $shippingCost = (float)$taxaEntrega;
                }
            } else {// true
                $method->setMethodTitle($this->getConfigData('text_shipping_free'));
                $shippingCost = 0.0;
            }

//            $this->checkoutSession->getQuote()->setCouponCode($couponFunc)->collectTotals()->save();

            $method->setPrice($shippingCost);
            $method->setCost($shippingCost);
            $result->append($method);

            return $result;
        }
    }

    /**
     * @return array
     */
    public function getAllowedMethods(): array
    {
        return [$this->_code => $this->getConfigData('name')];
    }
}
