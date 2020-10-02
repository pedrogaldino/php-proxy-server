<?php

namespace Galdino\Proxy\Server\Traits;

trait ManipulateHeaders
{
    protected $headers = [];

    public function addHeader($name, $value)
    {
        $this->headers[$name] = array_merge($this->headers[$name] ?? [], [
            $value
        ]);

        return $this;
    }

    public function setHeader($name, $value)
    {
        if(!is_array($value)) {
            $value = [$value];
        }

        $this->headers[$name] = $value;

        return $this;
    }

    public function hasHeader($name)
    {
        return isset($this->headers[$name]);
    }

    public function getHeader($name, $default = null)
    {
        return $this->headers[$name] ?? $default;
    }

    public function setHeaders($headers)
    {
        $this->headers = $headers;
        return $this;
    }

    public function mergeHeaders($headers)
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function unsetHeader($name)
    {
        if(isset($this->headers[$name]))
            unset($this->headers[$name]);

        return $this;
    }

}
