<?php

namespace Galdino\Proxy\Server;

use React\Http\Message\ResponseException;
use Galdino\Proxy\Server\Contracts\ManipulateCookiesContract;
use Galdino\Proxy\Server\Contracts\ManipulateHeadersContract;
use Galdino\Proxy\Server\Contracts\RequestInterceptorContract;
use Galdino\Proxy\Server\Traits\ManipulateCookies;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use React\EventLoop\LoopInterface;
use React\Promise\Promise;
use React\Promise\Deferred;

class Request implements ManipulateHeadersContract, ManipulateCookiesContract
{
    use ManipulateCookies;

    protected $requestDateStartTime;

    protected $requestTime;

    protected $method;

    protected $uri;

    protected $query;

    protected $headers = [];

    protected $proxyList = [];

    protected $debug = false;

    protected $showStatckTraceOnExceptions = false;

    protected $form = [];

    protected $files = [];

    protected $body;

    protected $onProgress;

    public function __construct(\Closure $onProgress = null)
    {
        $this->onProgress = $onProgress;
    }

    public function isFormRequest()
    {
        $headers = array_change_key_case($this->headers,CASE_LOWER);

        foreach ($headers['content-type'] ?? [] as $type) {
            if(strpos($type, 'application/x-www-form-urlencoded') > -1) {
                return true;
            }
        }

        return false;
    }

    public function isMultipartForm()
    {
        $headers = array_change_key_case($this->headers,CASE_LOWER);

        foreach ($headers['content-type'] ?? [] as $type) {
            if(strpos($type, 'multipart/form-data') > -1) {
                return true;
            }
        }

        return false;
    }

    public function disableStackTraceOnErrorResponse()
    {
        $this->showStatckTraceOnExceptions = false;
        return $this;
    }

