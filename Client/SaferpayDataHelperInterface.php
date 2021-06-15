<?php

namespace Valiton\Payment\SaferpayBundle\Client;

use Psr\Http\Message\ResponseInterface;

interface SaferpayDataHelperInterface
{
    /**
     * @param array $data
     * @return string
     */
    public function buildPayInitObj(array $data);

    /**
     * @param string $token
     * @return string
     */
    public function buildPayConfirmObj($token);

    /**
     * @param string $transactionId
     * @return string
     */
    public function buildPayCompleteObj($transactionId);

    /**
     * @param ResponseInterface $response
     * @return array
     */
    public function getDataFromResponse(ResponseInterface $response);

    /**
     * @param ResponseInterface $response
     * @return string
     */
    public function tryGetErrorInfoFromResponse(ResponseInterface $response);

    /**
     * @return string
     */
    public function getPayInitUrl();

    /**
     * @return string
     */
    public function getPayConfirmUrl();

    /**
     * @return string
     */
    public function getPayCompleteUrl();

    /**
     * @return array
     */
    public function getNecessaryRequestHeaders();
}