<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use Psr\Log\LoggerInterface;
use Zef\Framework\Database\QueryLogPolicy;
use Zef\Framework\Telemetry\MeterInterface;

/**
 * The query-log family's arming fold: the bracket a declaration's
 * policy arms over the shared sinks -- or the honest void the unarmed
 * declaration answers. One question, one fold: the composition the
 * wiring hands the connection, spelled where the family's own objects
 * live ({@see PdoRows} and {@see PdoIdentity}, the adapter's
 * single-verb folds' own lineage).
 *
 * The null answers are the family's own doctrines: the absent or
 * disarmed block (the policy's own arm question), and the readerless
 * armed block (both sinks null -- a measurement no consumer would ever
 * see, the timing family's "no observable difference between
 * measured-and-discarded and never-measured" discipline).
 *
 * @internal
 */
final class PdoQueryLogArming
{
    /**
     * Answers the bracket the declaration arms: the policy composed
     * over the shared meter and the one log address, or null.
     *
     * @param null|QueryLogPolicy  $queryLogPolicy the family's knob set, or
     *                                             null when the block stayed
     *                                             silent
     * @param null|MeterInterface  $meter          the shared metrics seam
     * @param null|LoggerInterface $logger         the shared record seam
     *
     * @return null|PdoQueryLog the bracket the connection holds, or
     *                          null when the family stays unobserved
     */
    public static function bracket(
        ?QueryLogPolicy $queryLogPolicy,
        ?MeterInterface $meter,
        ?LoggerInterface $logger,
    ): ?PdoQueryLog {
        if (
            !$queryLogPolicy instanceof QueryLogPolicy
            || !$queryLogPolicy->enabled
            || (!$meter instanceof MeterInterface && !$logger instanceof LoggerInterface)
        ) {
            return null;
        }

        return new PdoQueryLog($queryLogPolicy, $meter, $logger);
    }
}
