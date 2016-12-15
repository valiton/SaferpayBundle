<?php

namespace Valiton\Payment\SaferpayBundle\Client\Authentication;


use Guzzle\Http\Message\RequestInterface;

class JsonAuthenticationStrategy implements AuthenticationStrategyInterface
{
    /**
     * @var string
     */
    protected $account;

    /**
     * @var string
     */
    protected $customerId;

    /**
     * @var string
     */
    protected $terminalId;

    /**
     * @var string
     */
    protected $apiKey;

    /**
     * @var string
     */
    protected $apiPwd;

    /**
     * JsonAuthenticationStrategy constructor.
     * @param string $account
     * @param string $apiKey
     * @param string $apiPwd
     */
    public function __construct($account, $apiKey, $apiPwd)
    {
        $this->setAccount($account);
        $this->apiKey = $apiKey;
        $this->apiPwd = $apiPwd;
    }

    /**
     * @param RequestInterface $request
     * @param array $data
     * @param bool $withPassword
     */
    public function authenticate(RequestInterface $request = null, array &$data = null, $withPassword = false)
    {
        if ($request) {
            $request->setAuth($this->apiKey, $this->apiPwd, 'Basic');
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

        // format: 'customerId-terminalId'
        $tmp = explode('-', $this->account);
        if (count($tmp) == 2)
        {
            $this->customerId = $tmp[0];
            $this->terminalId = $tmp[1];
        }
        else
        {
            $this->customerId = "";
            $this->terminalId = "";
        }
    }

    /**
     * @param string $apiKey
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * @param string $apiPwd
     */
    public function setApiPwd($apiPwd)
    {
        $this->apiPwd = $apiPwd;
    }

    /**
     * Get customerId from accountId
     *
     * @return string
     */
    public function getCustomerId()
    {
        return $this->customerId;
    }

    /**
     * Get terminalId from accountId
     *
     * @return string
     */
    public function getTerminalId()
    {
        return $this->terminalId;
    }
}