    public function enableStackTraceOnErrorResponse()
    {
        $this->showStatckTraceOnExceptions = true;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @param mixed $method
     * @return Request
     */
    public function setMethod($method)
    {
        $this->method = $method;
        return $this;
    }

    /**
     * @return UriInterface
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * @param UriInterface $uri
     * @return Request
     */
    public function setUri(UriInterface $uri)
    {
        $this->uri = $uri;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @param mixed $query
     * @return Request
     */
    public function setQuery($query)
    {
        $this->query = $query;
        return $this;
    }

    /**
     * @return array
     */
    public function getProxyList(): array
    {
        return $this->proxyList;
    }

    /**
     * @param array $proxyList
     * @return Request
     */
    public function setProxyList(array $proxyList): Request
    {
        $this->proxyList = $proxyList;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @param mixed $body
     * @return Request
     */
    public function setBody($body)
    {
        $this->body = $body;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getProxy()
    {
        return $this->proxy;
    }

    /**
     * @param mixed $proxy
     * @return Request
     */
    public function setProxy($proxy)
    {
        $this->proxy = $proxy;
        return $this;
    }

    /**
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * @param bool $debug
     * @return Request
     */
    public function setDebug(bool $debug): Request
    {
        $this->debug = $debug;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRequestDateStartTime()
    {
        return $this->requestDateStartTime;
    }

    /**
     * @param mixed $requestDateStartTime
     * @return Request
     */
    public function setRequestDateStartTime($requestDateStartTime)
    {
        $this->requestDateStartTime = $requestDateStartTime;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRequestTime()
    {
        return $this->requestTime;
    }

    /**
     * @param mixed $requestTime
     * @return Request
     */
    public function setRequestTime($requestTime)
    {
        $this->requestTime = $requestTime;
        return $this;
    }

    public function addFile($fieldName, $file, $filename, $fileType = null, $headers = [])
    {
        $file = [
            'name' => $fieldName,
            'contents' => $file,
            'filename' => $filename,
            'headers'  => [
                'Content-Type' => $fileType
            ]
        ];

        if(!empty($fileType)) {
            $file['headers'] = [
                'Content-Type' => $fileType
            ];
        }

        if(!empty($headers)) {
            $file['headers'] = array_merge($file['headers'] ?? [], $headers);
        }

        $this->files[] = $file;

        return $this;
    }

    public function getFiles()
    {
        return $this->files;
    }

    public function addFormField($name, $value)
    {
        $this->form[$name] = $value;

        return $this;
    }

    public function getFormFields()
    {
        return $this->form;
    }

    public function makeRequest($loop, RequestInterceptorContract $interceptor, $callback, &$currentProxyIndex = 0)
    {
        $promise = new Promise(function ($resolve, $reject) use ($loop, $currentProxyIndex) {

            $currentProxy = null;
            $proxyUrl = null;

            if (count($this->proxyList)) {
                $currentProxy = $this->proxyList[$currentProxyIndex];

//                dump('currentProxy: ', $currentProxy);

                $this->unsetCookie('SelectedProxyId');
                $this->addCookie('SelectedProxyId', $currentProxy['id']);
                $proxyUrl = $currentProxy['proxy_url'];
            }

            $browser = new \Galdino\Proxy\Extra\Browser($loop, $proxyUrl);

            $browser = $browser
                ->withTimeout(3600.0)
                ->withFollowRedirects(false)
                ->withRejectErrorResponse(true)
                ->withResponseBuffer(256 * 1024 * 1024);

            $response = new Response();

            print 'Making the request' . PHP_EOL;

            $browser
                ->request($this->getMethod(), $this->getUri(), $this->getHeaders(), $this->getBody())
                ->then(function (ResponseInterface $browserResponse) use ($response, $resolve, $reject) {

                    print 'Request finished' . PHP_EOL;

                    $this->setRequestEndTime();

                    foreach ($this->getHeaders() as $name => $value) {
                        if (strpos($name, '_Proxy') === 0) {
                            $response->addHeader($name, $value);
                        }
                    }

                    $response
                        ->setUri($this->getUri())
                        ->setStatusCode($browserResponse->getStatusCode())
                        ->mergeHeaders($browserResponse->getHeaders())
                        ->setBody($browserResponse->getBody()->getContents());

                    $resolve($response);
                }, function (\Exception $exception) use($response, $resolve, $reject, $currentProxy) {

                    print 'Request error: ' . $exception->getMessage() . PHP_EOL;
                    print $this->getMethod() . ' -> ' . $this->getUri() . PHP_EOL;

                    if (!empty($currentProxy)) {
                        print date('Y-m-d H:i:s') . ' | Proxy used: #' . $currentProxy['id'] . ' - ' . $currentProxy['proxy_url'] . PHP_EOL;
                    }

                    dump($exception);

                    $this->setRequestEndTime();

                    $body = [
                        'error' => true,
                        'message' => $exception->getMessage(),
                        'type' => get_class($exception)
                    ];

                    if ($exception instanceof ResponseException) {
                        $content = $exception->getResponse()->getBody()->getContents();
                        if (!empty($content)) {
                            $contentJson = json_decode($content, true);
                            if (!empty($contentJson)) {
                                $body['json'] = $contentJson;
                            } else {
                                $body['json'] = $content;
                            }
                        }
                    }

                    if($this->showStatckTraceOnExceptions) {
                        $body = array_merge($body, [
                            'stack_trace' => $exception->getTraceAsString()
                        ]);
                    }

                    $response
                        ->setStatusCode($exception->getCode() ?: 506)
                        ->setHeader('Content-Type', 'application/json')
                        ->setBody(json_encode($body));

                    $reject([$exception, $response]);
                });
        });

        $promise
            ->then(function (Response $response) use ($callback, $interceptor) {
                $callback($response);
            })
            ->otherwise(function($result) use ($loop, $interceptor, &$currentProxyIndex, $callback) {

                $exception = $result[0];
                $response = $result[1];

                $isToIgnoreRetryProxyRequest = false;

                if (!empty($exception->getMessage()))
                {
                    if (str_contains($exception->getMessage(), 'Connection ended before receiving response'))
                    {
                        $isToIgnoreRetryProxyRequest = true;
                    }
                }

                $currentProxyIndex++;

                if (($currentProxyIndex + 1) <= count($this->getProxyList()) && !$isToIgnoreRetryProxyRequest)
                {
                    $this->setRequestDateStartTime(date('Y-m-d H:i:s'));

                    $interceptor
                        ->beforeRetryProxyRequest($this, $response)
                        ->then(function () use ($loop, $interceptor, $callback, $currentProxyIndex) {
                            $this->makeRequest($loop, $interceptor, $callback, $currentProxyIndex);
                        });
                }
                else
                {
                    $callback($result);
                }
            });
    }

    public function getResponse(LoopInterface $loop, RequestInterceptorContract $interceptor) : Promise
    {
        return new Promise(function ($resolve, $reject) use($loop, $interceptor) {
            $this->makeRequest($loop, $interceptor, function ($result) use ($resolve, $reject) {
                if ($result instanceof Response) {
                    $resolve($result);
                } else {
                    $reject($result);
                }
            });
        });
    }

    public function setRequestEndTime()
    {
        if ($this->getRequestDateStartTime()) {
            $start = strtotime($this->getRequestDateStartTime());
            $end = strtotime(date('Y-m-d H:i:s'));

            $diff = (abs($start - $end) / 86400);

            $diff = (int) (($diff * 100000) * 1000);

            $this->setRequestTime($diff);
        }
    }

}
