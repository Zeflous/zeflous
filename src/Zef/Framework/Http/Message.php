<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

/**
 * The shared half of every PSR-7 message: protocol version, headers and
 * body.
 *
 * Requests and responses descend from this abstract base instead of
 * composing a trait (the repository carries zero traits in its message
 * model); the header semantics live in the {@see HeaderStore} value the
 * message owns, so an immutability `with*` is a clone plus one
 * assignment. The body defaults to an empty in-memory stream, and the
 * protocol version follows the grammar {@see HttpVersion} guards.
 */
abstract class Message implements MessageInterface
{
    /** @var non-empty-string */
    private string $protocolVersion;

    private HeaderStore $headerStore;

    private StreamInterface $stream;

    /**
     * @param array<int|string, list<string>|string> $headers         header names
     *                                                                => string or list<string> values
     *                                                                per the PSR contract; a numerical
     *                                                                key is cast to its decimal
     *                                                                spelling, which the token grammar
     *                                                                accepts
     * @param non-empty-string                       $protocolVersion
     *
     * @throws HttpException when the protocol version is not a valid
     *                       HTTP version spelling, or a header is
     *                       rejected by the grammar
     */
    public function __construct(
        ?StreamInterface $stream = null,
        array $headers = [],
        string $protocolVersion = '1.1',
    ) {
        $this->stream = $stream ?? Stream::fromString('');
        HttpVersion::assert($protocolVersion);
        $this->protocolVersion = $protocolVersion;
        $this->headerStore = new HeaderStore();
        foreach ($headers as $name => $value) {
            $this->headerStore = $this->headerStore->with((string) $name, $value);
        }
    }

    #[Override]
    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    /**
     * @param non-empty-string $version
     *
     * @throws HttpException when the version is not a valid HTTP version spelling
     */
    #[Override]
    public function withProtocolVersion(string $version): MessageInterface
    {
        HttpVersion::assert($version);
        $versioned = clone $this;
        $versioned->protocolVersion = $version;

        return $versioned;
    }

    #[Override]
    public function getHeaders(): array
    {
        return $this->headerStore->all();
    }

    #[Override]
    public function hasHeader(string $name): bool
    {
        return $this->headerStore->has($name);
    }

    #[Override]
    public function getHeader(string $name): array
    {
        return $this->headerStore->get($name);
    }

    #[Override]
    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->headerStore->get($name));
    }

    /**
     * @param mixed $value string or list<string> per the PSR contract
     *
     * @throws HttpException when the name or value is rejected by the grammar
     */
    #[Override]
    public function withHeader(string $name, mixed $value): MessageInterface
    {
        $mutated = clone $this;
        $mutated->headerStore = $this->headerStore->with($name, $value);

        return $mutated;
    }

    /**
     * @param mixed $value string or list<string> per the PSR contract
     *
     * @throws HttpException when the name or value is rejected by the grammar
     */
    #[Override]
    public function withAddedHeader(string $name, mixed $value): MessageInterface
    {
        $appended = clone $this;
        $appended->headerStore = $this->headerStore->withAdded($name, $value);

        return $appended;
    }

    #[Override]
    public function withoutHeader(string $name): MessageInterface
    {
        $reduced = clone $this;
        $reduced->headerStore = $this->headerStore->without($name);

        return $reduced;
    }

    #[Override]
    public function getBody(): StreamInterface
    {
        return $this->stream;
    }

    #[Override]
    public function withBody(StreamInterface $body): MessageInterface
    {
        $rebooted = clone $this;
        $rebooted->stream = $body;

        return $rebooted;
    }
}
