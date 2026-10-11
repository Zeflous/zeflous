<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * The zero-library PSR-7 response: a status, its reason phrase and the
 * message half inherited from {@see Message}.
 *
 * The status and reason phrase follow the grammar {@see HttpStatusLine}
 * guards; the body conveniently accepts a string (converted through the
 * stream engine) or any PSR-7 stream. Ported from the reference
 * implementation (legacy `zef-framework` `Adapters\Http\Response`).
 *
 * @api
 */
final class Response extends Message implements ResponseInterface
{
    private int $status;

    private string $reasonPhrase;

    /**
     * @param array<string, list<string>|string> $headers
     * @param non-empty-string                   $protocolVersion
     *
     * @throws HttpException when the status, reason phrase, protocol
     *                       version or a header is rejected
     */
    public function __construct(
        int $status = 200,
        array $headers = [],
        StreamInterface|string $body = '',
        string $reasonPhrase = '',
        string $protocolVersion = '1.1',
    ) {
        parent::__construct(
            \is_string($body) ? Stream::fromString($body) : $body,
            $headers,
            $protocolVersion,
        );
        HttpStatusLine::assertCode($status);
        HttpStatusLine::assertPhrase($reasonPhrase);
        $this->status = $status;
        $this->reasonPhrase = HttpStatusLine::resolvePhrase($status, $reasonPhrase);
    }

    #[Override]
    public function getStatusCode(): int
    {
        return $this->status;
    }

    /**
     * @throws HttpException when the status or reason phrase is rejected
     */
    #[Override]
    public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface
    {
        HttpStatusLine::assertCode($code);
        HttpStatusLine::assertPhrase($reasonPhrase);
        $restated = clone $this;
        $restated->status = $code;
        $restated->reasonPhrase = HttpStatusLine::resolvePhrase($code, $reasonPhrase);

        return $restated;
    }

    #[Override]
    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }
}
