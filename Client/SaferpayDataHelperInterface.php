<?php

namespace Valiton\Payment\SaferpayBundle\Client;

use Guzzle\Http\Message\Response;

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
     * @param Response $response
     * @return array
     */
    public function getDataFromResponse(Response $response);

    /**
     * @param Response $response
     * @return string
     */
    public function tryGetErrorInfoFromResponse(Response $response);

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