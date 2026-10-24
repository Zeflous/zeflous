<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Override;
use Zef\Framework\Http\BodyParserInterface;

/**
 * Request-capturing strategy double: answers the JSON decode of
 * whatever body it was handed while remembering it -- the evidence a
 * middleware test needs that the strategy table was (or was not)
 * consulted, and with which body.
 */
final class RecordingParser implements BodyParserInterface
{
    private ?string $body = null;

    #[Override]
    public function supports(string $mediaType): bool
    {
        return true;
    }

    #[Override]
    public function parse(string $body): ?array
    {
        $this->body = $body;

        $decoded = json_decode($body, true, 32, \JSON_THROW_ON_ERROR);
        \assert(\is_array($decoded) || $decoded === null);

        return $decoded;
    }

    /**
     * The body the strategy was handed, or null when it was never
     * consulted.
     */
    public function parsed(): ?string
    {
        return $this->body;
    }
}
