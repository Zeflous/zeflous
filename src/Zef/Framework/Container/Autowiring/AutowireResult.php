<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Zef\Framework\Container\ServiceDefinition;

/**
 * One compile-pass run's answer: the planned set plus the report of
 * what the run did -- the identifiers it generated (birthed classes,
 * now plain declared definitions under their own names), the ones it
 * reused (resolved through an existing declaration or registration
 * instead of a birth) and the value services it materialised (the
 * synthetic `@value:` identifiers a `#[Value]` address asked for, each
 * one shared value service the boot graph validates like any
 * dependency).
 *
 * The report is the lane's observability seam: the bootstrapper
 * consumes the definitions, tests and tooling consume the bookkeeping,
 * and a host reading "generated: Widget, Logger\FileLogger" knows
 * exactly which unregistered classes the declaration pulled into the
 * container -- nothing is birthed or materialised off the record.
 */
final readonly class AutowireResult
{
    /**
     * The report's own fields (generated, reused, values) are the
     * run's observability, read by the host's wiring and the test
     * battery -- the psalm baseline's unused verdict records that
     * boundary, the same report-surface posture the AOT outcome's
     * own fields carry.
     *
     * @param list<ServiceDefinition> $definitions the planned set: every
     *                                             declared definition (rewritten
     *                                             where the lane applies) plus
     *                                             the births and the materialised
     *                                             value services
     * @param list<string>            $generated   the classes the run birthed,
     *                                             in birth order
     * @param list<string>            $reused      the identifiers resolved
     *                                             without a birth, in resolution
     *                                             order
     * @param list<string>            $values      the synthetic `@value:`
     *                                             identifiers the run
     *                                             materialised, in materialisation
     *                                             order
     */
    public function __construct(
        public array $definitions,
        public array $generated,
        public array $reused,
        public array $values = [],
    ) {
    }

    /**
     * The run's answer from the planned set, the births bookkeeping and
     * the value lane's materialisations.
     *
     * @param list<ServiceDefinition> $planned the planned declared set
     */
    public static function of(
        array $planned,
        AutowireBirths $autowireBirths,
        AutowireValueServices $autowireValueServices,
    ): self {
        return new self(
            [...$planned, ...$autowireBirths->definitions(), ...$autowireValueServices->definitions()],
            $autowireBirths->generated(),
            $autowireBirths->reused(),
            $autowireValueServices->materialized(),
        );
    }
}
