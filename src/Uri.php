<?php

namespace chitu\http;

use Psr\Http\Message\UriInterface;

class Uri implements UriInterface
{
    /** @var string Uri scheme. */
    private $scheme = '';

    /** @var string Uri user info. */
    private $userInfo = '';

    /** @var string Uri host. */
    private $host = '';

    /** @var int|null Uri port. */
    private $port;

    /** @var string Uri path. */
    private $path = '';

    /** @var string Uri query string. */
    private $query = '';

    /** @var string Uri fragment. */
    private $fragment = '';

    /** @var array Supported http scheme */
    protected $allowedSchemes = ['http', 'https'];

    /**
     * @return string
     */
    public function getScheme()
    {
        return $this->scheme;
    }

    /**
     * @return string
     */
    public function getAuthority()
    {
        $authority = $this->host;
        if ($this->userInfo !== '') {
            $authority = $this->userInfo . '@' . $authority;
        }

        if ($this->port !== null) {
            $authority .= ':' . $this->port;
        }

        return $authority;
    }

    /**
     * @return string
     */
    public function getUserInfo()
    {
        return $this->userInfo;
    }

    /**
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @return int|null
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @return string
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @return string
     */
    public function getFragment()
    {
        return $this->fragment;
    }

    /**
     * @param string $scheme
     * @return UriInterface
     */
    public function withScheme($scheme)
    {
        if (!is_string($scheme)) {
            throw new \InvalidArgumentException(sprintf(
                'Scheme must be a string: "%s"',
                is_object($scheme) ? get_class($scheme) : gettype($scheme)
            ));
        }

        $scheme = strtolower($scheme);
        if (!isset($this->allowedSchemes[$scheme])) {
            throw new \InvalidArgumentException(sprintf('Unsupported scheme: "%s"', $scheme));
        }
        if ($scheme === $this->scheme) {
            return $this;
        }
        $new = clone $this;
        $new->scheme = $scheme;

        return $new;
    }


    public function withUserInfo($user, $password = null)
    {
        // TODO: Implement withUserInfo() method.
    }


    public function withHost($host)
    {
        // TODO: Implement withHost() method.
    }


    public function withPort($port)
    {
        // TODO: Implement withPort() method.
    }


    public function withPath($path)
    {
        // TODO: Implement withPath() method.
    }


    public function withQuery($query)
    {
        // TODO: Implement withQuery() method.
    }


    public function withFragment($fragment)
    {
        // TODO: Implement withFragment() method.
    }


    /**
     * @return string
     */
    public function __toString()
    {
        $uri = '';
        if ($this->getScheme() != '') {
            $uri .= $this->getScheme() . ':';
        }

        if ($this->getAuthority() != '' || $this->getScheme() === 'file') {
            $uri .= '//' . $this->getAuthority();
        }

        $uri .= $this->getPath();

        if ($this->getQuery() != '') {
            $uri .= '?' . $this->getQuery();
        }

        if ($this->getFragment() != '') {
            $uri .= '#' . $this->getFragment();
        }

        return $uri;
    }
}