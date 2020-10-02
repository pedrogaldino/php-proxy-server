<?php

namespace Galdino\Proxy\Extra\HttpProtocol;

class Request extends \RingCentral\Psr7\Request
{
    protected $proxy;

    public function __construct($method, $uri, array $headers = array(), $body = null, $protocolVersion = '1.1', $proxy = null)
    {
        $this->proxy = $proxy;

        parent::__construct($method, $uri, $headers, $body, $protocolVersion);
    }

    public function setProxy($proxy)
    {
        $this->proxy = $proxy;
        return $this;
    }

    public function getProxy()
    {
        return $this->proxy;
    }
}