<?php

namespace Valiton\Payment\SaferpayBundle\Client;


use JMS\Payment\CoreBundle\Model\FinancialTransactionInterface;
use Payment\Saferpay\Data\Collection\CollectionItemInterface;
use Payment\Saferpay\Data\PayCompleteParameter;
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
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var SaferpayDataHelperInterface
     */
    protected $saferpayDataHelper;



    /**
     * Client constructor.
     * @param AuthenticationStrategyInterface $authenticationStrategy
     * @param SaferpayDataHelperInterface $saferpayDataHelper
     */
    public function __construct(AuthenticationStrategyInterface $authenticationStrategy,
                                SaferpayDataHelperInterface $saferpayDataHelper)
    {
        $this->authenticationStrategy = $authenticationStrategy;
        $this->saferpayDataHelper = $saferpayDataHelper;
    }

    /**
     * Create payment init
     *
     * @param CollectionItemInterface $payInitParameter
     * @param FinancialTransactionInterface $transaction
     * @return PayConfirmParameter
     */
    public function createPayInit(CollectionItemInterface $payInitParameter, FinancialTransactionInterface $transaction)
    {
        $data = $payInitParameter->getData();
        $requestData = $this->saferpayDataHelper->buildPayInitObj($data);

        $response = $this->sendApiRequest($this->saferpayDataHelper->getPayInitUrl(), $requestData);
        $responseData = $this->saferpayDataHelper->getDataFromResponse($response);

        // use field TrackingId to keep track of the returned Token
        $transaction->setTrackingId($responseData['Token']);

        return $responseData['RedirectUrl'];
    }

    /**
     * Verify payment confirm
     *
     * @param FinancialTransactionInterface $transaction
     * @param  CollectionItemInterface $payConfirmParameter
     * @return CollectionItemInterface
     */
    public function verifyPayConfirm(FinancialTransactionInterface $transaction, CollectionItemInterface $payConfirmParameter = null)
    {
        $requestData = $this->saferpayDataHelper->buildPayConfirmObj($transaction->getTrackingId());

        $response = $this->sendApiRequest($this->saferpayDataHelper->getPayConfirmUrl(), $requestData);
        $responseData = $this->saferpayDataHelper->getDataFromResponse($response);

        if (null == $payConfirmParameter) {
            $payConfirmParameter = new PayConfirmParameter();
        }

        $payConfirmParameter->set('ID',$responseData["Transaction"]["Id"] );
        $payConfirmParameter->set('AMOUNT',$responseData["Transaction"]["Amount"]["Value"] );
        $payConfirmParameter->set('CURRENCY',$responseData["Transaction"]["Amount"]["CurrencyCode"] );
        $payConfirmParameter->set('TOKEN', $transaction->getTrackingId());
        $payConfirmParameter->set('CARDREFID', $responseData["PaymentMeans"]["DisplayText"]);

        return $payConfirmParameter;
    }

    /**
     * Pay complete v2
     *
     * @param  CollectionItemInterface            $payConfirmParameter
     * @param  CollectionItemInterface            $payCompleteParameter
     * @param  CollectionItemInterface            $payCompleteResponse
     * @return CollectionItemInterface
     * @throws \Exception
     */
    public function payCompleteV2(
        CollectionItemInterface $payConfirmParameter,
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

        $requestData = $this->saferpayDataHelper->buildPayCompleteObj($payCompleteParameter->getId());

        $response = $this->sendApiRequest($this->saferpayDataHelper->getPayCompleteUrl(), $requestData);

        if (null == $payCompleteResponse) {
            $payCompleteResponse = new PayCompleteResponse();
        }

        $payCompleteResponse->setResult($response->getStatusCode());

        return $payCompleteResponse;
    }

    /**
     * Send api request
     *
     * @param string $url
     * @param string $data
     * @return \Guzzle\Http\Message\Response
     * @throws \Exception
     */
    protected function sendApiRequest($url, $data)
    {
        $this->getLogger()->debug($url);
        $this->getLogger()->debug($data);

        $client = new \Guzzle\Http\Client();
        $client->setBaseUrl($url);
        $client->setDefaultOption('exceptions', false);

        $request = $client->post();
        $request->setHeaders($this->saferpayDataHelper->getNecessaryRequestHeaders());
        $this->authenticationStrategy->authenticate($request);
        $request->setBody($data);

        $response = $request->send();

        $this->getLogger()->debug((string) $response->getBody());

        if ($response->getStatusCode() != 200) {
            $errorInfo = $this->saferpayDataHelper->tryGetErrorInfoFromResponse($response);
            $this->getLogger()->critical('Saferpay: request failed with statuscode: ' . $response->getStatusCode() . '! ' . $errorInfo);
            throw new \Exception('Saferpay: request failed with statuscode: ' . $response->getStatusCode() . '! ' . $errorInfo);
        }

        return $response;
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