<?php

declare(strict_types=1);

namespace Zef\Framework\Health;

use Throwable;

/**
 * The probe judgement's own record vocabulary: the four check
 * records the {@see HealthProbe} judgement answers -- the skipped
 * probe at the deadline, the crash contained with the failure's
 * class named, the overrun the post-answer clock read judges, and
 * the verdict the budget can still vouch for -- each record one
 * named builder, the judgement's arithmetic and the record's shape
 * one named place each.
 */
final class HealthVerdicts
{
    /**
     * The skipped record: the probe at the deadline, the boundary
     * inclusive, the marker the exposition carries.
     *
     * @return array{name:string,status:string,message:string}
     */
    public static function skipped(string $name): array
    {
        return [
            'name' => $name,
            'status' => HealthStatuses::STATUS_DOWN,
            'message' => 'skipped: overall health deadline exceeded',
        ];
    }

    /**
     * The crash contained: a probe that throws is itself an
     * observation about the dependency it owns, the failure's class
     * the diagnostic the record carries.
     *
     * @return array{name:string,status:string,message:string}
     */
    public static function probeFailure(string $name, Throwable $throwable): array
    {
        return [
            'name' => $name,
            'status' => HealthStatuses::STATUS_DOWN,
            'message' => 'probe failure: ' . mb_substr($throwable::class, 0, 128),
        ];
    }

    /**
     * The overrun record: the probe that answered past the deadline
     * -- the answer the deadline can no longer vouch for, the probe
     * that ate the budget the reason the scrape degrades.
     *
     * @return array{name:string,status:string,message:string}
     */
    public static function overran(string $name): array
    {
        return [
            'name' => $name,
            'status' => HealthStatuses::STATUS_DOWN,
            'message' => 'overran the scrape deadline: the answer the deadline can no longer vouch for',
        ];
    }

    /**
     * The verdict the budget can still vouch for: the probe's own
     * up or down when it answered inside the deadline, the overrun
     * record when the answer landed at or past it -- the answer the
     * deadline can no longer vouch for, the probe that ate the
     * budget the reason the scrape degrades.
     *
     * @return array{name:string,status:string,message:string}
     */
    public static function answered(string $name, bool $healthy, bool $overran): array
    {
        if ($overran) {
            return self::overran($name);
        }

        return [
            'name' => $name,
            'status' => $healthy ? HealthStatuses::STATUS_UP : HealthStatuses::STATUS_DOWN,
            'message' => '',
        ];
    }
}
