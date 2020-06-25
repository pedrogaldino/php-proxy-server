<?php


namespace Galdino\Proxy\Extra\HttpProtocol;


class MessageFactory extends \Clue\React\Buzz\Message\MessageFactory
{

    public function request($method, $uri, $headers = array(), $content = '', $proxy = null)
    {
        return new Request($method, $uri, $headers, $this->body($content), '1.1', $proxy);
    }

}