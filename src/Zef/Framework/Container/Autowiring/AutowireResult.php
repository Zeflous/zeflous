<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Zef\Framework\Container\ServiceDefinition;

/**
 * One compile-pass run's answer: the planned set plus the report of
 * what the run did -- the identifiers it generated (birthed classes,
 * now plain declared definitions under their own names) and the ones it
 * reused (resolved through an existing declaration or registration
 * instead of a birth).
 *
 * The report is the lane's observability seam: the bootstrapper
 * consumes the definitions, tests and tooling consume the bookkeeping,
 * and a host reading "generated: Widget, Logger\FileLogger" knows
 * exactly which unregistered classes the declaration pulled into the
 * container -- nothing is birthed off the record.
 */
final readonly class AutowireResult
{
    /**
     * @param list<ServiceDefinition> $definitions the planned set: every
     *                                             declared definition (rewritten
     *                                             where the lane applies) plus
     *                                             the births
     * @param list<string>            $generated   the classes the run birthed,
     *                                             in birth order
     * @param list<string>            $reused      the identifiers resolved
     *                                             without a birth, in resolution
     *                                             order
     */
    public function __construct(
        public array $definitions,
        public array $generated,
        public array $reused,
    ) {
    }

    /**
     * The run's answer from the planned set and the births bookkeeping.
     *
     * @param list<ServiceDefinition> $planned the planned declared set
     */
    public static function of(array $planned, AutowireBirths $autowireBirths): self
    {
        return new self(
            [...$planned, ...$autowireBirths->definitions()],
            $autowireBirths->generated(),
            $autowireBirths->reused(),
        );
    }
}
