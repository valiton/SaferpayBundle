<?php

namespace Valiton\Payment\SaferpayBundle\Client\Authentication;

use Payment\Saferpay\Data\DataInterface;
use Valiton\Payment\SaferpayBundle\Client\Authentication\AuthenticationStrategyInterface;
use Guzzle\Http\Message\RequestInterface;

/**
 * TokenAuthenticationStrategy
 *
 * @package Valiton\Payment\SaferpayBundle\Client\Authentication
 * @author Sven Cludius<sven.cludius@valiton.com>
 */
class TokenAuthenticationStrategy implements AuthenticationStrategyInterface
{
    /**
     * @var string
     */
    protected $account;

    /**
     * @var string
     */
    protected $password;

    /**
     * Constructor
     *
     * @param string $account
     * @param string $password
     */
    public function __construct($account, $password)
    {
        $this->account = $account;
        $this->password = $password;
    }

    /**
     * Add authentication fields
     *
     * @param RequestInterface $response
     * @param array $data
     * @param bool $withPassword
     * @return void
     */
    public function authenticate(RequestInterface $response = null, array &$data = null, $withPassword = false)
    {
        if ($data) {
            $data['ACCOUNTID'] = $this->account;
            if ($withPassword) {
                $data['spPassword'] = $this->password;
            }
        }
    }

    /**
     * set account
     *
     * @param string $account
     */
    public function setAccount($account)
    {
        $this->account = $account;
    }

    /**
     * set password
     *
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

}