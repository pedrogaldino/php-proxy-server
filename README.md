# Php Proxy Server

Proxy server with HTTP(S) support implemented in PHP.

## Instalation

Package Installation:

    composer require pedrogaldino/php-proxy-server
    
## Quick Start

First, start the service as follows:

``````
<?php
    
namespace App\Services\Proxy;
    
use Galdino\Proxy\Server\Request;
use Galdino\Proxy\Server\Response;
use Galdino\Proxy\Server\Contracts\RequestInterceptorContract;
use Galdino\Proxy\Server\ProxyMiddleware;
use Galdino\Proxy\Server\ProxyServer;

class MyProxyServer extends ProxyMiddleware implements RequestInterceptorContract
{
    protected $proxy;
    
    public function __construct()
    {
        $this->proxy = new ProxyServer($this);
    }
...
``````

Then implement the middleware events:

``````
...
public function onReceiveRequest(Request $request): Promise
{
    return new Promise(function ($resolve, $reject) use($request) {
        print 'Before client request' . PHP_EOL;

        //...

        $resolve($request);
    });
}

public function afterRetryProxyRequest(Request $request, Response $response) : Promise
{
    return new \React\Promise\Promise(function($resolve, $reject) use($request, $response) {
        print 'After proxy request' . PHP_EOL;

        //...

        $resolve([$request, $response]);
    });
}

public function beforeClientResponse(Request $request, Response $response): Promise
{
    return new \React\Promise\Promise(function($resolve, $reject) use($request, $response) {
        print 'Before client response' . PHP_EOL;

        //...

        $resolve([$request, $response]);
    });
}

public function onError($exception, Request $request = null, Response $response = null): Promise
{
    return new \React\Promise\Promise(function($resolve, $reject) use($exception, $request, $response) {

        print 'On error' . PHP_EOL;
    
        $resolve([$exception, $request, $response]);
    });
}
...
``````

