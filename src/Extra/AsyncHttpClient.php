<?php

namespace Galdino\Proxy\Extra;

use React\EventLoop\LoopInterface;
use React\HttpClient\Client;
use React\Socket\Connector;
use React\Socket\ConnectorInterface;

class AsyncHttpClient
{
    protected $loop;

    protected $connector;

    public function __construct(LoopInterface $loop, ConnectorInterface $connector = null)
    {
        $this->loop = $loop;

        if ($connector === null) {
            $this->connector = new Connector($loop);
        }
    }

    public function request($method, $url, array $headers = array(), \Closure $callback = null, $protocolVersion = '1.0')
    {
        $client = new Client($this->loop);

        $request = $client->request($method, $url, $headers, $protocolVersion);

        $request->on('response', function ($response) use($callback) {
            $chunks = null;

            $response->on('data', function ($chunk) use($callback, &$chunks) {
                $chunks .= $chunk;
            });

            $response->on('end', function() use($callback, &$chunks) {
                if(!empty($callback))
                    $callback($chunks, null);
            });
        });

        $request->on('error', function (\Exception $e) use($callback) {
            if(!empty($callback))
                $callback(null, $e);
        });

        $request->end();
    }

}
