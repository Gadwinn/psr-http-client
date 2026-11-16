<?php

declare(strict_types=1);

namespace G\Http\Client\Handler;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\{RequestInterface, ResponseInterface};

interface HandlerInterface
{
    /**
     * Sends the request and fills the given empty response.
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response Empty response with a writable body.
     *
     * @return ResponseInterface
     * @throws ClientExceptionInterface If the request cannot be sent or the response cannot be read.
     */
    public function handle(RequestInterface $request, ResponseInterface $response): ResponseInterface;
}
