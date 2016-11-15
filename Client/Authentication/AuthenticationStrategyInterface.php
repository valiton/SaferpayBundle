<?php

namespace Valiton\Payment\SaferpayBundle\Client\Authentication;

use Guzzle\Http\Message\RequestInterface;

/**
 * AuthenticationStrategyInterface
 *
 * @package Valiton\Payment\SaferpayBundle\Client\Authentication
 * @author Sven Cludius<sven.cludius@valiton.com>
 */
interface AuthenticationStrategyInterface
{
    /**
     * Add authentication fields
     *
     * @param RequestInterface $request
     * @param array $data
     * @param bool $withPassword
     * @return void
     */
    public function authenticate(RequestInterface $request = null, array &$data = null, $withPassword = false);
}
