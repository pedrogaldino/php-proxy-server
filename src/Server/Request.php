<?php


namespace Galdino\Proxy\Server;

use GuzzleHttp\Client;
use GuzzleHttp\TransferStats;
use Galdino\Proxy\Server\Contracts\ManipulateCookiesContract;
use Galdino\Proxy\Server\Contracts\ManipulateHeadersContract;
use Galdino\Proxy\Server\Traits\ManipulateCookies;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

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

//    public function parseBody()
//    {
//        $body = null;
//
//        if($this->isMultipartForm()) {
//            $body['multipart'] = [];
//
//            $this
//                ->unsetHeader('Content-Type')
//                ->unsetHeader('content-type');
//
//            if(!empty($this->getFiles())) {
//                $body['multipart'] = $this->getFiles();
//            }
//
//            foreach($this->getFormFields() as $key => $value) {
//                $body['multipart'][] = [
//                    'name' => $key,
//                    'contents' => $value
//                ];
//            }
//        } else if ($this->isFormRequest()) {
//            $body['form_params'] = $this->getFormFields();
//        } else {
//            $body = [
//                'body' => $this->getBody()
//            ];
//        }
//
//        return $body;
//    }

    public function getResponse(\Closure $onHeaders = null, \Closure $onStats = null) : Response
    {
        $client = new Client();
        $response = new Response();

        try {
            $clientResponse = $client->request($this->getMethod(), $this->getUri(), [
                'headers' => $this->getHeaders(),
                'allow_redirects' => false,
                'connect_timeout' => 0,
                'debug' => $this->isDebug(),
                'decode_content' => true,
                'http_errors' => false,
                'proxy' => $this->getProxy(),
                'query' => $this->getQuery(),
                'verify' => false,
                'body' => $this->getBody(),
                'on_headers' => function (ResponseInterface $response) use(&$onHeaders){
                    if(!empty($onHeaders)) {
                        $onHeaders($response);
                    }
                },
                'on_stats' => function (TransferStats $stats) use(&$response, &$onStats) {
                    $response
                        ->setUri($stats->getEffectiveUri())
                        ->setTransferTime($stats->getTransferTime());

                    if(!empty($onStats)) {
                        $onStats($stats);
                    }
                },
                'progress' => function(...$args) use(&$onProgress) {
                    if(!empty($this->onProgress)) {
                        $progress = $this->onProgress;
                        $progress($args);
                    }
                }
            ]);

            $response
                ->setStatusCode($clientResponse->getStatusCode())
                ->mergeHeaders($clientResponse->getHeaders())
                ->setBody($clientResponse->getBody()->getContents());
        } catch (\Exception $exception) {
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
        }

        foreach ($this->getHeaders() as $name => $value) {
            if (strpos($name, '_Proxy') === 0) {
                $response->addHeader($name, $value);
            }
        }

        return $response;
    }

}
