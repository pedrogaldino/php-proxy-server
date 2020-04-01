<?php


namespace Galdino\Proxy\Extra;


use Clue\React\Buzz\Message\MessageFactory;
use Psr\Http\Message\UriInterface;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectorInterface;

class Browser extends \Clue\React\Buzz\Browser
{
    private $messageFactory;

    public function __construct(LoopInterface $loop, ConnectorInterface $connector = null)
    {
        $this->messageFactory = new MessageFactory();

        parent::__construct($loop, $connector);
    }

    public function request($method, UriInterface $uri, array $headers, $body = null)
    {
        return $this->send($this->messageFactory->request($method, $uri, $headers, $body));
    }

}