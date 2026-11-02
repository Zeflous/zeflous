<?php

declare(strict_types=1);

namespace Zef\Test\Modules\Fixtures;

use Override;
use Zef\Framework\Event\Event;
use Zef\Framework\Event\EventListener;

/**
 * A listener whose evidence is its own tag: every delivery writes the
 * tag onto the fixture log in running order, so a dispatch involving
 * several listeners answers the standing question -- who ran first --
 * through the one tape. The wired declaration's standing and a runtime
 * subscription's own priority meet over this object's recordings.
 *
 * @internal
 */
final readonly class StandingListener implements EventListener
{
    public function __construct(
        private string $tag,
    ) {
    }

    #[Override]
    public function handle(Event $event): void
    {
        FixtureLog::record('listener', $this->tag);
    }
}
