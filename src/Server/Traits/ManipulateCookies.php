<?php

namespace Galdino\Proxy\Server\Traits;

trait ManipulateCookies
{
    use ManipulateHeaders;

    public function addCookie($name, $value)
    {
        $cookies = $this->parseCookiesToString();

        $cookies .= $name . '=' . $value . ';';

        $this->unsetCookiesHeaders();

        $this->addHeader('Cookie', $cookies);

        return $this;
    }

    public function parseCookiesToString() : string
    {
        $cookies = '';

        foreach ($this->getCookies() as $name => $value) {
            if(!empty($name))
                $cookies .= $name . '=' . $value . ';';
        }

        return $cookies;
    }

    public function parseCookies() : array
    {
        $cookie = $this->getHeader('cookie', []);
        $Cookie = $this->getHeader('Cookie', []);

        if(is_string($cookie)) {
            $cookie = [$cookie];
        }

        if(is_string($Cookie)) {
            $Cookie = [$Cookie];
        }

        $cookies = array_merge($cookie, $Cookie);
        $cookies = implode(';', $cookies);
        $cookies = str_replace(';;', ';', $cookies);
        $cookies = str_replace('; ', ';', $cookies);

        if(substr($cookies, -1, 1) === ';') {
            $cookies = substr($cookies, 0, -1);
        }

        $cookiesArr = [];

        foreach (explode(';', $cookies) as $cookie) {
            if(strpos($cookie, '=') > 0) {
                $equalPos = strpos($cookie, '=');
                $key = substr($cookie, 0, $equalPos);
                $value = substr($cookie, $equalPos + 1, strlen($cookie));

                $cookiesArr[$key] = $value;
            } else {
                $cookiesArr[$cookie] = '';
            }
        }

        return $cookiesArr;
    }

    public function unsetCookiesHeaders()
    {
        $this->unsetHeader('cookie');
        $this->unsetHeader('Cookie');
    }

    public function getCookies() : array
    {
        return $this->parseCookies();
    }

    public function getCookie($name, $default = null)
    {
        return $this->parseCookies()[$name] ?? $default;
    }

    public function unsetCookie($name)
    {
        $cookies = $this->getCookies();

        unset($cookies[$name]);

        $this->unsetCookiesHeaders();

        foreach ($cookies as $key => $value) {
            if (!empty($key)) {
                $this->addCookie($key, $value);
            }
        }

        return $this;
    }

    public function deleteCookie($name)
    {
        $this->addSetCookie($name, '');
        return $this;
    }

    public function hasCookie($name)
    {
        return isset($this->getCookies()[$name]);
    }

}
