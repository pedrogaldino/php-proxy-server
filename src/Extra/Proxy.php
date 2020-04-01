<?php

namespace Galdino\Proxy\Extra;

use Clue\React\HttpProxy\ProxyConnector;
use React\Promise\Promise;
use React\Socket\ConnectorInterface;
use React\Socket\ConnectionInterface;
use React\Socket\FixedUriConnector;

class Proxy extends ProxyConnector
{
    private $connector;
    private $proxyUri;
    private $headers = '';

    /**
     * Instantiate a new ProxyConnector which uses the given $proxyUrl
     *
     * @param string $proxyUrl The proxy URL may or may not contain a scheme and
     *     port definition. The default port will be `80` for HTTP (or `443` for
     *     HTTPS), but many common HTTP proxy servers use custom ports.
     * @param ConnectorInterface $connector In its most simple form, the given
     *     connector will be a \React\Socket\Connector if you want to connect to
     *     a given IP address.
     * @param array $httpHeaders Custom HTTP headers to be sent to the proxy.
     * @throws \InvalidArgumentException if the proxy URL is invalid
     */
    public function __construct($proxyUrl, ConnectorInterface $connector, array $httpHeaders = array())
    {
        // support `http+unix://` scheme for Unix domain socket (UDS) paths
        if (preg_match('/^http\+unix:\/\/(.*?@)?(.+?)$/', $proxyUrl, $match)) {
            // rewrite URI to parse authentication from dummy host
            $proxyUrl = 'http://' . $match[1] . 'localhost';

            // connector uses Unix transport scheme and explicit path given
            $connector = new FixedUriConnector(
                'unix://' . $match[2],
                $connector
            );
        }

        if (strpos($proxyUrl, '://') === false) {
            $proxyUrl = 'http://' . $proxyUrl;
        }

        $parts = parse_url($proxyUrl);
        if (!$parts || !isset($parts['scheme'], $parts['host']) || ($parts['scheme'] !== 'http' && $parts['scheme'] !== 'https')) {
            throw new \InvalidArgumentException('Invalid proxy URL "' . $proxyUrl . '"');
        }

        // apply default port and TCP/TLS transport for given scheme
        if (!isset($parts['port'])) {
            $parts['port'] = $parts['scheme'] === 'https' ? 443 : 80;
        }
        $parts['scheme'] = $parts['scheme'] === 'https' ? 'tls' : 'tcp';

        $this->connector = $connector;
        $this->proxyUri = $parts['scheme'] . '://' . $parts['host'] . ':' . $parts['port'];

        // prepare Proxy-Authorization header if URI contains username/password
        if (isset($parts['user']) || isset($parts['pass'])) {
            $this->headers = 'Proxy-Authorization: Basic ' . base64_encode(
                    rawurldecode($parts['user'] . ':' . (isset($parts['pass']) ? $parts['pass'] : ''))
                ) . "\r\n";
        }

        // append any additional custom request headers
        foreach ($httpHeaders as $name => $values) {
            foreach ((array)$values as $value) {
                $this->headers .= $name . ': ' . $value . "\r\n";
            }
        }

        parent::__construct($proxyUrl, $connector, $httpHeaders);
    }

    public function getProxyTarget($uri)
    {
        if (strpos($uri, '://') === false) {
            $uri = 'tcp://' . $uri;
        }

        $parts = parse_url($uri);

        // construct URI to HTTP CONNECT proxy server to connect to
        $proxyUri = $this->proxyUri;

        // append path from URI if given
        if (isset($parts['path'])) {
            $proxyUri .= $parts['path'];
        }

        // parse query args
        $args = array();
        if (isset($parts['query'])) {
            parse_str($parts['query'], $args);
        }

        // append hostname from URI to query string unless explicitly given
        if (!isset($args['hostname'])) {
            $args['hostname'] = trim($parts['host'], '[]');
        }

        // append query string
        $proxyUri .= '?' . http_build_query($args, '', '&');

        // append fragment from URI if given
        if (isset($parts['fragment'])) {
            $proxyUri .= '#' . $parts['fragment'];
        }

        return $proxyUri;
    }

    public function connect($uri)
    {
        return new Promise(function($resolve, $reject) use($uri) {
            if(strpos($uri, '443') > -1) {
                parent::connect($uri)
                    ->then(function($res) use($resolve) {
                        $resolve($res);
                    })
                    ->otherwise($reject);
            } else {
                $this
                    ->connector
                    ->connect($this->getProxyTarget($this->proxyUri))
                    ->then(function(ConnectionInterface $stream) use($resolve) {
//                        dump($this->headers);

//                        $stream->write($this->headers);
                        $resolve($stream);
                    }, function ($err) use ($reject) {
                        $reject($err);
                    });
            }
        });
    }

}