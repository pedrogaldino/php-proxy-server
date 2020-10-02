<?php

namespace Galdino\Proxy\Server\Contracts;

interface ManipulateHeadersContract
{
    /**
     * @param string $name
     * @param string $value
     * @return mixed
     */
    public function addHeader($name, $value);

    /**
     * @param string $name
     * @param string $value
     * @return mixed
     */
    public function setHeader($name, $value);

    /**
     * @param string $name
     * @return bool
     */
    public function hasHeader($name);

    /**
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function getHeader($name, $default = null);

    /**
     * @return mixed
     */
    public function getHeaders();

    /**
     * @param mixed $headers
     * @return mixed
     */
    public function setHeaders($headers);

    /**
     * @param mixed $headers
     * @return mixed
     */
    public function mergeHeaders($headers);

    /**
     * @param string $name
     * @return mixed
     */
    public function unsetHeader($name);

}
