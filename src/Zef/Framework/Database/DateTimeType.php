<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Throwable;

/**
 * The datetime column's converter: {@see DateTimeImmutable} in, the
 * driver's canonical datetime spelling out; the stored spelling in,
 * the immutable object back -- the one place the spelling converts,
 * in both directions, deterministically.
 *
 * THE ZONE POSTURE (the design record's open edge, confirmed here in
 * writing): UTC-canonical at the boundary. The write direction
 * converts the instant to UTC wall time before it formats, and the
 * read direction anchors the offset-free stored spelling AS UTC --
 * because a boundary that stores "whatever zone the writer was in" is
 * a cross-timezone lie, and a reader that guesses the server's zone
 * is the same lie read back ({@see DateTimeImmutable} in, the equal
 * instant out, both sides pinned). A stored spelling that carries its
 * own offset still wins: the anchor applies only to offset-free
 * spellings, so a foreign writer's explicit zone travels honestly.
 *
 * The canonical spelling carries WHOLE SECONDS (`Y-m-d H:i:s`): the
 * spelling the driver's own CURRENT_TIMESTAMP answers, the reference's
 * own default. Microseconds are the DATETIME(6) question -- a named
 * follow-up, not a silent truncation a converter would perform
 * without the column's own precision ever spelling it.
 *
 * @api
 */
final class DateTimeType implements ColumnType
{
    /**
     * The whole-second canonical spelling the driver's own clock
     * answers -- the format every boundary cell travels in.
     */
    private const string CANONICAL = 'Y-m-d H:i:s';

    #[Override]
    public function toDatabase(mixed $value): string
    {
        if (!$value instanceof DateTimeImmutable) {
            throw new TypeMappingException(
                "The datetime column's write value must be DateTimeImmutable (got "
                . get_debug_type($value) . ') -- the immutable idiom is non-negotiable at a value boundary.',
            );
        }

        return $value->setTimezone(new DateTimeZone('UTC'))->format(self::CANONICAL);
    }

    #[Override]
    public function toPhp(mixed $value): mixed
    {
        if (!\is_string($value)) {
            throw new TypeMappingException(
                "The datetime column's stored cell must be a string (got "
                . get_debug_type($value) . ') -- the driver answers the canonical spelling as text.',
            );
        }

        try {
            return new DateTimeImmutable($value, new DateTimeZone('UTC'));
        } catch (Throwable $throwable) {
            throw new TypeMappingException(
                "The datetime column's stored cell '{$value}' is not a spelling the immutable clock reads.",
                0,
                $throwable,
            );
        }
    }
}
