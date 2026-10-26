<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

/**
 * What one delivery's preparation answered: the frozen event and
 * the ordered pool it will be delivered over -- the pair every
 * delivery verb reads together, carried as one value so the
 * dispatcher's vocabulary stays one-line verbs.
 */
final readonly class PreparedDelivery
{
    /**
     * @param list<ListenerSubscription> $pool the ordered delivery pool
     */
    public function __construct(
        private Event $event,
        private array $pool,
    ) {
    }

    /**
     * The frozen event -- the payload validated at the delivery
     * boundary, before anything was queued or delivered.
     */
    public function event(): Event
    {
        return $this->event;
    }

    /**
     * The ordered delivery pool -- the snapshot a synchronous
     * dispatch delivers over and a defer admits (KDD-EA3: the
     * dispatch-time subscription state IS the message).
     *
     * @return list<ListenerSubscription>
     */
    public function pool(): array
    {
        return $this->pool;
    }
}
