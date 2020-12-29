<?php

namespace chitu\http;

use Psr\Http\Message\StreamInterface;

class Stream implements StreamInterface
{
    /** @var resource */
    private $stream;
    /** @var bool */
    private $seekable;
    /** @var bool */
    private $readable;
    /** @var bool */
    private $writable;
    /** @var string|null */
    private $uri;

    /**
     * @return string
     */
    public function __toString()
    {
        if (!$this->isReadable()) {
            return '';
        }
        try {
            if ($this->isSeekable()) {
                $this->rewind();
            }

            return $this->getContents();
        } catch (\RuntimeException $e) {
            return '';
        }
    }

    /**
     * @return void
     */
    public function close()
    {
        if (isset($this->stream)) {
            if (is_resource($this->stream)) {
                fclose($this->stream);
            }
            $this->detach();
        }
    }

    /**
     * @return resource|null
     */
    public function detach()
    {
        $result = $this->stream;
        $this->stream = null;

        return $result;
    }

    /**
     * @return int|null
     */
    public function getSize()
    {
        if (!isset($this->stream)) {
            return null;
        }

        $stats = fstat($this->stream);
        if (is_array($stats) && isset($stats['size'])) {
            return $stats['size'];
        }

        return null;
    }

    /**
     * @return int
     */
    public function tell()
    {
        if (!isset($this->stream)) {
            throw new \RuntimeException('Stream is detached');
        }

        $result = ftell($this->stream);
        if (!is_int($result)) {
            throw new \RuntimeException('Unable to determine stream position');
        }

        return $result;
    }

    /**
     * @return bool
     */
    public function eof()
    {
        if (!isset($this->stream)) {
            throw new \RuntimeException('Stream is detached');
        }

        return feof($this->stream);
    }

    /**
     * @return bool
     */
    public function isSeekable()
    {
        if (!isset($this->stream)) {
            return false;
        }

        $meta = stream_get_meta_data($this->stream);
        return $meta['seekable'];
    }

    /**
     * @param int $offset
     * @param int $whence
     * @return void
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        if (!isset($this->stream)) {
            throw new \RuntimeException('Stream is detached');
        }

        if (!$this->isSeekable()) {
            throw new \RuntimeException('Stream is not seekable');
        }

        $result = fseek($this->stream, $offset, $whence);
        if (-1 === $result) {
            throw new \RuntimeException('Unable to seek to stream position '
                . $offset . ' with whence ' . var_export($whence, true));
        }
    }

    /**
     * @return void
     */
    public function rewind()
    {
        $this->seek(0);
    }

    /**
     * @return bool
     */
    public function isWritable()
    {
        if (!isset($this->stream)) {
            return false;
        }
        $meta = stream_get_meta_data($this->stream);

        return (bool)preg_match('/a|w|r\+|rb\+|rw|x|c/', $meta['mode']);
    }

    /**
     * @param string $string
     * @return int
     */
    public function write($string)
    {
        if (!isset($this->stream)) {
            throw new \RuntimeException('Stream is detached');
        }

        if (!$this->isWritable()) {
            throw new \RuntimeException('Cannot write to a non-writable stream');
        }

        $result = fwrite($this->stream, $string);
        if (false === $result) {
            throw new \RuntimeException('Unable to write to stream');
        }

        return $result;
    }

    /**
     * @return bool
     */
    public function isReadable()
    {
        if (!isset($this->stream)) {
            return false;
        }
        $meta = stream_get_meta_data($this->stream);

        return (bool)preg_match('/r|a\+|ab\+|w\+|wb\+|x\+|xb\+|c\+|cb\+/', $meta['mode']);
    }

    /**
     * @param int $length
     * @return string
     */
    public function read($length)
    {
        if (!isset($this->stream)) {
            throw new \RuntimeException('Stream is detached');
        }
        if (!$this->isReadable()) {
            throw new \RuntimeException('Cannot read from non-readable stream');
        }
        if ($length < 0) {
            throw new \RuntimeException('Length parameter cannot be negative');
        }
        if (0 === $length) {
            return '';
        }

        $string = fread($this->stream, $length);
        if (false === $string) {
            throw new \RuntimeException('Unable to read from stream');
        }

        return $string;
    }

    /**
     * @return string
     */
    public function getContents()
    {
        if (!isset($this->stream)) {
            throw new \RuntimeException('Stream is detached');
        }
        if (!$this->isReadable()) {
            throw new \RuntimeException('Stream is not readable');
        }

        $contents = stream_get_contents($this->stream);
        if ($contents === false) {
            throw new \RuntimeException('Unable to read stream contents');
        }

        return $contents;
    }

    /**
     * @param string $key Specific metadata to retrieve.
     * @return array|mixed|null Returns an associative array if no key is
     *     provided. Returns a specific key value if a key is provided and the
     *     value is found, or null if the key is not found.
     */
    public function getMetadata($key = null)
    {
        if (!isset($this->stream)) {
            throw new \RuntimeException('Stream is detached');
        }
        if (null === $key) {
            return stream_get_meta_data($this->stream);
        }
        $metadata = stream_get_meta_data($this->stream);
        if (!array_key_exists($key, $metadata)) {
            return null;
        }

        return $metadata[$key];
    }
}