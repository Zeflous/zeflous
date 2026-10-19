<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Aot;

use Zef\Framework\Container\DefinitionKeys;

/**
 * The strict boolean reading the AOT lane's two toggles pass through: the
 * closed vocabulary the knob's values fold into.
 *
 * A native bool passes through untouched (a file or the default layer
 * declared it); a string -- the raw spelling an environment overlay
 * writes, {@see \Zef\Framework\Config\Merge\EnvSource} passing values
 * through as the environment carries them -- folds through the Unicode
 * case map into the closed vocabulary (`1`/`true`/`on`/`yes` and their
 * negations, case-insensitive) and answers its boolean; everything else
 * is rejected with the knob named, the same fail-closed discipline the
 * security families' toggles carry ({@see \Zef\Framework\Http\StrictToggles}
 * -- the legacy `readBoolStrict` lineage).
 *
 * The vocabulary is re-declared here rather than shared through the HTTP
 * seam because the layer graph keeps it unreachable: the container layer
 * may depend only on the contracts and exception roots, and widening the
 * deptrac ruleset for a spelling list is a heavier trade than carrying
 * the words twice (the KDD records the decision). The fold is
 * value-equivalent under the MBString case-fold mutator on this method by
 * construction, the same argument the security seam makes: the only
 * single-character lowercase landing in ASCII the Unicode case map
 * carries is U+212A to `k`, and no vocabulary word contains a `k`, so
 * every fold-divergent value is rejected under both spellings.
 */
final class AotToggles
{
    /**
     * The strict boolean vocabulary's truth spellings, comparison form.
     */
    private const array TRUE_WORDS = ['1', 'true', 'on', 'yes'];

    /**
     * The strict boolean vocabulary's falsehood spellings, comparison form
     * (the empty string included: an explicitly empty env value reads as
     * an explicit off, not as an unset knob).
     */
    private const array FALSE_WORDS = ['0', 'false', 'off', 'no', ''];

    /**
     * The knob map's two answers, read under the grammar's guards: a
     * non-map or list knob refuses, an unknown key refuses with the
     * known list, and each present toggle folds through the closed
     * vocabulary under its family-qualified name -- an absent toggle is
     * the off default.
     *
     * @param mixed $source the raw `aot` value, null already excluded by
     *                      the caller (null is the everything-off default
     *                      the parsed form answers directly)
     *
     * @return array{bool, bool} the enabled and warm answers
     */
    public static function pair(string $key, mixed $source): array
    {
        if (!\is_array($source)) {
            throw AotRefusals::notAMap($key, $source);
        }

        if ($source !== [] && array_is_list($source)) {
            throw AotRefusals::mapRequired($key);
        }

        $unknown = DefinitionKeys::unknownIn($source, ['enabled', 'warm']);
        if ($unknown !== null) {
            throw AotRefusals::unknownKey($key, $unknown);
        }

        return [
            isset($source['enabled']) && self::read($source['enabled'], $key . '.enabled'),
            isset($source['warm']) && self::read($source['warm'], $key . '.warm'),
        ];
    }

    /**
     * Reads one AOT toggle: native bool passthrough, strict string fold,
     * rejection with the knob named otherwise.
     *
     * @param string $knob the toggle's family-qualified name, as the
     *                     rejection spells it (`aot.enabled`)
     */
    private static function read(mixed $value, string $knob): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (\is_string($value)) {
            $folded = mb_strtolower($value);

            if (\in_array($folded, self::TRUE_WORDS, true)) {
                return true;
            }

            if (\in_array($folded, self::FALSE_WORDS, true)) {
                return false;
            }
        }

        throw AotRefusals::notAToggle($knob, $value);
    }
}
