<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Override;
use Zef\Framework\Http\ApcuCounterStoreInterface;

/**
 * Deterministic APCu counter-store double: records every primitive call
 * and answers configurable counter values, with the reset-epoch claim
 * honouring first-call-wins per key (what `apcu_add` semantics give the
 * real store).
 */
final class RecordingApcuCounterStore implements ApcuCounterStoreInterface
{
    /** @var list<array{string, int, int}> */
    public array $claims = [];

    /** @var list<array{string, int}> */
    public array $increments = [];

    public int $count = 0;

    /** @var array<string, int> */
    public array $claimedEpochs = [];

    #[Override]
    public function claimResetEpoch(string $key, int $epoch, int $ttl): int
    {
        $this->claims[] = [$key, $epoch, $ttl];

        return $this->claimedEpochs[$key] ??= $epoch;
    }

    #[Override]
    public function increment(string $key, int $ttl): int
    {
        $this->increments[] = [$key, $ttl];

        return ++$this->count;
    }
}
