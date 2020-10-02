<?php

namespace Galdino\Proxy\Server\Contracts;

use Galdino\Proxy\Server\Request;
use Galdino\Proxy\Server\Response;
use React\Promise\Promise;

interface RequestInterceptorContract
{
    /**
     * Called when the server receive a new request
     * @param Request $request The request object. You can manipulate it
     * @return Promise
     */
    public function onReceiveRequest(Request $request) : Promise;

    /**
     * Called before the server's request
     * @param Request $request The request object. You can manipulate it to change any param.
     * @return Promise
     */
    public function beforeProxyRequest(Request $request) : Promise;

    /**
     * Called before retry the server's proxy request
     * @param Request $request
     * @param Response $response
     * @return Promise
     */
    public function beforeRetryProxyRequest(Request $request, Response $response) : Promise;

    /**
     * Called after server request and before the server respond to the client.
     * @param Request $request
     * @param Response $response The response object. You can manipulate it or return a new response to the client.
     * @return Promise
     */
    public function beforeClientResponse(Request $request, Response $response) : Promise;

    /**
     * Called if any error ocurrs
     * @param mixed $exception
     * @param Request $request
     * @param Response $response
     * @return Promise
     */
    public function onError($exception, Request $request = null, Response $response = null) : Promise;
}
