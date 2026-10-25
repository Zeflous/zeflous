<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;

/**
 * One field's demand within an event shape: the kind the field
 * must answer plus whether an explicit null may stand in for an
 * absent value. Parsed from the shape's spelling at registration
 * -- `int` demands an integer, `?int` additionally accepts null --
 * so the producer-facing grammar stays a single quoted word per
 * field instead of a parallel boolean vocabulary.
 */
final readonly class EventShapeField
{
    private const string NULLABLE_PREFIX = '?';

    private PayloadFieldType $payloadFieldType;

    private bool $nullable;

    /**
     * @param string $spelling the field's kind spelling: one of the
     *                         {@see PayloadFieldType} spellings, optionally prefixed
     *                         with `?` to let null stand in for the value
     *
     * @throws InvalidArgumentException when the spelling is not a
     *                                  kind the vocabulary carries
     */
    public function __construct(string $spelling)
    {
        $bare = $spelling;
        $nullable = false;

        if (str_starts_with($spelling, self::NULLABLE_PREFIX)) {
            $bare = mb_substr($spelling, mb_strlen(self::NULLABLE_PREFIX));
            $nullable = true;
        }

        $type = PayloadFieldType::tryFrom($bare);

        if ($type === null) {
            throw new InvalidArgumentException(
                'An event shape field must spell one of the payload kinds'
                . ' (string, int, float, bool, list), optionally prefixed with'
                . " '?' for a nullable field, got '{$spelling}'.",
            );
        }

        $this->payloadFieldType = $type;
        $this->nullable = $nullable;
    }

    /**
     * The kind the field demands, the enum the payload check
     * delegates to.
     */
    public function type(): PayloadFieldType
    {
        return $this->payloadFieldType;
    }

    /**
     * Whether an explicit null may stand in for the field's value
     * -- the `?` the spelling carried.
     */
    public function isNullable(): bool
    {
        return $this->nullable;
    }

    /**
     * Whether a candidate value satisfies the field: either it
     * answers the demanded kind, or the field is nullable and the
     * value is null.
     */
    public function accepts(mixed $value): bool
    {
        if ($value === null) {
            return $this->nullable;
        }

        return $this->payloadFieldType->accepts($value);
    }
}
