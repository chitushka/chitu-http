<?php

namespace chitu\http;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;


class Message implements MessageInterface
{
    /**
     * List of all registered headers, as key => array of values.
     *
     * @var array
     */
    protected $headers = [];

    /**
     * Map of normalized header name to original name used to register header.
     *
     * @var array
     */
    protected $headerNames = [];

    /**
     * @var string
     */
    private $protocol = '1.1';

    /**
     * @var StreamInterface
     */
    private $stream;

    /**
     * Retrieves the HTTP protocol version as a string.
     *
     * The string MUST contain only the HTTP version number (e.g., "1.1", "1.0").
     *
     * @return string HTTP protocol version.
     */
    public function getProtocolVersion()
    {
        return $this->protocol;
    }

    /**
     * Return an instance with the specified HTTP protocol version.
     *
     * The version string MUST contain only the HTTP version number (e.g.,
     * "1.1", "1.0").
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new protocol version.
     *
     * @param string $version HTTP protocol version
     * @return static
     */
    public function withProtocolVersion($version): MessageInterface
    {
        if (empty($version) || !is_string($version) || !preg_match('#^(1\.[01]|2)$#', $version)) {
            throw new \InvalidArgumentException(sprintf('Unsupported HTTP protocol version; must be a string: "%s"', $version));
        }
        $new = clone $this;
        $new->protocol = $version;

        return $new;
    }

    /**
     * Retrieves all message headers.
     *
     * The keys represent the header name as it will be sent over the wire, and
     * each value is an array of strings associated with the header.
     *
     *     // Represent the headers as a string
     *     foreach ($message->getHeaders() as $name => $values) {
     *         echo $name . ": " . implode(", ", $values);
     *     }
     *
     *     // Emit headers iteratively:
     *     foreach ($message->getHeaders() as $name => $values) {
     *         foreach ($values as $value) {
     *             header(sprintf('%s: %s', $name, $value), false);
     *         }
     *     }
     *
     * @return array Returns an associative array of the message's headers. Each
     *     key MUST be a header name, and each value MUST be an array of strings.
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Checks if a header exists by the given case-insensitive name.
     *
     * @param string $header Case-insensitive header name.
     * @return bool Returns true if any header names match the given header
     *     name using a case-insensitive string comparison. Returns false if
     *     no matching header name is found in the message.
     */
    public function hasHeader($header): bool
    {
        return isset($this->headerNames[strtolower($header)]);
    }

    /**
     * Retrieves a message header value by the given case-insensitive name.
     *
     * This method returns an array of all the header values of the given
     * case-insensitive header name.
     *
     * If the header does not appear in the message, this method MUST return an
     * empty array.
     *
     * @param string $header Case-insensitive header field name.
     * @return string[] An array of string values as provided for the given
     *    header. If the header does not appear in the message, this method MUST
     *    return an empty array.
     */
    public function getHeader($header): array
    {
        if (!$this->hasHeader($header)) {
            return [];
        }
        $header = $this->headerNames[strtolower($header)];

        return $this->headers[$header];
    }

    /**
     * Retrieves a comma-separated string of the values for a single header.
     *
     * This method returns all of the header values of the given
     * case-insensitive header name as a string concatenated together using
     * a comma.
     *
     * NOTE: Not all header values may be appropriately represented using
     * comma concatenation. For such headers, use getHeader() instead
     * and supply your own delimiter when concatenating.
     *
     * If the header does not appear in the message, this method MUST return
     * an empty string.
     *
     * @param string $name Case-insensitive header field name.
     * @return string A string of values as provided for the given header
     *    concatenated together using a comma. If the header does not appear in
     *    the message, this method MUST return an empty string.
     */
    public function getHeaderLine($name): string
    {
        $value = $this->getHeader($name);
        if (empty($value)) {
            return '';
        }

        return implode(',', $value);
    }

