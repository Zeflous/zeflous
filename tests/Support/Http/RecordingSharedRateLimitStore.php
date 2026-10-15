<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Override;
use Zef\Framework\Http\SharedRateLimitStoreInterface;

/**
 * Recording shared-store double: answers the configured bucket for every
 * increment call.
 */
final class RecordingSharedRateLimitStore implements SharedRateLimitStoreInterface
{
    /** @var list<array{string, int, int}> */
    public array $increments = [];

    /** @param array{count:int, reset:int} $bucket */
    public function __construct(private readonly array $bucket)
    {
    }

    #[Override]
    public function increment(string $key, int $windowSeconds, int $now): array
    {
        $this->increments[] = [$key, $windowSeconds, $now];

        return $this->bucket;
    }
}
