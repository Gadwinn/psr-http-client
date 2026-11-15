<?php

declare(strict_types=1);

namespace G\Http\Client\Tests\Fixtures;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;

/**
 * Minimal PSR-7 message used by the tests (no concrete implementation is required).
 */
trait MessageTrait
{
    private string $protocolVersion = '1.1';

    /** @var array<string, string[]> */
    private array $headers = [];

    private StreamInterface $body;

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion(string $version): static
    {
        $new = clone $this;
        $new->protocolVersion = $version;

        return $new;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        return $this->findHeader($name) !== null;
    }

    public function getHeader(string $name): array
    {
        $found = $this->findHeader($name);

        return $found !== null ? $this->headers[$found] : [];
    }

    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader(string $name, $value): static
    {
        return $this->withoutHeader($name)->withAddedHeader($name, $value);
    }

    public function withAddedHeader(string $name, $value): static
    {
        if (preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/D', $name) !== 1) {
            throw new InvalidArgumentException('Invalid header name.');
        }

        $new = clone $this;
        $key = $this->findHeader($name) ?? $name;
        $new->headers[$key] = array_merge($this->headers[$key] ?? [], array_map('strval', (array) $value));

        return $new;
    }

    public function withoutHeader(string $name): static
    {
        $new = clone $this;
        $found = $this->findHeader($name);
        if ($found !== null) {
            unset($new->headers[$found]);
        }

        return $new;
    }

    public function getBody(): StreamInterface
    {
        return $this->body ??= new Stream();
    }

    public function withBody(StreamInterface $body): static
    {
        $new = clone $this;
        $new->body = $body;

        return $new;
    }

    private function findHeader(string $name): ?string
    {
        foreach (array_keys($this->headers) as $key) {
            if (strcasecmp((string) $key, $name) === 0) {
                return (string) $key;
            }
        }

        return null;
    }
}
