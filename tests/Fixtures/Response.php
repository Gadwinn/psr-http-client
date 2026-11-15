<?php

declare(strict_types=1);

namespace G\Http\Client\Tests\Fixtures;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

final class Response implements ResponseInterface
{
    use MessageTrait;

    public function __construct(private int $statusCode = 200, private string $reasonPhrase = '')
    {
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function withStatus(int $code, string $reasonPhrase = ''): static
    {
        if ($code < 100 || $code > 599) {
            throw new InvalidArgumentException('Invalid status code.');
        }

        $new = clone $this;
        $new->statusCode = $code;
        $new->reasonPhrase = $reasonPhrase;

        return $new;
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }
}
