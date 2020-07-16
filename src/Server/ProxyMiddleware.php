<?php


namespace Galdino\Proxy\Server;

use Galdino\Proxy\Server\Contracts\RequestInterceptorContract;
use React\Promise\Promise;

class ProxyMiddleware implements RequestInterceptorContract
{

    public function onError($exception, Request $request = null, Response $response = null) : Promise
    {
        return new \React\Promise\Promise(function($resolve, $reject) use($exception) {
            print 'Received an error!' . PHP_EOL;
            $reject($exception);
        });
    }

    public function onReceiveRequest(Request $request) : Promise
    {
        return new \React\Promise\Promise(function($resolve, $reject) use($request) {
            print 'Received new request' . PHP_EOL;
            $resolve($request);
        });
    }

    public function beforeProxyRequest(Request $request) : Promise
    {
        return new \React\Promise\Promise(function($resolve, $reject) use($request) {
            print 'Before proxy request' . PHP_EOL;
            $resolve($request);
        });
    }

    public function beforeClientResponse(Request $request, Response $response) : Promise
    {
        return new \React\Promise\Promise(function($resolve, $reject) use($request, $response) {
            print 'Before client response' . PHP_EOL;
            $resolve([$request, $response]);
        });
    }


}
