<?php

namespace Galdino\Proxy\Extra\HttpProtocol;

use React\EventLoop\LoopInterface;
use React\Http\Client\Request;
use React\Socket\Connector;
use React\Socket\ConnectorInterface;

class Client extends \React\Http\Client\Client
{
    private $connector;

    public function __construct(LoopInterface $loop, ConnectorInterface $connector = null)
    {
        if ($connector === null) {
            $connector = new Connector($loop);
        }

        $this->connector = $connector;

        parent::__construct($loop, $connector);
    }

    public function request($method, $url, array $headers = array(), $protocolVersion = '1.0', $proxy = null)
    {
        $requestData = new \Galdino\Proxy\Extra\HttpProtocol\RequestData($method, $url, $headers, $protocolVersion, $proxy);

        dump((string) $requestData);

        return new Request($this->connector, $requestData);
    }
}
