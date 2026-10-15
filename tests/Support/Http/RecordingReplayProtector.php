<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Override;
use Zef\Framework\Http\ReplayDecision;
use Zef\Framework\Http\ReplayProtectorInterface;

/**
 * Deterministic replay-protector double: records every check's replay
 * id and clock, and answers the configured decision -- the shape the
 * boundary's tests need to observe whether the protector was consulted
 * at all and which id it was handed.
 */
final class RecordingReplayProtector implements ReplayProtectorInterface
{
    /** @var list<array{null|string, int}> */
    public array $checks = [];

    public ReplayDecision $decision;

    public function __construct(?ReplayDecision $decision = null)
    {
        $this->decision = $decision ?? ReplayDecision::ACCEPT;
    }

    #[Override]
    public function check(?string $replayId, int $nowMs): ReplayDecision
    {
        $this->checks[] = [$replayId, $nowMs];

        return $this->decision;
    }
}
