<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The strict boolean reading one knob's value passes through: the closed
 * vocabulary every config-driven family's toggles fold into.
 *
 * A native bool passes through untouched (a file or the default layer
 * declared it); a string -- the raw spelling an environment overlay
 * writes, `EnvSource` passing values through as the environment carries
 * them -- folds through the Unicode case map into the closed vocabulary
 * (`1`/`true`/`on`/`yes` and their negations, case-insensitive) and
 * answers its boolean; everything else is rejected with the knob named:
 * a `maybe` or a mistyped `ture` halts the boot rather than silently
 * reading as false, because a silently-mistyped security knob is a
 * protection the operator believes is on while it is not. This is the
 * legacy `zef-framework` `app/Bootstrap.php` `readBoolStrict` lineage,
 * extracted from the security-headers policy so every later family reads
 * its toggles through the same seam instead of re-declaring the
 * vocabulary.
 *
 * The fold is value-equivalent under the MBString mutator on this method
 * by construction (KDD-SC8, `docs/ci-notes/security-runtime.md`): the
 * only single-character lower-case landing into ASCII the Unicode case
 * map carries is U+212A to `k`, no vocabulary word contains a `k`, so
 * every fold-divergent value is rejected under both spellings -- the
 * zone is answered with the method-scoped ignore entry in
 * `infection.json5` (the ETagMiddleware method-token fold precedent).
 */
final class StrictToggles
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
     * Reads one toggle knob's value: native bool passthrough, strict
     * string fold, rejection with the knob named otherwise.
     *
     * @param string $knob the knob's family-qualified name, as the
     *                     rejection spells it (`security.headers hsts`)
     */
    public static function read(mixed $value, string $knob): bool
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

        throw new HttpException(
            "The {$knob} knob must be a boolean or one of the boolean string "
            . 'spellings (true/false, on/off, yes/no, 1/0), ' . get_debug_type($value) . ' given.',
        );
    }
}
