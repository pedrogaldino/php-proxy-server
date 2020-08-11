<?php

namespace Galdino\Proxy\Server;

use GuzzleHttp\Cookie\SetCookie;
use Galdino\Proxy\Server\Contracts\ManipulateCookiesContract;
use Galdino\Proxy\Server\Contracts\ManipulateHeadersContract;
use Galdino\Proxy\Server\Traits\ManipulateCookies;
use Psr\Http\Message\UriInterface;
use function GuzzleHttp\Psr7\stream_for;

class Response implements ManipulateHeadersContract, ManipulateCookiesContract
{
    use ManipulateCookies;

    protected $uri;

    protected $headers = [];

    protected $statusCode = 200;

    protected $transferTime;

    protected $body;

    public function addSetCookie(
        $name,
        $value,
        $domain = null,
        $path = '/',
        $maxAge = null,
        $expires = null,
        $secure = false,
        $discard = false,
        $httpOnly = false
    )
    {
        $cookie = (string) new SetCookie([
            'Name'     => $name,
            'Value'    => $value,
            'Domain'   => $domain,
            'Path'     => $path,
            'Max-Age'  => $maxAge,
            'Expires'  => $expires,
            'Secure'   => $secure,
            'Discard'  => $discard,
            'HttpOnly' => $httpOnly
        ]);

        $cookies = $this->getHeader('set-cookie', []);
        $cookies[] = $cookie;

        $cookies1 = $this->getHeader('Set-Cookie', []);
        $cookies2 = $this->getHeader('Set-cookie', []);
        $cookies3 = $this->getHeader('set-Cookie', []);

        if (!empty($cookies1)) {
            $cookies = array_merge($cookies, $cookies1);
        }

        if (!empty($cookies2)) {
            $cookies = array_merge($cookies, $cookies2);
        }

        if (!empty($cookies3)) {
            $cookies = array_merge($cookies, $cookies3);
        }

        $this->mergeHeaders([
            'set-cookie' => $cookies
        ]);

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
     * @return Response
     */
    public function setUri($uri)
    {
        $this->uri = $uri;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * @param mixed $statusCode
     * @return Response
     */
    public function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getTransferTime()
    {
        return $this->transferTime;
    }

    /**
     * @param mixed $transferTime
     * @return Response
     */
    public function setTransferTime($transferTime)
    {
        $this->transferTime = $transferTime;
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
     * @return Response
     */
    public function setBody($body)
    {
        $this->body = $body;
        return $this;
    }


}
