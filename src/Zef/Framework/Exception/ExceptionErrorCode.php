<?php

declare(strict_types=1);

namespace Zef\Framework\Exception;

/**
 * The default error code a {@see ZefException} subclass earns from
 * its own class name -- the stable machine-readable identity the
 * exception carries when no explicit code was spelled.
 *
 * The named spelling is the class's short name minus its `Exception`
 * suffix, folded to the uppercase snake grammar the code census
 * accepts (camel boundaries become underscores, anything the grammar
 * refuses becomes an underscore, runs collapse, the frame trims, a
 * result that still would not start a letter answers the
 * `FRAMEWORK_ERROR` fallback, and the whole never exceeds 64 bytes).
 *
 * The anonymous spelling is one digest over the stable identity the
 * engine's anonymous name carries -- the parent it extends and the
 * name's own tail (the file's basename, the declaration line, and
 * the per-file instance counter). The absolute path the full name
 * embeds is deployment layout, never identity (the same tree checked
 * out elsewhere answers the same code), the digest never truncates
 * into the collisions a 64-byte slice of the pathed spelling once
 * invited, and two sibling files declaring at the same line answer
 * different codes because their basenames differ.
 */
final class ExceptionErrorCode
{
    /** The fallback code a name that folds to nothing answers. */
    private const string FRAMEWORK_ERROR = 'FRAMEWORK_ERROR';

    /** The code ceiling: the census accepts at most 64 bytes. */
    private const int CODE_LENGTH = 64;

    /**
     * The default error code for one exception class name.
     */
    public static function for(string $class): string
    {
        if (str_contains($class, '@anonymous')) {
            return self::anonymous($class);
        }

        $separator = mb_strrpos($class, '\\');
        $shortName = $separator === false ? $class : mb_substr($class, $separator + 1);
        $shortName = (string) preg_replace('/Exception$/', '', $shortName);

        $snake = preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName);
        $normalized = mb_strtoupper((string) preg_replace('/[^A-Z0-9_]/', '_', (string) $snake));
        $normalized = mb_trim((string) preg_replace('/_+/', '_', $normalized), '_');

        if ($normalized === '' || preg_match('/^[A-Z]/D', $normalized) !== 1) {
            $normalized = self::FRAMEWORK_ERROR;
        }

        // @infection-ignore-all The fold's alphabet is ASCII by its own
        // grammar -- the census above refused every byte outside
        // [A-Z0-9_] -- so the ceiling slice and the trailing trim agree
        // between the byte and the character spelling on every string
        // the fold can hand them; a fold-divergent slice never exists.
        return mb_rtrim(mb_substr($normalized, 0, self::CODE_LENGTH), '_');
    }

    /**
     * The anonymous class's own code: one digest over the stable
     * identity the engine's anonymous name carries.
     *
     * @param non-empty-string $class the engine's `Parent@anonymous/...:line$counter` spelling
     */
    private static function anonymous(string $class): string
    {
        $parent = mb_strstr($class, '@anonymous', true);
        $parent = \is_string($parent) && $parent !== '' ? $parent : 'class';

        $tail = (string) mb_strrchr($class, '/');
        $tail = $tail === '' ? $class : mb_substr($tail, 1);

        return 'ANONYMOUS_' . mb_strtoupper(hash('crc32b', $parent . '|' . $tail));
    }
}
