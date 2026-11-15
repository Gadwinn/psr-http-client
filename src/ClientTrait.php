<?php

declare(strict_types=1);

namespace G\Http\Client;

use Psr\Http\Message\{ResponseInterface, StreamInterface, UriInterface};

/**
 * Shortcuts for the usual HTTP methods.
 *
 * @phpstan-type RequestOptions array{
 *     headers?: array<string, string|string[]>,
 *     body?: string|StreamInterface,
 *     protocolVersion?: string,
 *     requestTarget?: string
 * }
 */
trait ClientTrait
{
    /**
     * @param string $method
     * @param UriInterface|string $uri
     * @param RequestOptions $options
     * @return ResponseInterface
     */
    abstract public function request(
        string $method,
        UriInterface|string $uri,
        array $options = []
    ): ResponseInterface;

    /**
     * @param UriInterface|string $uri
     * @param RequestOptions $options
     * @return ResponseInterface
     */
    public function get(UriInterface|string $uri, array $options = []): ResponseInterface
    {
        return $this->request('GET', $uri, $options);
    }

    /**
     * @param UriInterface|string $uri
     * @param RequestOptions $options
     * @return ResponseInterface
     */
    public function post(UriInterface|string $uri, array $options = []): ResponseInterface
    {
        return $this->request('POST', $uri, $options);
    }

    /**
     * @param UriInterface|string $uri
     * @param RequestOptions $options
     * @return ResponseInterface
     */
    public function put(UriInterface|string $uri, array $options = []): ResponseInterface
    {
        return $this->request('PUT', $uri, $options);
    }

    /**
     * @param UriInterface|string $uri
     * @param RequestOptions $options
     * @return ResponseInterface
     */
    public function patch(UriInterface|string $uri, array $options = []): ResponseInterface
    {
        return $this->request('PATCH', $uri, $options);
    }

    /**
     * @param UriInterface|string $uri
     * @param RequestOptions $options
     * @return ResponseInterface
     */
    public function delete(UriInterface|string $uri, array $options = []): ResponseInterface
    {
        return $this->request('DELETE', $uri, $options);
    }

    /**
     * @param UriInterface|string $uri
     * @param RequestOptions $options
     * @return ResponseInterface
     */
    public function connect(UriInterface|string $uri, array $options = []): ResponseInterface
    {
        return $this->request('CONNECT', $uri, $options);
    }

    /**
     * @param UriInterface|string $uri
     * @param RequestOptions $options
     * @return ResponseInterface
     */
    public function head(UriInterface|string $uri, array $options = []): ResponseInterface
    {
        return $this->request('HEAD', $uri, $options);
    }

    /**
     * @param UriInterface|string $uri
     * @param RequestOptions $options
     * @return ResponseInterface
     */
    public function options(UriInterface|string $uri, array $options = []): ResponseInterface
    {
        return $this->request('OPTIONS', $uri, $options);
    }

    /**
     * @param UriInterface|string $uri
     * @param RequestOptions $options
     * @return ResponseInterface
     */
    public function trace(UriInterface|string $uri, array $options = []): ResponseInterface
    {
        return $this->request('TRACE', $uri, $options);
    }
}
