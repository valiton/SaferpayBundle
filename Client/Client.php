<?php

namespace Valiton\Payment\SaferpayBundle\Client;


use JMS\Payment\CoreBundle\Model\FinancialTransactionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Valiton\Payment\SaferpayBundle\Client\Authentication\AuthenticationStrategyInterface;


/**
 * Client - inspired by Payment\Saferpay\Saferpay class
 *
 * @package Valiton\Payment\SaferpayBundle\Client
 * @author Sven Cludius<sven.cludius@valiton.com>
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
     * @param array $payInitParameter
     * @param FinancialTransactionInterface $transaction
     * @return string
     */
    public function createPayInit(array $payInitParameter, FinancialTransactionInterface $transaction)
    {
        $requestData = $this->saferpayDataHelper->buildPayInitObj($payInitParameter);

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
     * @param array $payConfirmParameter
     * @return array
     */
    public function verifyPayConfirm(FinancialTransactionInterface $transaction, array $payConfirmParameter = null)
    {
        $requestData = $this->saferpayDataHelper->buildPayConfirmObj($transaction->getTrackingId());

        $response = $this->sendApiRequest($this->saferpayDataHelper->getPayConfirmUrl(), $requestData);
        $responseData = $this->saferpayDataHelper->getDataFromResponse($response);

        if (null === $payConfirmParameter) {
            $payConfirmParameter = array();
        }

        $payConfirmParameter['id'] = $responseData['Transaction']['Id'];
        $payConfirmParameter['amount'] = $responseData['Transaction']['Amount']['Value'];
        $payConfirmParameter['currency'] = $responseData['Transaction']['Amount']['CurrencyCode'];
        $payConfirmParameter['token'] = $transaction->getTrackingId();

        if (isset($responseData['PaymentMeans'])
            && isset($responseData['PaymentMeans']['Brand'])
            && isset($responseData['PaymentMeans']['Brand']['PaymentMethod'])
        ) {
            $payConfirmParameter['cardbrand'] = $responseData['PaymentMeans']['Brand']['PaymentMethod'];
        }

        if (isset($responseData['PaymentMeans'])
            && isset($responseData['PaymentMeans']['DisplayText'])
        ) {
            $payConfirmParameter['cardmask'] = $responseData['PaymentMeans']['DisplayText'];
        }

        if (isset($responseData['PaymentMeans'])
            && isset($responseData['PaymentMeans']['Card'])
            && isset($responseData['PaymentMeans']['Card']['ExpYear'])
        ) {
            $payConfirmParameter['cardvalidyear'] = $responseData['PaymentMeans']['Card']['ExpYear'];
        }

        if (isset($responseData['PaymentMeans'])
            && isset($responseData['PaymentMeans']['Card'])
            && isset($responseData['PaymentMeans']['Card']['ExpMonth'])
        ) {
            $payConfirmParameter['cardvalidmonth'] = $responseData['PaymentMeans']['Card']['ExpMonth'];
        }

        if (isset($responseData['RegistrationResult'])
            && isset($responseData['RegistrationResult']['Success'])
            && $responseData['RegistrationResult']['Success']
            && isset($responseData['RegistrationResult']['Alias'])
            && isset($responseData['RegistrationResult']['Alias']['Id'])
        ) {
            $payConfirmParameter['cardrefid'] = $responseData['RegistrationResult']['Alias']['Id'];
        }

        return $payConfirmParameter;
    }

    /**
     * Pay complete v2
     *
     * @param  array            $payConfirmParameter
     * @param  array            $payCompleteParameter
     * @param  array            $payCompleteResponse
     * @return array
     * @throws \Exception
     */
    public function payCompleteV2(
        array $payConfirmParameter,
        array $payCompleteParameter = null,
        array $payCompleteResponse = null
    ) {
        if (!isset($payConfirmParameter['id'])) {
            $this->getLogger()->critical('Saferpay: call confirm before complete!');
            throw new \Exception('Saferpay: call confirm before complete!');
        }

        if (null === $payCompleteParameter) {
            $payCompleteParameter = array();
        }
        $payCompleteParameter['id'] = $payConfirmParameter['id'];
        $payCompleteParameter['amount'] = $payConfirmParameter['amount'];

        $requestData = $this->saferpayDataHelper->buildPayCompleteObj($payCompleteParameter['id']);

        $response = $this->sendApiRequest($this->saferpayDataHelper->getPayCompleteUrl(), $requestData);

        if (null === $payCompleteResponse) {
            $payCompleteResponse = array();
        }

        $payCompleteResponse['result'] = $response->getStatusCode();

        return $payCompleteResponse;
    }

    /**
     * Send api request
     *
     * @param string $url
     * @param string $data
     * @return ResponseInterface;
     * @throws \Exception
     */
    protected function sendApiRequest($url, $data)
    {
        $this->getLogger()->debug($url);
        $this->getLogger()->debug($data);

        $client = new \GuzzleHttp\Client();

        $options = [
            'headers' => $this->saferpayDataHelper->getNecessaryRequestHeaders(),
            'body' => $data,
        ];

        $this->authenticationStrategy->authenticate($options);

        $response = $client->request(
            'POST',
            $url,
            $options
        );

        $this->getLogger()->debug((string) $response->getBody());

        if ($response->getStatusCode() !== 200) {
            $errorInfo = $this->saferpayDataHelper->tryGetErrorInfoFromResponse($response);
            $this->getLogger()->critical('Saferpay: request failed with statuscode: ' . $response->getStatusCode() . '! ' . $errorInfo);
            throw new \Exception('Saferpay: request failed with statuscode: ' . $response->getStatusCode() . '! ' . $errorInfo);
        }

        return $response;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

}
