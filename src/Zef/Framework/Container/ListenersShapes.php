<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The listeners knob's map-level shape proof: the knob itself must be
 * a map of the two known kinds -- not a positional list, not a scalar,
 * and no third kind -- the vocabulary one place owns so the map's
 * rejections report what *would* have been accepted (the
 * {@see DefinitionKeys} posture).
 */
final readonly class ListenersShapes
{
    /** The kinds the knob's map may carry. */
    private const array KINDS = ['resolving', 'resolved'];

    /**
     * The knob's shape proof, answering the narrowed map.
     *
     * @return array<array-key, mixed>
     */
    public static function checked(string $key, mixed $source): array
    {
        if (!\is_array($source)) {
            throw InterceptionRefusals::listenersNotAMap($key, $source);
        }

        if ($source !== [] && array_is_list($source)) {
            throw InterceptionRefusals::listenersNotAMap($key, $source);
        }

        $unknown = DefinitionKeys::unknownIn($source, self::KINDS);
        if ($unknown !== null) {
            throw InterceptionRefusals::unknownListenerKind($key, $unknown);
        }

        return $source;
    }
}
