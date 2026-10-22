<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use DateTimeImmutable;
use DateTimeZone;
use Zef\Framework\Config\ConfigException;

/**
 * Canonicalises one route's deprecation declaration -- the RFC 9745
 * `Deprecation` date, the RFC 8594 `Sunset` date and the deprecation
 * documentation link a deprecated route answers with on every response
 * (`ROADMAP.md`, "API Versioning": the deprecation middleware's declaration
 * half, declared per route and rendered by
 * {@see \Zef\Framework\Middleware\RouteDeprecationHeaders}).
 *
 * The declaration grammar is deliberately narrower than the headers it
 * feeds: a calendar date (`2024-06-30`, read as that day's midnight UTC)
 * or a full ISO-8601 instant with an explicit zone designator
 * (`2024-06-30T12:00:00Z`, `2024-06-30T14:00:00+02:00` -- an offset keeps
 * its own frame, exactly like {@see \Zef\Framework\Http\HttpDate} honours
 * a zone designator). A timestamp without a zone is ambiguous, so it is
 * refused rather than guessed; a parse the engine would roll over (a day
 * of `32`, `2024-02-30`) is rejected with the same warning check
 * {@see \Zef\Framework\Http\HttpDate} applies, because a rolled-over date
 * is not the one the declaration named.
 *
 * `Sunset` must name an instant strictly after the deprecation date: a
 * resource that retires before (or exactly when) it is deprecated is a
 * retirement, not a deprecation -- the refusal is the declaration-line
 * judgement, not a runtime surprise.
 *
 * The link is a URI-reference rendered into an RFC 8288 `Link` field value
 * (`<...>; rel="deprecation"`, the relation RFC 9745 registers), so a value
 * carrying whitespace, control bytes, or the angle brackets the field's own
 * syntax reserves is refused on the declaration line: it could never
 * render as the link it claims to be. A relative reference is accepted --
 * RFC 8288 allows URI-references as link targets -- but the reserved bytes
 * are not the link's to spend.
 *
 * @internal
 */
final class RouteDeprecation
{
    /**
     * The declaration members a deprecation may carry: the required
     * `date`, and the optional `sunset` and `link`.
     */
    private const array MEMBERS = [
        'date' => true,
        'sunset' => true,
        'link' => true,
    ];

    /**
     * Canonicalises a shape-checked deprecation declaration into the
     * compiled field the dispatch lane renders from.
     *
     * @param array{
     *     date: non-empty-string,
     *     sunset?: non-empty-string,
     *     link?: non-empty-string,
     * } $declaration the shape-checked declaration
     * @param RoutePath $routePath the route the declaration belongs to, for the failure message
     *
     * @return array{date: int, sunset: null|int, link: null|non-empty-string}
     *
     * @throws ConfigException when a member's grammar is refused
     */
    public static function canonical(array $declaration, RoutePath $routePath): array
    {
        $date = self::instant($declaration['date'], 'date', $routePath);

        $sunset = null;
        if (\array_key_exists('sunset', $declaration)) {
            $sunset = self::instant($declaration['sunset'], 'sunset', $routePath);

            if ($sunset <= $date) {
                throw new ConfigException(\sprintf(
                    'The route "%s" declares a sunset at or before its deprecation date.'
                    . ' A resource that retires when it is deprecated is a retirement, not a deprecation.',
                    $routePath->pattern(),
                ));
            }
        }

        $link = null;
        if (\array_key_exists('link', $declaration)) {
            $link = self::link($declaration['link'], $routePath);
        }

        return ['date' => $date, 'sunset' => $sunset, 'link' => $link];
    }

    /**
     * The members a deprecation declaration may carry, keyed by name; the
     * shape side ({@see RouteEntryShape}) refuses any other key against
     * this same vocabulary.
     *
     * @return array<non-empty-string, true>
     */
    public static function members(): array
    {
        return self::MEMBERS;
    }

    /**
     * One declaration member's instant: the calendar-date grammar (that
     * day's midnight UTC) or the full ISO-8601 grammar with an explicit
     * zone designator, warning-checked so a rolled-over date is refused.
     *
     * @param non-empty-string $value     the declared date member
     * @param non-empty-string $member    the member's name, for the failure message
     * @param RoutePath        $routePath the route the declaration belongs to
     *
     * @return int the UTC instant the declaration names
     *
     * @throws ConfigException when neither grammar accepts the value cleanly
     */
    private static function instant(string $value, string $member, RoutePath $routePath): int
    {
        foreach (['Y-m-d', 'Y-m-d\TH:i:sP'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value, new DateTimeZone('UTC'));

            if ($date === false) {
                continue;
            }

            $errors = DateTimeImmutable::getLastErrors();
            $warningCount = $errors === false ? 0 : $errors['warning_count'];

            if ($warningCount !== 0) {
                continue;
            }

            // The calendar-date grammar names a day, not an instant: the
            // engine fills the unspecified time components with the
            // process's current wall-clock, so the day is pinned to its
            // midnight before the instant is answered -- the declaration
            // stays deterministic whatever hour it compiled at.
            if ($format === 'Y-m-d') {
                $date = $date->setTime(0, 0, 0);
            }

            return $date->getTimestamp();
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
     * One declaration's documentation link: a URI-reference that renders
     * into an RFC 8288 field value, so the bytes that syntax reserves
     * (whitespace, control bytes, the angle brackets) are refused here.
     *
     * @param non-empty-string $value the declared link member
     *
     * @return non-empty-string
     *
     * @throws ConfigException when the value cannot render as a link target
     */
    private static function link(string $value, RoutePath $routePath): string
    {
        if (preg_match('/[\x00-\x20<>]/', $value) === 1) {
            throw new ConfigException(\sprintf(
                'The route "%s" declares a deprecation "link" that cannot render as a link target:'
                . ' it carries whitespace, a control byte, or an angle bracket ("%s").',
                $routePath->pattern(),
                $value,
            ));
        }

        return $value;
    }
}
