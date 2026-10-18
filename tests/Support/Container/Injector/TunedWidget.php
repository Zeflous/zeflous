<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Injector;

/**
 * Fixture service for the method-call injection lane: a mutable object
 * whose setters record every injection in arrival order, so the tests
 * can pin both the values and the sequence. One setter is fluent (it
 * answers the instance itself) to pin that the lane discards the
 * method's answer, and one takes no argument at all (the warm-up form).
 */
final class TunedWidget
{
    /** @var list<string> */
    private array $log = [];

    private bool $warmedUp = false;

    public function __construct(
        public string $label,
    ) {
    }

    public function setVolume(int $volume): void
    {
        $this->log[] = 'volume:' . $volume;
    }

    public function addTag(string $tag): self
    {
        $this->log[] = 'tag:' . $tag;

        return $this;
    }

    public function warmUp(): void
    {
        $this->warmedUp = true;
        $this->log[] = 'warm';
    }

    public function adoptPeer(self $peer): void
    {
        $this->log[] = 'peer:' . $peer->label;
    }

    public function warmed(): bool
    {
        return $this->warmedUp;
    }

    /**
     * @return list<string>
     */
    public function log(): array
    {
        return $this->log;
    }
}
