<?php

namespace Galdino\Proxy\Extra\HttpProtocol;

class RequestData extends \React\HttpClient\RequestData
{
    private $url;

    protected $proxy;

    public function __construct($method, $url, array $headers = array(), $protocolVersion = '1.0', $proxy = null)
    {
        $this->url = $url;
        $this->proxy = $proxy;

        if($this->proxy && strpos($this->url, 'http://') === 0) {
            $proxy = parse_url($this->proxy);

            if(!empty($proxy['user']) && !empty($proxy['pass'])) {
                $headers = array_merge([
                    'Proxy-Authorization' => 'Basic ' . base64_encode($proxy['user'] . ':' . $proxy['pass'])
                ]);
            }
        }

        parent::__construct($method, $url, $headers, $protocolVersion);
    }

    public function getPath()
    {
        if($this->proxy && strpos($this->url, 'http://') === 0) {
            return $this->url;
        }
        return parent::getPath();
    }
}