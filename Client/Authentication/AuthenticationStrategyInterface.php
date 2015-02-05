<?php

namespace Valiton\Payment\SaferpayBundle\Client\Authentication;

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
     * @param array $data
     * @param bool $withPassword
     * @return void
     */
    public function authenticate(array &$data, $withPassword = false);
}