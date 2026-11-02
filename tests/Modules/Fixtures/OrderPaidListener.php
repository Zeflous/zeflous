<?php

declare(strict_types=1);

namespace Zef\Test\Modules\Fixtures;

use Override;
use Zef\Framework\Event\Event;
use Zef\Framework\Event\EventListener;

/**
 * The module-declared listener: records every event it handles onto the
 * fixture log -- the wired dispatcher's delivery evidence, the same tape
 * the boot phases write.
 */
final class OrderPaidListener implements EventListener
{
    #[Override]
    public function handle(Event $event): void
    {
        FixtureLog::record(
            'listener',
            $event->name()->spelling() . '@' . json_encode(
                $event->payload()->values(),
                \JSON_UNESCAPED_SLASHES,
            ),
        );
    }
}
