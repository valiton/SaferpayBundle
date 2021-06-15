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
     * @param array $options
     */
    public function authenticate(array &$options);
}
