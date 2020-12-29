<?php

namespace chitu\http;

use Psr\Http\Message\UriInterface;

class Uri implements UriInterface
{
    const CHAR_SUB_DELIMITERS = '!\$&\'\(\)\*\+,;=';
    const CHAR_UNRESERVED = 'a-zA-Z0-9_\-\.~\pL';

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

    /**
     * @param string $user
     * @param null $password
     * @return UriInterface
     */
    public function withUserInfo($user, $password = null)
    {
        if (!is_string($user)) {
            throw new \InvalidArgumentException(sprintf(
                '%s expects a string user argument; received %s',
                __METHOD__,
                is_object($user) ? get_class($user) : gettype($user)
            ));
        }
        if (null !== $password && !is_string($password)) {
            throw new \InvalidArgumentException(sprintf(
                '%s expects a string or null password argument; received %s',
                __METHOD__,
                is_object($password) ? get_class($password) : gettype($password)
            ));
        }

        $info = $this->filterUserInfo($user);
        if (null !== $password) {
            $info .= ':' . $this->filterUserInfo($password);
        }

        if ($info === $this->userInfo) {
            // Do nothing if no change was made.
            return $this;
        }

        $new = clone $this;
        $new->userInfo = $info;

        return $new;
    }

    /**
     * @param string $host
     * @return UriInterface
     */
    public function withHost($host)
    {
        if (!is_string($host)) {
            throw new \InvalidArgumentException('Host must be a string');
        }

        $host = strtolower($host);
        if ($this->host === $host) {
            return $this;
        }

        $new = clone $this;
        $new->host = $host;

        return $new;
    }

    /**
     * @param int|null $port
     * @return UriInterface
     */
    public function withPort($port)
    {
        if ($port !== null) {
            if (!is_numeric($port) || is_float($port)) {
                throw new \InvalidArgumentException(sprintf(
                    'Invalid port "%s" specified; must be an integer, an integer string, or null',
                    is_object($port) ? get_class($port) : gettype($port)
                ));
            }
            $port = (int)$port;
        }

        if ($port === $this->port) {
            // Do nothing if no change was made.
            return $this;
        }

        if ($port !== null && ($port < 1 || $port > 65535)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid port "%d" specified; must be a valid TCP/UDP port',
                $port
            ));
        }
        $new = clone $this;
        $new->port = $port;

        return $new;
    }

    /**
     * @param string $path
     * @return UriInterface
     */
    public function withPath($path)
    {
        if (!is_string($path)) {
            throw new \InvalidArgumentException('Invalid path provided; must be a string');
        }

        if (strpos($path, '?') !== false) {
            throw new \InvalidArgumentException('Invalid path provided; must not contain a query string');
        }

        if (strpos($path, '#') !== false) {
            throw new \InvalidArgumentException('Invalid path provided; must not contain a URI fragment');
        }

        $path = $this->filterPath($path);
        if ($path === $this->path) {
            // Do nothing if no change was made.
            return $this;
        }

        $new = clone $this;
        $new->path = $path;

        return $new;
    }

    /**
     * @param string $query
     * @return UriInterface
     */
    public function withQuery($query)
    {
        $query = $this->filterQueryAndFragment($query);
        if ($this->query === $query) {
            return $this;
        }

        $new = clone $this;
        $new->query = $query;

        return $new;
    }

    /**
     * @param string $fragment
     * @return UriInterface
     */
    public function withFragment($fragment)
    {
        $fragment = $this->filterQueryAndFragment($fragment);
        if ($this->fragment === $fragment) {
            return $this;
        }

        $new = clone $this;
        $new->fragment = $fragment;

        return $new;
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

    /**
     * @param string $part
     * @return string
     */
    private function filterUserInfo(string $part): string
    {
        $part = $this->filterInvalidUtf8($part);

        return preg_replace_callback('/(?:[^%' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMITERS . ']+|%(?![A-Fa-f0-9]{2}))/u', [$this, 'urlEncodeChar'], $part);
    }

    /**
     * @param string $string
     * @return string
     */
    private function filterInvalidUtf8($string)
    {
        if (preg_match('//u', $string)) {
            return $string;
        }

        $letters = str_split($string);
        foreach ($letters as $i => $letter) {
            if (!preg_match('//u', $letter)) {
                $letters[$i] = $this->urlEncodeChar([$letter]);
            }
        }

        return implode('', $letters);
    }

    /**
     * @param string $path
     * @return string
     */
    private function filterPath($path)
    {
        $path = $this->filterInvalidUtf8($path);
        $path = preg_replace_callback('/(?:[^' . self::CHAR_UNRESERVED . ')(:@&=\+\$,\/;%]+|%(?![A-Fa-f0-9]{2}))/u', [$this, 'urlEncodeChar'], $path);
        if (('' === $path) || ($path[0] !== '/')) {
            return $path;
        }

        return '/' . ltrim($path, '/');
    }

    /**
     * @param $str
     * @return string
     */
    private function filterQueryAndFragment($str)
    {
        if (!is_string($str)) {
            throw new \InvalidArgumentException('Query and fragment must be a string');
        }

        return preg_replace_callback('/(?:[^' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMITERS . '%:@\/\?]++|%(?![A-Fa-f0-9]{2}))/', [$this, 'urlEncodeChar'], $str);
    }

    /**
     * @param array $matches
     * @return string
     */
    private function urlEncodeChar($matches)
    {
        return rawurlencode($matches[0]);
    }
}