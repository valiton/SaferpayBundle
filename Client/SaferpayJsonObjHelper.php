<?php

namespace Valiton\Payment\SaferpayBundle\Client;


use Valiton\Payment\SaferpayBundle\Client\Authentication\JsonAuthenticationStrategy;
use Psr\Http\Message\ResponseInterface;
use Faker\Provider\Uuid;

/**
 * Class SaferpayJsonObjHelper
 * Builds JSON encoded arrays according to http://saferpay.github.io/jsonapi/
 *
 * @package Valiton\Payment\SaferpayBundle\Client
 */
class SaferpayJsonObjHelper implements SaferpayDataHelperInterface
{
    const SPEC_VERSION = '1.4';
    const RETRY_INDICATOR = 0;

    /**
     * @var JsonAuthenticationStrategy
     */
    protected $authenticationStrategy;

    /**
     * @var string
     */
    protected $paymentPageInitializeUrl;

    /**
     * @var string
     */
    protected $paymentPageAssertUrl;

    /**
     * @var string
     */
    protected $transactionCaptureUrl;

    /**
     * @var string
     */
    protected $contentTypeHeader;

    /**
     * @var string
     */
    protected $acceptHeader;

    /**
     * SaferpayJsonObjHelper constructor.
     * @param JsonAuthenticationStrategy $authenticationStrategy
     * @param string $baseUrl
     * @param string $paymentPageInitializeUrl
     * @param string $paymentPageAssertUrl
     * @param string $transactionCaptureUrl
     * @param string $contentTypeHeader
     * @param string $acceptHeader
     */
    function __construct(JsonAuthenticationStrategy $authenticationStrategy,
                         $baseUrl,
                         $paymentPageInitializeUrl,
                         $paymentPageAssertUrl,
                         $transactionCaptureUrl,
                         $contentTypeHeader,
                         $acceptHeader)
    {
        $this->authenticationStrategy = $authenticationStrategy;
        $this->paymentPageInitializeUrl = $baseUrl . $paymentPageInitializeUrl;
        $this->paymentPageAssertUrl = $baseUrl . $paymentPageAssertUrl;
        $this->transactionCaptureUrl = $baseUrl . $transactionCaptureUrl;
        $this->contentTypeHeader = $contentTypeHeader;
        $this->acceptHeader = $acceptHeader;
    }

    /**
     * @param array $data
     * @return string
     */
    public function buildPayInitObj(array $data)
    {
        return $this->buildPaymentPageInitializeObj($data);
    }

    /**
     * @param string $token
     * @return string
     */
    public function buildPayConfirmObj($token)
    {
        return $this->buildPaymentPageAssertObj($token);
    }

    /**
     * @param string $transactionId
     * @return string
     */
    public function buildPayCompleteObj($transactionId)
    {
        return $this->buildTransactionCaptureObj($transactionId);
    }

    /**
     * @param ResponseInterface $response
     * @return array
     */
    public function getDataFromResponse(ResponseInterface $response)
    {
        return json_decode($response->getBody(), true);
    }

    /**
     * @param ResponseInterface $response
     * @return string
     */
    public function tryGetErrorInfoFromResponse(ResponseInterface $response)
    {
        $errorInfo = "";
        if (strtolower($response->getHeaderLine('Content-Type')) === strtolower($this->contentTypeHeader)) {
            $responseData = $this->getDataFromResponse($response);
            $errorInfo = 'ErrorName: ' . $responseData['ErrorName'] . ' ErrorMessage: ' . $responseData['ErrorMessage'];
            if (array_key_exists('ErrorDetail', $responseData))
            {
                $errorInfo .= ' ErrorDetail:';
                foreach ($responseData['ErrorDetail'] as $detail)
                {
                    $errorInfo .= ' ' . $detail;
                }
            }
        }
        return $errorInfo;
    }

    /**
     * @return string
     */
    public function getPayInitUrl()
    {
        return $this->paymentPageInitializeUrl;
    }

    /**
     * @return string
     */
    public function getPayConfirmUrl()
    {
        return $this->paymentPageAssertUrl;
    }

    /**
     * @return string
     */
    public function getPayCompleteUrl()
    {
        return $this->transactionCaptureUrl;
    }


    /**
     * @return array
     */
    public function getNecessaryRequestHeaders()
    {
        return array(
            'Content-Type' => $this->contentTypeHeader,
            'Accept' => $this->acceptHeader
        );
    }

    /**
     * @param array $data
     * @return string
     */
    protected function  buildPaymentPageInitializeObj(array $data)
    {
        $jsonData = array(
            'RequestHeader' => $this->buildRequestHeader(),

            'TerminalId' => $this->authenticationStrategy->getTerminalId(),

            'Payment' => array(
                'Amount' => array(
                    'Value' => $data['amount'],
                    'CurrencyCode' => $data['currency']
                ),

                'OrderId' => $data['orderid'], // optional
                'Description' => $data['description']
            ),

            'ReturnUrls' => array(
                'Success' => $data['successlink'],
                'Fail' => $data['faillink'],
                'Abort' => $data['backlink'] // optional
            )
        );

        if ($data['isRecurringPayment']) {
            $jsonData['Payment']['Recurring']['Initial'] = true;
        }

        if (isset($data['cardrefid'])) {
            if ('new' === $data['cardrefid']) {
                $jsonData['RegisterAlias'] = array('IdGenerator' => 'RANDOM');
            } else {
                $jsonData['RegisterAlias'] = array('IdGenerator' => 'MANUAL', 'Id' => $data['cardrefid']);
            }
        }

        return json_encode($jsonData);
    }

    /**
     * @param string $token
     * @return string
     */
    protected function buildPaymentPageAssertObj($token)
    {
        $jsonData = json_encode(array(
            'RequestHeader' => $this->buildRequestHeader(),
            'Token' => $token
        ));

        return $jsonData;
    }

    /**
     * @param string $transactionId
     * @return string
     */
    protected function buildTransactionCaptureObj($transactionId)
    {
        $jsonData = json_encode(array(
            'RequestHeader' => $this->buildRequestHeader(),
            "TransactionReference" => array(
                // user either TransactionId or OrderId to reference the transaction
                'TransactionId' => $transactionId
            )
        ));

        return $jsonData;
    }

    /**
     * @return array
     */
    protected function buildRequestHeader()
    {
        return array(
            'SpecVersion' => self::SPEC_VERSION,
            'CustomerId' => $this->authenticationStrategy->getCustomerId(),
            'RequestId' => Uuid::uuid(),
            'RetryIndicator' => self::RETRY_INDICATOR
        );
    }
}
