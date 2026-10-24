<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Override;
use Zef\Framework\Http\BodyParserInterface;
use Zef\Framework\Http\FormBodyParser;
use Zef\Framework\Http\JsonBodyParser;

/**
 * Grammar-overlapping strategy double: claims every media type either
 * of its members owns, and delegates the parse to the first member --
 * the overlapping grammar a host-built strategy table can carry, so the
 * table's first-declared-owner rule is observable.
 */
final class ClaimingParser implements BodyParserInterface
{
    public function __construct(
        private readonly JsonBodyParser $first,
        private readonly FormBodyParser $second,
    ) {
    }

    #[Override]
    public function supports(string $mediaType): bool
    {
        return $this->first->supports($mediaType) || $this->second->supports($mediaType);
    }

    #[Override]
    public function parse(string $body): ?array
    {
        return $this->first->parse($body);
    }
}
