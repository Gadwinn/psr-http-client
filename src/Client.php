<?php

declare(strict_types=1);

namespace G\Http\Client;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\{
    RequestFactoryInterface,
    RequestInterface,
    ResponseFactoryInterface,
    ResponseInterface,
    StreamFactoryInterface,
    StreamInterface,
    UriInterface
};
use G\Http\Client\Handler\{CurlHandler, HandlerInterface};

/**
 * PSR-18 HTTP client.
 *
 * @phpstan-import-type RequestOptions from ClientTrait
 */
class Client implements ClientInterface
{
    use ClientTrait;

    /**
     * @param ResponseFactoryInterface $responseFactory Creates the responses returned by the client.
     * @param RequestFactoryInterface $requestFactory Creates the requests built by request() and its shortcuts.
     * @param StreamFactoryInterface $streamFactory Creates the request and response bodies.
     * @param HandlerInterface $handler Transport, cURL by default.
     */
    public function __construct(
        protected ResponseFactoryInterface $responseFactory,
        protected RequestFactoryInterface $requestFactory,
        protected StreamFactoryInterface $streamFactory,
        protected HandlerInterface $handler = new CurlHandler()
    ) { //
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->handler->handle(
            $request,
            $this->responseFactory->createResponse()->withBody($this->streamFactory->createStream())
        );
    }

    /**
     * Builds a request and sends it.
     *
     * @param RequestOptions $options
     *
     * @throws \Psr\Http\Client\ClientExceptionInterface If an error happens while processing the request.
     */
    public function request(string $method, UriInterface|string $uri, array $options = []): ResponseInterface
    {
        $request = $this->requestFactory->createRequest($method, $uri);

        if (isset($options['protocolVersion'])) {
            $request = $request->withProtocolVersion($options['protocolVersion']);
        }

        foreach ($options['headers'] ?? [] as $name => $value) {
            $request = $request->withAddedHeader($name, $value);
        }

        if (isset($options['body'])) {
            $body = $options['body'] instanceof StreamInterface ? $options['body']
                : $this->streamFactory->createStream($options['body']);

            $request = $request->withBody($body);
        }

        if (isset($options['requestTarget'])) {
            $request = $request->withRequestTarget($options['requestTarget']);
        }

        return $this->sendRequest($request);
    }
}
