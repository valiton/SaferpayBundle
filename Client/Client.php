<?php

namespace Valiton\Payment\SaferpayBundle\Client;

use Payment\HttpClient\HttpClientInterface;
use Payment\Saferpay\Data\Collection\CollectionItemInterface;
use Payment\Saferpay\Data\PayCompleteParameter;
use Payment\Saferpay\Data\PayCompleteParameterInterface;
use Payment\Saferpay\Data\PayCompleteResponse;
use Payment\Saferpay\Data\PayConfirmParameter;
use Payment\Saferpay\Data\PayInitParameterWithDataInterface;
use Psr\Log\LoggerInterface;
use Valiton\Payment\SaferpayBundle\Client\Authentication\AuthenticationStrategyInterface;

/**
 * Client - inspeared by Payment\Saferpay\Saferpay class
 *
 * @package Valiton\Payment\SaferpayBundle\Client
 * @author Sven Cludius<sven.cludius@valiton.com>
 * @see Payment\Saferpay\Saferpay
 */
class Client 
{
    const PAY_INIT_PARAM_DATA = 'DATA';
    const PAY_INIT_PARAM_SIGNATURE = 'SIGNATURE';

    const VERIFY_PAY_PARAM_STATUS_OK = 'OK';
    const VERIFY_PAY_PARAM_STATUS_ERROR = 'ERROR';

    const PAY_CONFIRM_PARAM_ID = 'ID';
    const PAY_CONFIRM_PARAM_AMOUNT = 'AMOUNT';
    const PAY_CONFIRM_PARAM_ACTION = 'ACTION';

    /**
     * @var AuthenticationStrategyInterface
     */
    protected $authenticationStrategy;

    /**
     * @var HttpClientInterface
     */
    protected $httpClient;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Constructor
     *
     * @param AuthenticationStrategyInterface $authenticationStrategy
     * @param HttpClientInterface $httpClient
     */
    public function __construct(AuthenticationStrategyInterface $authenticationStrategy, HttpClientInterface $httpClient)
    {
        $this->authenticationStrategy = $authenticationStrategy;
        $this->httpClient = $httpClient;
    }

    /**
     * Create payment init
     *
     * @param  CollectionItemInterface $payInitParameter
     * @return PayConfirmParameter
     */
    public function createPayInit(CollectionItemInterface $payInitParameter)
    {
        return $this->sendApiRequest($payInitParameter->getRequestUrl(), $payInitParameter->getData());
    }

    /**
     * Verify payment confirm
     *
     * @param string $xml
     * @param string $signature
     * @param  CollectionItemInterface $payConfirmParameter
     * @return CollectionItemInterface
     */
    public function verifyPayConfirm($xml, $signature, CollectionItemInterface $payConfirmParameter = null)
    {
        if (null == $payConfirmParameter) {
            $payConfirmParameter = new PayConfirmParameter();
        }
        $this->fillDataFromXML($payConfirmParameter, $xml);
        $response = $this->sendApiRequest($payConfirmParameter->getRequestUrl(), array(
            self::PAY_INIT_PARAM_DATA => $xml,
            self::PAY_INIT_PARAM_SIGNATURE => $signature
        ));

        $status = self::VERIFY_PAY_PARAM_STATUS_ERROR;
        $parameterOrMessage = 'Invalid';
        if (null != $response && false !== $responseFields = explode(':', $response, 2)) {
            if (count($responseFields) == 2) {
                list($status, $parameterOrMessage) = $responseFields;
                if (self::VERIFY_PAY_PARAM_STATUS_OK == $status) {
                    parse_str($parameterOrMessage, $parameters);
                    foreach($parameters as $field => $value) {
                        $payConfirmParameter->set($field, $value);
                    }
                }
            }
        }
        if (self::VERIFY_PAY_PARAM_STATUS_ERROR == $status) {
            throw new \Exception($parameterOrMessage);
        }

        return $payConfirmParameter;
    }