    /**
     * Return an instance with the provided header, replacing any existing
     * values of any headers with the same case-insensitive name.
     *
     * While header names are case-insensitive, the casing of the header will
     * be preserved by this function, and returned from getHeaders().
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new and/or updated header and value.
     *
     * @param string $header Case-insensitive header field name.
     * @param string|string[] $value Header value(s).
     * @return static
     * @throws \InvalidArgumentException for invalid header names or values.
     */
    public function withHeader($header, $value): MessageInterface
    {
        if (!is_string($header) || !preg_match('/^[a-zA-Z0-9\'`#$%&*+.^_|~!-]+$/', $header)) {
            throw new \InvalidArgumentException(sprintf('Header name is not valid: "%s"', $header));

        }

        $normalized = strtolower($header);
        $new = clone $this;

        if ($new->hasHeader($header)) {
            unset($new->headers[$new->headerNames[$normalized]]);
        }
        if (!is_array($value)) {
            $value = [$value];
        }
        if ([] === $value) {
            throw new \InvalidArgumentException('Invalid header value; cannot be an empty array');
        }

        $value = array_map(function ($headValue) {
            if (!is_string($headValue) && !is_numeric($headValue)) {
                throw new \InvalidArgumentException(sprintf(
                    'Invalid header value type; must be a string or numeric; received %s',
                    (is_object($headValue) ? get_class($headValue) : gettype($headValue))
                ));
            }
            $headValue = (string)$headValue;
            if (!preg_match('/[^\x09\x0a\x0d\x20-\x7E\x80-\xFE]/', $headValue) ||
                !preg_match("#(?:(?:(?<!\r)\n)|(?:\r(?!\n))|(?:\r\n(?![ \t])))#", $headValue)) {
                throw new \InvalidArgumentException(sprintf('"%s" is not valid header value', $headValue));
            }

            return $headValue;
        }, array_values($value));
        $new->headerNames[$normalized] = $header;
        $new->headers[$header] = $value;

        return $new;
    }


    /**
     * Return an instance with the specified header appended with the
     * given value.
     *
     * Existing values for the specified header will be maintained. The new
     * value(s) will be appended to the existing list. If the header did not
     * exist previously, it will be added.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new header and/or value.
     *
     * @param string $header Case-insensitive header field name to add.
     * @param string|string[] $value Header value(s).
     * @return static
     * @throws \InvalidArgumentException for invalid header names or values.
     */
    public function withAddedHeader($header, $value): MessageInterface
    {
        if (!is_string($header) || !preg_match('/^[a-zA-Z0-9\'`#$%&*+.^_|~!-]+$/', $header)) {
            throw new \InvalidArgumentException(sprintf('Header name type is not valid: "%s"', $header));
        }
        if (!$this->hasHeader($header)) {
            return $this->withHeader($header, $value);
        }

        $header = $this->headerNames[strtolower($header)];
        $new = clone $this;

        if (!is_array($value)) {
            $value = [$value];
        }
        if ([] === $value) {
            throw new \InvalidArgumentException('Invalid header value; cannot be an empty array');
        }

        $value = array_map(function ($headValue) {
            if (!is_string($headValue) && !is_numeric($headValue)) {
                throw new \InvalidArgumentException(sprintf(
                    'Invalid header value type; must be a string or numeric; received %s',
                    (is_object($headValue) ? get_class($headValue) : gettype($headValue))
                ));
            }
            $headValue = (string)$headValue;
            if (!preg_match('/[^\x09\x0a\x0d\x20-\x7E\x80-\xFE]/', $headValue) ||
                !preg_match("#(?:(?:(?<!\r)\n)|(?:\r(?!\n))|(?:\r\n(?![ \t])))#", $headValue)) {
                throw new \InvalidArgumentException(sprintf('"%s" is not valid header value', $headValue));
            }

            return $headValue;
        }, array_values($value));
        $new->headers[$header] = array_merge($this->headers[$header], $value);

        return $new;
    }

    /**
     * Return an instance without the specified header.
     *
     * Header resolution MUST be done without case-sensitivity.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that removes
     * the named header.
     *
     * @param string $header Case-insensitive header field name to remove.
     * @return static
     */
    public function withoutHeader($header): MessageInterface
    {
        if (!$this->hasHeader($header)) {
            return clone $this;
        }

        $normalized = strtolower($header);
        $original = $this->headerNames[$normalized];
        $new = clone $this;
        unset($new->headers[$original], $new->headerNames[$normalized]);

        return $new;
    }

    /**
     * Gets the body of the message.
     *
     * @return StreamInterface Returns the body as a stream.
     */
    public function getBody(): StreamInterface
    {
        return $this->stream;
    }

    /**
     * Return an instance with the specified message body.
     *
     * The body MUST be a StreamInterface object.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return a new instance that has the
     * new body stream.
     *
     * @param StreamInterface $body Body.
     * @return static
     * @throws \InvalidArgumentException When the body is not valid.
     */
    public function withBody(StreamInterface $body): MessageInterface
    {
        $new = clone $this;
        $new->stream = $body;

        return $new;
    }
}