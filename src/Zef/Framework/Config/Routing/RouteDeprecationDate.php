<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use DateTimeImmutable;
use DateTimeZone;
use Zef\Framework\Config\ConfigException;

/**
 * The deprecation declaration's date grammar: one question -- which UTC
 * instant does a declared date member name?
 *
 * Two grammar shapes are accepted, in precedence order: the calendar date
 * (`2024-06-30`, read as that day's midnight UTC) and the full ISO-8601
 * instant with an explicit zone designator (`2024-06-30T12:00:00Z`,
 * `2024-06-30T14:00:00+02:00` -- an offset keeps its own frame, exactly
 * like {@see \Zef\Framework\Http\HttpDate} honours a zone designator). A
 * timestamp without a zone is ambiguous, so it is refused rather than
 * guessed; a parse the engine would roll over (a day of `32`,
 * `2024-02-30`, a second of `60`) is rejected with the same warning check
 * {@see \Zef\Framework\Http\HttpDate} applies, because a rolled-over date
 * is not the one the declaration named.
 *
 * @internal
 */
final class RouteDeprecationDate
{
    /**
     * One declared date member's instant, refused when neither grammar
     * accepts the value cleanly.
     *
     * @param non-empty-string $value  the declared date member
     * @param non-empty-string $member the member's name, for the failure message
     *
     * @return int the UTC instant the declaration names
     *
     * @throws ConfigException when neither grammar accepts the value cleanly
     */
    public static function instant(string $value, string $member, RoutePath $routePath): int
    {
        foreach (['Y-m-d', 'Y-m-d\TH:i:sP'] as $format) {
            $timestamp = self::parsedInstant($format, $value);

            if ($timestamp === null) {
                continue;
            }

            return $timestamp;
        }

        throw new ConfigException(\sprintf(
            'The route "%s" declares a deprecation "%s" of "%s" that is neither a calendar date'
            . " (2024-06-30, read as that day's midnight UTC) nor a full date-time with an explicit"
            . ' zone designator (2024-06-30T12:00:00Z or 2024-06-30T14:00:00+02:00).',
            $routePath->pattern(),
            $member,
            $value,
        ));
    }

    /**
     * One grammar's instant, or null when the grammar does not accept the
     * value cleanly: a parse that fails, or a parse the engine would roll
     * over, is not the instant the declaration named.
     *
     * @param non-empty-string $format the grammar's date format
     * @param non-empty-string $value  the declared date member
     */
    private static function parsedInstant(string $format, string $value): ?int
    {
        $date = DateTimeImmutable::createFromFormat($format, $value, new DateTimeZone('UTC'));

        if ($date === false) {
            return null;
        }

        $errors = DateTimeImmutable::getLastErrors();

        if ($errors !== false && $errors['warning_count'] !== 0) {
            return null;
        }

        if ($format === 'Y-m-d') {
            // The calendar-date grammar names a day, not an instant: the
            // engine fills the unspecified time components with the
            // process's current wall-clock, so the day is pinned to its
            // midnight (the second component defaults to zero) before the
            // instant is answered -- the declaration stays deterministic
            // whatever hour it compiled at.
            $date = $date->setTime(0, 0);
        }

        return $date->getTimestamp();
    }
}
