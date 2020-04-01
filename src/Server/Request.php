<?php


namespace Galdino\Proxy\Server;

use Clue\React\HttpProxy\ProxyConnector;
use GuzzleHttp\TransferStats;
use Galdino\Proxy\Server\Contracts\ManipulateCookiesContract;
use Galdino\Proxy\Server\Contracts\ManipulateHeadersContract;
use Galdino\Proxy\Server\Traits\ManipulateCookies;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use React\EventLoop\LoopInterface;
use React\Promise\Promise;
use React\Socket\Connector;

class Request implements ManipulateHeadersContract, ManipulateCookiesContract
{
    use ManipulateCookies;

    protected $method;

    protected $uri;

    protected $query;

    protected $headers = [];

    protected $proxy;

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

    public function getResponse(LoopInterface $loop) : Promise
    {
        return new Promise(function ($resolve, $reject) use($loop) {
            $response = new Response();

            $browser = new \Galdino\Proxy\Extra\Browser($loop, $this->getProxy());

            $browser->withOptions([
                'timeout' => null,
                'followRedirects' => false,
                'obeySuccessCode' => true,
                'streaming' => false
            ]);

            print 'Making the request' . PHP_EOL;

            $browser
                ->request($this->getMethod(), $this->getUri(), $this->getHeaders(), $this->getBody())
                ->then(function (ResponseInterface $browserResponse) use ($response, $resolve) {
                    print 'Request finished' . PHP_EOL;

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
                }, function (\Exception $exception) use($response, $resolve) {
                    print 'Request error ' . $exception->getMessage() . PHP_EOL;

                    dump($exception);

                    $body = [
                        'error' => true,
                        'message' => $exception->getMessage(),
                        'type' => get_class($exception)
                    ];

                    if($this->showStatckTraceOnExceptions) {
                        $body = array_merge($body, [
                            'stack_trace' => $exception->getTraceAsString()
                        ]);
                    }

                    $response
                        ->setStatusCode($exception->getCode() ?: 506)
                        ->setHeader('Content-Type', 'application/json')
                        ->setBody(json_encode($body));

                    $resolve($response);
                });
        });
    }

}
