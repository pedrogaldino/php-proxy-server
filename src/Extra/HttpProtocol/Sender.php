<?php

namespace Galdino\Proxy\Extra\HttpProtocol;

use Psr\Http\Message\ResponseInterface;
use React\Http\Io\ChunkedDecoder;
use React\Http\Io\ChunkedEncoder;
use Psr\Http\Message\RequestInterface;
use React\EventLoop\LoopInterface;
use React\Http\Client\Client as HttpClient;
use React\Http\Io\ReadableBodyStream;
use React\Promise\Deferred;
use React\Socket\ConnectorInterface;
use React\Stream\ReadableStreamInterface;

class Sender extends \React\Http\Io\Sender
{
    private $http;

    public function __construct(Client $http)
    {
        $this->http = $http;

        parent::__construct($http);
    }

    public static function createFromLoop(LoopInterface $loop, ConnectorInterface $connector = null)
    {
        return new self(new Client($loop, $connector));
    }

    public function send(RequestInterface $request)
    {
        $body = $request->getBody();
        $size = $body->getSize();

        if ($size !== null && $size !== 0) {
            // automatically assign a "Content-Length" request header if the body size is known and non-empty
            $request = $request->withHeader('Content-Length', (string)$size);
        } elseif ($size === 0 && \in_array($request->getMethod(), array('POST', 'PUT', 'PATCH'))) {
            // only assign a "Content-Length: 0" request header if the body is expected for certain methods
            $request = $request->withHeader('Content-Length', '0');
        } elseif ($body instanceof ReadableStreamInterface && $body->isReadable() && !$request->hasHeader('Content-Length')) {
            // use "Transfer-Encoding: chunked" when this is a streaming body and body size is unknown
            $request = $request->withHeader('Transfer-Encoding', 'chunked');
        } else {
            // do not use chunked encoding if size is known or if this is an empty request body
            $size = 0;
        }

        $headers = array();
        foreach ($request->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }

        $requestStream = $this->http->request($request->getMethod(), (string)$request->getUri(), $headers, $request->getProtocolVersion(), $request->getProxy());

        $deferred = new Deferred(function ($_, $reject) use ($requestStream) {
            // close request stream if request is cancelled
            $reject(new \RuntimeException('Request cancelled'));
            $requestStream->close();
        });

        $requestStream->on('error', function($error) use ($deferred) {
            $deferred->reject($error);
        });

        $requestStream->on('response', function (ResponseInterface $response, ReadableStreamInterface $body) use ($deferred, $request) {
            $length = null;
            $code = $response->getStatusCode();
            if ($request->getMethod() === 'HEAD' || ($code >= 100 && $code < 200) || $code == 204 || $code == 304) {
                $length = 0;
            } elseif (\strtolower($response->getHeaderLine('Transfer-Encoding')) === 'chunked') {
                $body = new ChunkedDecoder($body);
            } elseif ($response->hasHeader('Content-Length')) {
                $length = (int) $response->getHeaderLine('Content-Length');
            }

            $deferred->resolve($response->withBody(new ReadableBodyStream($body, $length)));
        });

        if ($body instanceof ReadableStreamInterface) {
            if ($body->isReadable()) {
                // length unknown => apply chunked transfer-encoding
                if ($size === null) {
                    $body = new ChunkedEncoder($body);
                }

                // pipe body into request stream
                // add dummy write to immediately start request even if body does not emit any data yet
                $body->pipe($requestStream);
                $requestStream->write('');

                $body->on('close', $close = function () use ($deferred, $requestStream) {
                    $deferred->reject(new \RuntimeException('Request failed because request body closed unexpectedly'));
                    $requestStream->close();
                });
                $body->on('error', function ($e) use ($deferred, $requestStream, $close, $body) {
                    $body->removeListener('close', $close);
                    $deferred->reject(new \RuntimeException('Request failed because request body reported an error', 0, $e));
                    $requestStream->close();
                });
                $body->on('end', function () use ($close, $body) {
                    $body->removeListener('close', $close);
                });
            } else {
                // stream is not readable => end request without body
                $requestStream->end();
            }
        } else {
            // body is fully buffered => write as one chunk
            $requestStream->end((string)$body);
        }

        return $deferred->promise();
    }
}