<?php

declare(strict_types=1);

namespace G\Http\Client\Tests\Fixtures;

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;

/**
 * PSR-17 request, response and stream factories backed by the test stubs.
 */
final class Factory implements RequestFactoryInterface, ResponseFactoryInterface, StreamFactoryInterface
{
    public function createRequest(string $method, $uri): RequestInterface
    {
        return new Request($method, $uri instanceof UriInterface ? $uri : new Uri($uri));
    }

    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return new Response($code, $reasonPhrase);
    }

    public function createStream(string $content = ''): StreamInterface
    {
        return new Stream($content);
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        throw new RuntimeException('Not needed by the tests.');
    }

    public function createStreamFromResource($resource): StreamInterface
    {
        throw new RuntimeException('Not needed by the tests.');
    }
}
