<?php

declare(strict_types=1);

namespace G\Http\Client\Tests\Fixtures;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

final class Request implements RequestInterface
{
    use MessageTrait;

    private ?string $requestTarget = null;

    public function __construct(private string $method, private UriInterface $uri)
    {
        if ($uri->getHost() !== '') {
            $this->headers['Host'] = [$uri->getAuthority()];
        }
    }

    public function getRequestTarget(): string
    {
        if ($this->requestTarget !== null) {
            return $this->requestTarget;
        }

        $target = $this->uri->getPath() !== '' ? $this->uri->getPath() : '/';

        return $this->uri->getQuery() !== '' ? $target . '?' . $this->uri->getQuery() : $target;
    }

    public function withRequestTarget(string $requestTarget): static
    {
        $new = clone $this;
        $new->requestTarget = $requestTarget;

        return $new;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod(string $method): static
    {
        $new = clone $this;
        $new->method = $method;

        return $new;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): static
    {
        $new = clone $this;
        $new->uri = $uri;

        return $new;
    }
}
