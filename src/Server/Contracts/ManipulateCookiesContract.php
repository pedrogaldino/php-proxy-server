<?php

namespace Galdino\Proxy\Server\Contracts;

interface ManipulateCookiesContract extends ManipulateHeadersContract
{
    /**
     * @param $name
     * @param $value
     * @return mixed
     */
    public function addCookie($name, $value);

    /**
     * @return array
     */
    public function parseCookies() : array;

    /**
     * @return string
     */
    public function parseCookiesToString() : string;

    /**
     * @return array
     */
    public function getCookies() : array;

    /**
     * @param string $name
     * @return bool
     */
    public function hasCookie($name);

    /**
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function getCookie($name, $default = null);

    /**
     * @return void
     */
    public function unsetCookiesHeaders();
}
