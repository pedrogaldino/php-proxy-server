<?php

namespace Galdino\Proxy\Server;

use Evenement\EventEmitter;
use Evenement\EventEmitterTrait;
use Galdino\Proxy\Server\Contracts\RequestInterceptorContract;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\EventLoop\TimerInterface;
use React\Http\Middleware\LimitConcurrentRequestsMiddleware;
use React\Http\Middleware\StreamingRequestMiddleware;
use React\Http\Message\Response;
use React\Promise\Promise;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;

class ProxyServer
{
    use EventEmitterTrait;

    protected $loop;

    protected $interceptor;

    protected $debugRequests = false;

    protected $proxy = null;

    protected $concurrentRequestsLimit = 100;

    public function __construct(RequestInterceptorContract $interceptor = null)
    {
        if($interceptor) {
            $this->interceptor = $interceptor;
        } else {
            $this->interceptor = new ProxyMiddleware();
        }

        $this->loop = Factory::create();
    }

    public function getLoop()
    {
        return $this->loop;
    }

    public function enableDebug()
    {
        $this->debugRequests = true;
        return $this;
    }

    public function disableDebug()
    {
        $this->debugRequests = false;
        return $this;
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

    private function isMultipartForm($headers)
    {
        $headers = array_change_key_case($headers,CASE_LOWER);

        foreach ($headers['content-type'] ?? [] as $type) {
            if(strpos($type, 'multipart/form-data') > -1) {
                return true;
            }
        }

        return false;
    }

    private function isFormRequest($headers)
    {
        $headers = array_change_key_case($headers,CASE_LOWER);

        foreach ($headers['content-type'] ?? [] as $type) {
            if(strpos($type, 'application/x-www-form-urlencoded') > -1) {
                return true;
            }
        }

        return false;
    }

    private function prepareRequest(ServerRequestInterface $serverRequest, $rawBody = null, $requestDateStartTime = null) : Request
    {
        $request = new Request();

        $request
            ->setMethod($serverRequest->getMethod())
            ->setUri($serverRequest->getUri())
            ->setHeaders($serverRequest->getHeaders())
            ->setQuery($serverRequest->getQueryParams())
            ->setProxy($this->proxy)
            ->setDebug($this->debugRequests)
            ->setBody($rawBody)
            ->setRequestDateStartTime($requestDateStartTime);

//        if($request->isMultipartForm()) {
//            foreach ($serverRequest->getUploadedFiles() ?? [] as $key => $file) {
//                $request->addFile($key, $file->getStream()->getContents(), $file->getClientFilename(), $file->getClientMediaType());
//            }
//        }
//
//        if($request->isFormRequest() || $request->isMultipartForm()) {
//            foreach ($serverRequest->getParsedBody() ?? [] as $key => $value) {
//                $request->addFormField($key, $value);
//            }
//        } else {
//            $request->setBody($serverRequest->getBody()->getContents());
//        }

        return $request;
    }

    private function prepareNativeResponse(\Galdino\Proxy\Server\Response $response)
    {
        $statusCode = $response->getStatusCode();
        $headers = $response->getHeaders();
        $body = $response->getBody();

        return new Response(
            $statusCode,
            $headers,
            $body
        );
    }

    private function prepareNativeResponseFromException(\Exception $exception)
    {
        $statusCode = $exception->getCode();
        $headers = [
            'Content-Type' => 'application/json'
        ];
        $body = [
            'error' => true,
            'message' => $exception->getMessage(),
            'type' => get_class($exception)
        ];

        return new Response(
            $statusCode,
            $headers,
            json_encode($body)
        );
    }

    private function proxyRequest(Request $request) : Promise
    {
        return new Promise(function($resolve, $reject) use($request) {
            try {
                $this
                    ->interceptor
                    ->onReceiveRequest($request)
                    ->then(function(Request &$newRequest) use($resolve, $reject) {
                        $this
                            ->interceptor
                            ->beforeProxyRequest($newRequest)
                            ->then(function ($result) use($resolve, $reject, &$newRequest) {
                                print 'Line 169' . PHP_EOL;

                                $callBeforeClientResponse = function($request, $response, $callback) use($resolve, $reject) {
                                    print 'Line 172' . PHP_EOL;

                                    $this
                                        ->interceptor
                                        ->beforeClientResponse($request, $response)
                                        ->then(function($result) use($callback) {
                                            print 'Line 177' . PHP_EOL;

                                            $callback(
                                                $result[0], $result[1]
                                            );
                                        })
                                        ->otherwise($reject);
                                };

                                if($result instanceof \Galdino\Proxy\Server\Response) {
                                    $callBeforeClientResponse($newRequest, $result, function ($request, $response) use($resolve, $reject) {
                                        $resolve(
                                            $this->prepareNativeResponse($response)
                                        );
                                    });

                                    return;
                                }

                                $newRequest
                                    ->getResponse($this->getLoop(), $this->interceptor)
                                    ->then(function (\Galdino\Proxy\Server\Response $response) use($newRequest, $callBeforeClientResponse, $resolve, $reject) {
                                        $callBeforeClientResponse($newRequest, $response, function ($request, $response) use($resolve, $reject) {
                                            $resolve(
                                                $this->prepareNativeResponse($response)
                                            );
                                        });
                                    })
                                    ->otherwise(function ($result) use($newRequest, $callBeforeClientResponse, $resolve, $reject) {

                                        $callBeforeClientResponse($newRequest, $result[1], function ($request, $response) use($resolve, $reject, $result) {

                                            $this
                                                ->interceptor
                                                ->onError($result[0], $request, $response)
                                                ->then(function () use ($request, $response, $resolve) {
                                                    $resolve(
                                                        $this->prepareNativeResponse($response)
                                                    );
                                                })
                                                ->otherwise($reject);
                                        });
                                    });
                            })->otherwise($reject);
                    })->otherwise($reject);
            } catch (\Exception $exception) {
                $reject($exception);
            }
        });
    }

    public function getResponse(ServerRequestInterface $request)
    {
        return new Promise(function ($resolve, $reject) use ($request) {

            $body = $request->getBody();

            $rawBody = null;
            $requestDateStartTime = date('Y-m-d H:i:s');

            $body->on('data', function ($data) use (&$rawBody) {
                $rawBody .= $data;
            });

            $body->on('end', function () use ($resolve, $reject, $request, &$rawBody, $requestDateStartTime){
                $request = $this->prepareRequest($request, $rawBody, $requestDateStartTime);

                $this
                    ->proxyRequest($request)
                    ->then($resolve)
                    ->otherwise($reject);
            });

            $body->on('error', function (\Exception $exception) use ($resolve, $reject, &$contentLength) {
                $onError = $this
                    ->interceptor
                    ->onError($exception);

                if($onError instanceof Promise) {
                    $onError
                        ->then($resolve)
                        ->otherwise($reject);
                } else if($onError instanceof \Galdino\Proxy\Server\Response) {
                    $resolve(
                        $this->prepareNativeResponse($onError)
                    );
                } else {
                    $resolve(
                        $this->prepareNativeResponseFromException($exception)
                    );
                }
            });
        });
    }

    protected function startHttpsServer()
    {
        $connector = new Connector($this->getLoop(), [
            'tls' => array(
                'verify_peer' => false,
                'verify_peer_name' => false
            ),
            'happy_eyeballs' => false,
            'timeout' => 300.0
        ]);

        $server = new \React\Http\Server(
            $this->getLoop(),
            new StreamingRequestMiddleware(),
            new LimitConcurrentRequestsMiddleware($this->concurrentRequestsLimit),
            function (ServerRequestInterface $request) use ($connector) {
                if ($request->getMethod() !== 'CONNECT') {
                    return $this->getResponse($request);
                }

                return $connector->connect('127.0.0.1:8002')->then(
                    function (ConnectionInterface $remote) {
                        return new Response(
                            200,
                            array(),
                            $remote
                        );
                    },
                    function ($e) {
                        return new Response(
                            502,
                            array(
                                'Content-Type' => 'text/plain'
                            ),
                            'Unable to connect: ' . $e->getMessage()
                        );
                    }
                );
            }
        );

        $socket = new \React\Socket\Server('0.0.0.0:8001', $this->getLoop(), [
            'tls' => array(
                'verify_peer' => false,
                'verify_peer_name' => false
            )
        ]);

        $server->listen($socket);

        $socket->on('error', 'printf');
        $server->on('error', 'printf');

        $this->prepareSocketEvents($socket);

        return $socket;
    }

    protected function prepareSocketEvents(EventEmitter &$socket)
    {
        $socket->on('error', function(...$args) {
            $this->emit('error', $args);
        });

        $socket->on('connection', function(...$args) {
            $this->emit('connection', $args);
        });
    }

    protected function startTlsHelloServer()
    {
        $server = new \React\Http\Server(
            $this->getLoop(),
            new StreamingRequestMiddleware(),
            new LimitConcurrentRequestsMiddleware($this->concurrentRequestsLimit),
            function (ServerRequestInterface $request) {
                return $this->getResponse($request);
            }
        );

        $socket = new \React\Socket\Server('0.0.0.0:8002', $this->getLoop(), [
            'tls' => array(
                'verify_peer' => false,
                'verify_peer_name' => false
            )
        ]);
        $socket = new \React\Socket\SecureServer($socket, $this->getLoop(), [
            'local_cert' => realpath(
                __DIR__ . DIRECTORY_SEPARATOR .
                '..' . DIRECTORY_SEPARATOR . '..' .
                DIRECTORY_SEPARATOR .
                'localhost.pem'
            )
        ]);

        $server->listen($socket);

        $socket->on('error', 'printf');
        $server->on('error', 'printf');

        return $socket;
    }

    public function addTaskAsync(\Closure $closure)
    {
        $this->getLoop()->addTimer(0.1, $closure);
    }

    public function addPeriodicTaskAsync($interval, \Closure $closure)
    {
        $this->getLoop()->addPeriodicTimer($interval, $closure);
    }

    public function startMemoryInfoLogs()
    {
        $loop = $this->getLoop();

        $r = 2;
        $t = 0;

        $runs = 0;

        if (5 < $t) {
            $loop->addTimer($t, function () use ($loop) {
                $loop->stop();
            });

        }

        $loop->addPeriodicTimer(0.001, function () use (&$runs, $loop) {
            $runs++;

            $loop->addPeriodicTimer(1, function (TimerInterface $timer) use ($loop) {
                $loop->cancelTimer($timer);
            });
        });

        $loop->addPeriodicTimer($r, function () use (&$runs) {
            $kmem = round(memory_get_usage() / 1024);
            $kmemReal = round(memory_get_usage(true) / 1024);
            echo "Runs:\t\t\t$runs\n";
            echo "Memory (internal):\t$kmem KiB\n";
            echo "Memory (real):\t\t$kmemReal KiB\n";
            echo str_repeat('-', 50), "\n";
        });

        echo "PHP Version:\t\t", phpversion(), "\n";
        echo "Loop\t\t\t", get_class($loop), "\n";
        echo "Time\t\t\t", date('r'), "\n";

        echo str_repeat('-', 50), "\n";

        $beginTime = time();
        $endTime = time() + 1;
        $timeTaken = $endTime - $beginTime;

        echo "PHP Version:\t\t", phpversion(), "\n";
        echo "Loop\t\t\t", get_class($loop), "\n";
        echo "Time\t\t\t", date('r'), "\n";
        echo "Time taken\t\t", $timeTaken, " seconds\n";
        echo "Runs per second\t\t", round($runs / $timeTaken), "\n";
    }

    public function start()
    {
        ini_set('memory_limit', '-1');

        $this->startTlsHelloServer();

        $httpsConnector = $this->startHttpsServer();
//        $this->startMemoryInfoLogs();

        echo 'Listening on ' . str_replace('tcp:', 'http:', $httpsConnector->getAddress()) . PHP_EOL;

        $this->getLoop()->run();
    }
}
