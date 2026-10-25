<?php

declare(strict_types=1);

namespace Zef\Test\Event;

/**
 * The shared order log a test's listeners append their own tags
 * to -- the one ledger that can state which listener ran before
 * which across a whole dispatch.
 *
 * @internal
 */
final class EventLog
{
    /** @var list<string> */
    private array $tags = [];

    public function record(string $tag): void
    {
        $this->tags[] = $tag;
    }

    /**
     * The tags in the order they were recorded -- the dispatch's
     * own listener order, spelled by the listeners themselves.
     *
     * @return list<string>
     */
    public function tags(): array
    {
        return $this->tags;
    }
}
