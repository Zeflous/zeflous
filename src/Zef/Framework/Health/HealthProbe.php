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
 * observation about the dependency it owns -- a probe past the
 * scrape's deadline is skipped, `down` with the skip marker, and a
 * probe that ANSWERS past the deadline is itself `down` with the
 * overrun marker (issue #788, finding 3): the answer the deadline
 * can no longer vouch for -- the response is already over budget
 * while it ran, and the probe that ate the whole budget is the
 * reason the scrape degrades. The boolean seam carries no message,
 * so a probe that is simply down answers the empty message -- the
 * seam's own honest shape.
 */
final class HealthProbe
{
    /**
     * Judges one probe under the scrape's overall deadline: the
     * deadline read first (a probe at the deadline is skipped -- the
     * boundary is inclusive), then the probe itself, contained, and
     * the deadline read again after the answer -- a probe that
     * returns past the deadline is the overrun the scrape's own
     * budget cannot vouch for.
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

        $before = $deadline === null ? null : $healthScrapeClock->now();

        if ($before !== null && $before >= $deadline) {
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

        $after = $deadline === null ? null : $healthScrapeClock->now();

        if ($after !== null && $after >= $deadline) {
            return [
                'name' => $name,
                'status' => HealthStatuses::STATUS_DOWN,
                'message' => 'overran the scrape deadline: the answer the deadline can no longer vouch for',
            ];
        }

        return [
            'name' => $name,
            'status' => $healthy ? HealthStatuses::STATUS_UP : HealthStatuses::STATUS_DOWN,
            'message' => '',
        ];
    }
}