    /**
     * Pay complete v2
     *
     * @param  CollectionItemInterface            $payConfirmParameter
     * @param  string                             $action
     * @param  CollectionItemInterface            $payCompleteParameter
     * @param  CollectionItemInterface            $payCompleteResponse
     * @return CollectionItemInterface
     * @throws \Exception
     */
    public function payCompleteV2(
        CollectionItemInterface $payConfirmParameter,
        $action = PayCompleteParameterInterface::ACTION_SETTLEMENT,
        CollectionItemInterface $payCompleteParameter = null,
        CollectionItemInterface $payCompleteResponse = null
    ) {
        if (null == $payConfirmParameter->get('ID')) {
            $this->getLogger()->critical('Saferpay: call confirm before complete!');
            throw new \Exception('Saferpay: call confirm before complete!');
        }

        if (null == $payCompleteParameter) {
            $payCompleteParameter = new PayCompleteParameter();
        }
        $payCompleteParameter->set(self::PAY_CONFIRM_PARAM_ID, $payConfirmParameter->get(self::PAY_CONFIRM_PARAM_ID));
        $payCompleteParameter->set(self::PAY_CONFIRM_PARAM_AMOUNT, $payConfirmParameter->get(self::PAY_CONFIRM_PARAM_AMOUNT));
        $payCompleteParameter->set(self::PAY_CONFIRM_PARAM_ACTION, $action);

        $payCompleteParameterData = $payCompleteParameter->getData();
        $response = $this->sendApiRequest($payCompleteParameter->getRequestUrl(), $payCompleteParameterData, true);

        if (null == $payCompleteResponse) {
            $payCompleteResponse = new PayCompleteResponse();
        }
        $this->fillDataFromXML($payCompleteResponse, substr($response, 3));

        return $payCompleteResponse;
    }

    /**
     * Send api request
     *
     * @param $url
     * @param array $data
     * @param bool $withPassword
     * @return mixed
     * @throws \Exception
     */
    protected function sendApiRequest($url, array $data, $withPassword = false)
    {
        $this->authenticationStrategy->authenticate($data, $withPassword);

        $data = http_build_query($data);

        $this->getLogger()->debug($url);
        $this->getLogger()->debug($data);

        $response = $this->httpClient->request(
            'POST',
            $url,
            $data,
            array('Content-Type' => 'application/x-www-form-urlencoded')
        );

        $this->getLogger()->debug($response->getContent());

        if ($response->getStatusCode() != 200) {
            $this->getLogger()->critical('Saferpay: request failed with statuscode: {statuscode}!', array('statuscode' => $response->getStatusCode()));
            throw new \Exception('Saferpay: request failed with statuscode: ' . $response->getStatusCode() . '!');
        }

        if (strpos($response->getContent(), 'ERROR') !== false) {
            $this->getLogger()->critical('Saferpay: request failed: {content}!', array('content' => $response->getContent()));
            throw new \Exception('Saferpay: request failed: ' . $response->getContent() . '!');
        }

        return $response->getContent();
    }

    /**
     * Fill data from XML
     *
     * @param CollectionItemInterface $data
     * @param $xml
     * @throws \Exception
     */
    protected function fillDataFromXML(CollectionItemInterface $data, $xml)
    {
        $document = new \DOMDocument();
        $fragment = $document->createDocumentFragment();

        if (!$fragment->appendXML($xml)) {
            $this->getLogger()->critical('Saferpay: Invalid xml received from saferpay');
            throw new \Exception('Saferpay: Invalid xml received from saferpay!');
        }

        foreach ($fragment->firstChild->attributes as $attribute) {
            /** @var \DOMAttr $attribute */
            $data->set($attribute->nodeName, $attribute->nodeValue);
        }
    }

    /**
     * get logger
     *
     * @return \Symfony\Component\HttpKernel\Log\LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * set logger
     *
     * @param \Symfony\Component\HttpKernel\Log\LoggerInterface $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

}