<?php

declare(strict_types=1);

namespace G\Http\Client\Exceptions;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Exception;
use Throwable;

class ClientException extends Exception implements ClientExceptionInterface
{
    /**
     * @param RequestInterface $request
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(
        protected RequestInterface $request,
        string $message = "",
        int $code = 0,
        Throwable|null $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return RequestInterface
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
