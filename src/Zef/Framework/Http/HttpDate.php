<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use DateTimeImmutable;
use DateTimeZone;

/**
 * The HTTP-date grammar: parses the timestamp forms a conditional request
 * or a `Last-Modified` validator may legally carry.
 *
 * Two grammar shapes are accepted, in precedence order: the RFC 5322/1123
 * form with its zone designator (`Mon, 01 Jan 2024 10:00:00 GMT`) and the
 * lenient zone-less form (`Mon, 01 Jan 2024 10:00:00`) -- the lenient
 * shape is read in the constructor's GMT frame, exactly like the reference
 * implementation (legacy `zef-framework` `Adapters.Http.ETagMiddleware`).
 * A parse that carries engine warnings (a day of `32`, a second of `60`
 * -- the engine rolls them over instead of failing) is rejected as
 * invalid: a rolled-over timestamp is not the one either side sent.
 *
 * Deviation from the reference, engine-verified: the reference also
 * carried `l, ...` (full-day-name) variants of both shapes, but this
 * engine's `D` specifier already accepts full day names (`Monday`
 * parses through `D, d M Y H:i:s T` with no warnings), making the `l`
 * variants unreachable dead grammar -- and a redundancy no test could
 * distinguish. The dashed RFC 850 form (`Monday, 01-Jan-24 ...`) never
 * parsed through any of the reference's formats either (verified), so
 * nothing observable is lost by the two-shape grammar.
 *
 * The zone designator is honoured wherever present (an explicit offset
 * keeps its own frame); timestamps answer through `getTimestamp()`, which
 * is frame-independent, so no re-framing of the parsed value is needed.
 */
final class HttpDate
{
    /**
     * Parses an HTTP-date value, answering null when no grammar shape
     * accepts it cleanly.
     */
    public static function parse(string $value): ?DateTimeImmutable
    {
        foreach (['D, d M Y H:i:s T', 'D, d M Y H:i:s'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value, new DateTimeZone('GMT'));
            if ($date === false) {
                continue;
            }

            $warnings = DateTimeImmutable::getLastErrors();
            $warningCount = $warnings === false ? 0 : $warnings['warning_count'];
            if ($warningCount === 0) {
                return $date;
            }
        }

        return null;
    }

    /**
     * Renders a UTC instant in the HTTP-date spelling the grammar above
     * parses -- the IMF-fixdate form RFC 8594's `Sunset` examples use.
     */
    public static function render(int $instant): string
    {
        $date = new DateTimeImmutable('@' . $instant)->setTimezone(new DateTimeZone('GMT'));

        return $date->format('D, d M Y H:i:s') . ' GMT';
    }
}
