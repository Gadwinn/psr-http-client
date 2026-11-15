<?php

declare(strict_types=1);

namespace G\Http\Client\Tests\Fixtures;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * In-memory stream. With $knownSize = false it behaves like a stream whose size cannot be determined.
 */
final class Stream implements StreamInterface
{
    private int $position = 0;

    public function __construct(
        private string $content = '',
        private bool $knownSize = true,
        private bool $seekable = true
    ) {
    }

    public function __toString(): string
    {
        return $this->content;
    }

    public function close(): void
    {
    }

    public function detach()
    {
        return null;
    }

    public function getSize(): ?int
    {
        return $this->knownSize ? strlen($this->content) : null;
    }

    public function tell(): int
    {
        return $this->position;
    }

    public function eof(): bool
    {
        return $this->position >= strlen($this->content);
    }

    public function isSeekable(): bool
    {
        return $this->seekable;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (!$this->seekable) {
            throw new RuntimeException('Stream is not seekable.');
        }

        $this->position = $offset;
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        return true;
    }

    public function write(string $string): int
    {
        $this->content .= $string;
        $this->position = strlen($this->content);

        return strlen($string);
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function read(int $length): string
    {
        $chunk = substr($this->content, $this->position, $length);
        $this->position += strlen($chunk);

        return $chunk;
    }

    public function getContents(): string
    {
        return $this->read(strlen($this->content));
    }

    public function getMetadata(?string $key = null)
    {
        return $key === null ? [] : null;
    }
}
