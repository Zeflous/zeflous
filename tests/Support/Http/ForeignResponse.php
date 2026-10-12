<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Deterministic foreign-response double: carries whatever status, header
 * map and body stream it is constructed with, with none of the layer's
 * construction-time validation. The emitter re-judges every field at
 * emission time precisely because such an implementation is allowed to
 * exist -- an invalid name or value here must be skipped, not trusted and
 * not fatal.
 *
 * The constructor takes an honestly-shaped map, while the deliberate
 * engine-shaped lies (a bare scalar member, a non-string member) enter
 * through the untyped PSR-7 wither parameters -- the same channel every
 * PSR-7 consumer faces from a foreign implementation, and the same one
 * the reference tests used to feed non-string header values.
 */
final class ForeignResponse implements ResponseInterface
{
    /**
     * @param int                         $status  the raw status code the double answers
     * @param array<string, list<string>> $headers the header map the double
     *                                             answers verbatim (grammar-invalid names and values included)
     * @param StreamInterface             $body    the body stream the double answers
     */
    public function __construct(
        private readonly int $status,
        private readonly array $headers,
        private readonly StreamInterface $body,
    ) {
    }

    #[Override]
    public function getStatusCode(): int
    {
        return $this->status;
    }

    #[Override]
    public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface
    {
        return new self($code, $this->headers, $this->body);
    }

    #[Override]
    public function getReasonPhrase(): string
    {
        return '';
    }

    #[Override]
    public function getHeaders(): array
    {
        return $this->headers;
    }

    #[Override]
    public function hasHeader(string $name): bool
    {
        foreach (array_keys($this->headers) as $name_) {
            if (strcasecmp($name_, $name) === 0) {
                return true;
            }
        }

        return false;
    }

    #[Override]
    public function getHeader(string $name): array
    {
        foreach ($this->headers as $name_ => $values) {
            if (strcasecmp($name_, $name) === 0) {
                return $values;
            }
        }

        return [];
    }

    #[Override]
    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    /**
     * Stores the raw pair verbatim: the lie channel for engine-shaped
     * values the typed constructor cannot express.
     *
     * @param array-key $name  the raw foreign header name, as the engine
     *                         would hold it for a map key
     * @param mixed     $value the raw foreign header value -- a bare scalar, a
     *                         list, or any engine shape a foreign implementation
     *                         may answer
     */
    #[Override]
    public function withHeader($name, $value): self
    {
        /** @var array<string, list<string>> $headers */
        $headers = [$name => $value, ...$this->headers];

        return new self($this->status, $headers, $this->body);
    }

    /**
     * Stores the raw pair verbatim: the lie channel for engine-shaped
     * values the typed constructor cannot express.
     *
     * @param array-key $name  the raw foreign header name, as the engine
     *                         would hold it for a map key
     * @param mixed     $value the raw foreign header value -- a bare scalar, a
     *                         list, or any engine shape a foreign implementation
     *                         may answer
     */
    #[Override]
    public function withAddedHeader($name, $value): self
    {
        /** @var array<string, list<string>> $headers */
        $headers = [$name => $value, ...$this->headers];

        return new self($this->status, $headers, $this->body);
    }

    #[Override]
    public function withoutHeader(string $name): ResponseInterface
    {
        $rows = [];
        foreach ($this->headers as $name_ => $values) {
            if (strcasecmp($name_, $name) === 0) {
                continue;
            }

            $rows[$name_] = $values;
        }

        return new self($this->status, $rows, $this->body);
    }

    #[Override]
    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    #[Override]
    public function withBody(StreamInterface $body): ResponseInterface
    {
        return new self($this->status, $this->headers, $body);
    }

    #[Override]
    public function getProtocolVersion(): string
    {
        return '1.1';
    }

    #[Override]
    public function withProtocolVersion(string $version): ResponseInterface
    {
        return $this;
    }
}
