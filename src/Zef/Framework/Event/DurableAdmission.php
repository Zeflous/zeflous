<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;

/**
 * The durable arm's admission fold: what one durable defer admits
 * -- the pool snapshot filtered by the quarantine, one serialized
 * entry per surviving listener in pool order, saved to the store
 * the host spelled. The mirror of the in-process
 * {@see DeliveryAdmission}, over the vocabulary that survives the
 * process boundary.
 *
 * The identity demand is this fold's own honesty rule: EVERY pool
 * listener must be a {@see DurableEventListener}, because a
 * durable record can only spell what a listener declared. A pool
 * that carries an anonymous listener is refused at the defer call
 * with the class named -- the in-process arm stays available for
 * exactly that pool, and the refusal is the vocabulary telling the
 * producer which arm is which, never a silent downgrade that
 * would promise a delivery no later process could resolve.
 *
 * The pool is the dispatcher's own snapshot (KDD-EA3, carried
 * over): this fold never re-reads the subscription state, so a
 * late subscriber cannot join and an unsubscribe cannot retract.
 * The quarantine filter is forward-looking containment
 * (KDD-EA7), process-local by declaration -- the durable
 * quarantine is the named follow-up this count is the seam for.
 */
final readonly class DurableAdmission
{
    public function __construct(
        private DeferredDeliveryStore $deferredDeliveryStore,
        private ListenerQuarantine $listenerQuarantine,
    ) {
    }

    /**
     * Admits one prepared delivery durably and answers the outcome.
     *
     * @throws InvalidArgumentException when the pool carries a
     *                                  listener without a durable identity, or the store refuses
     *                                  the batch (the backpressure refusal -- drain before
     *                                  deferring more)
     */
    public function admit(PreparedDelivery $preparedDelivery): DeferralOutcome
    {
        $envelopes = [];
        $skippedQuarantined = 0;

        foreach ($preparedDelivery->pool() as $listenerSubscription) {
            $eventListener = $listenerSubscription->listener();

            if ($this->listenerQuarantine->isQuarantined($eventListener)) {
                ++$skippedQuarantined;

                continue;
            }

            if (!$eventListener instanceof DurableEventListener) {
                throw new InvalidArgumentException(
                    'The delivery pool carries a listener without a durable'
                    . ' identity: ' . $eventListener::class . ' -- defer it'
                    . ' in-process, or spell its identity, but a durable'
                    . ' defer can never promise a delivery no later process'
                    . ' could resolve.',
                );
            }

            $envelopes[] = new DeliveryEnvelope(
                DeliveryEntryId::mint(),
                $preparedDelivery->event()->name(),
                $preparedDelivery->event()->payload()->values(),
                $eventListener->listenerIdentity(),
                1,
                0,
            );
        }

        $this->deferredDeliveryStore->save(...$envelopes);

        return new DeferralOutcome(
            $preparedDelivery->event()->name(),
            \count($envelopes),
            $skippedQuarantined,
        );
    }
}
