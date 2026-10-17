<?php

declare(strict_types=1);

namespace Zef\Framework\Health;

use Throwable;
use Zef\Framework\Contracts\HealthCheckInterface;

/**
 * The single-probe judgement: one probe's outcome normalised into
 * its check record -- the unit the {@see HealthAggregator} reads per
 * probe, extracted so the scrape's status math and the probe's own
 * containment stay one named place each.
 *
 * THE CONTAINMENT IS THE POINT: a crashing probe degrades to `down`
 * with the failure's class named -- a probe that throws is itself an
 * observation about the dependency it owns -- and a probe past the
 * scrape's deadline is skipped, `down` with the skip marker. The
 * boolean seam carries no message, so a probe that is simply down
 * answers the empty message -- the seam's own honest shape.
 */
final class HealthProbe
{
    /**
     * Judges one probe under the scrape's overall deadline: the
     * deadline read first (a probe at the deadline is skipped -- the
     * boundary is inclusive), then the probe itself, contained.
     *
     * @param HealthCheckInterface $healthCheck       the probe to judge
     * @param null|int             $deadline          the scrape's wall-clock deadline, nanoseconds
     * @param HealthScrapeClock    $healthScrapeClock the scrape's own clock
     *
     * @return array{name:string,status:string,message:string}
     */
    public static function judged(
        HealthCheckInterface $healthCheck,
        ?int $deadline,
        HealthScrapeClock $healthScrapeClock,
    ): array {
        $name = ProbeName::sanitized($healthCheck->name());

        if ($deadline !== null && $healthScrapeClock->now() >= $deadline) {
            return [
                'name' => $name,
                'status' => HealthStatuses::STATUS_DOWN,
                'message' => 'skipped: overall health deadline exceeded',
            ];
        }

        try {
            $healthy = $healthCheck->isHealthy();
        } catch (Throwable $throwable) {
            return [
                'name' => $name,
                'status' => HealthStatuses::STATUS_DOWN,
                'message' => 'probe failure: ' . mb_substr($throwable::class, 0, 128),
            ];
        }

        return [
            'name' => $name,
            'status' => $healthy ? HealthStatuses::STATUS_UP : HealthStatuses::STATUS_DOWN,
            'message' => '',
        ];
    }
}
