<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\ConfigException;

/**
 * The uploads family's allowlist grammar: the strict reading of the
 * `uploads.types` knob -- a list of media types, each one the
 * lowercase canonical spelling the exact-match comparison keeps --
 * refused with the entry named otherwise, the
 * {@see EventDeliveryStores} lineage replayed for the type half of
 * the lane's first fail-closed validation.
 *
 * The grammar is deliberately narrow, the closed shape a media type
 * the tree's own comparisons already answer: lowercase alphanumerics
 * first on each side, then the same class plus `.` `+` `_` `-` (`image/png`,
 * `application/pdf`, `image/svg+xml`, `application/vnd.ms-excel`), exactly
 * one separating slash, no parameters -- a `text/plain; charset=utf-8`
 * spelling is a refusal, never a silently accepted string the
 * exact-match comparison would then carry verbatim against a client
 * that never sends it. An uppercase `IMAGE/PNG` entry is the same
 * refusal: the declared vocabulary owns the canonical spelling, and a
 * deployment that believes it accepts `IMAGE/PNG` while the client's
 * `image/png` never matches it is exactly the unnoticed typo the
 * strict census family exists to catch.
 *
 * A repeated entry rides verbatim -- the exact-match comparison is
 * repeat-invariant, so the fold never silently de-duplicates what
 * the deployment spelled. The empty list is the family's own silent
 * posture, allowed here and refused where the question belongs
 * ({@see UploadsStorageWiring}: a writer that accepts nothing is a
 * declaration the host never made).
 */
final class UploadsTypes
{
    /**
     * The media-type entry's own grammar: lowercase alphanumerics
     * first on each side, then the same class plus the registered
     * subtype punctuation, one separating slash.
     */
    private const string ENTRY = '#^[a-z0-9][a-z0-9.+_-]*/[a-z0-9][a-z0-9.+_-]*$#D';

    /**
     * Reads the declared allowlist, refusing every entry the grammar
     * above names.
     *
     * @param mixed $types the `uploads.types` knob's value as the
     *                     frozen repository carries it: a list, or null when the
     *                     knob is absent (the silent posture's empty answer)
     *
     * @return list<non-empty-string> the declared allowlist, in
     *                                declaration order
     *
     * @throws ConfigException when the value is neither a list nor
     *                         null, or an entry is not a string or refuses the
     *                         grammar
     */
    public static function read(mixed $types): array
    {
        if ($types === null) {
            return [];
        }

        if (!\is_array($types)) {
            throw new ConfigException(
                'uploads.types must be a list of media types, ' . get_debug_type($types) . ' given.',
            );
        }

        $validated = [];
        foreach ($types as $type) {
            if (!\is_string($type) || $type === '' || preg_match(self::ENTRY, $type) !== 1) {
                throw new ConfigException(
                    'uploads.types must carry lowercase media types (type/subtype), '
                    . self::described($type) . ' given.',
                );
            }

            $validated[] = $type;
        }

        return $validated;
    }

    /**
     * The entry's own description for the refusal's message: the
     * string in quotes, or the debug type a non-string carries.
     */
    private static function described(mixed $type): string
    {
        if (\is_string($type)) {
            return "'{$type}'";
        }

        return get_debug_type($type);
    }
}
