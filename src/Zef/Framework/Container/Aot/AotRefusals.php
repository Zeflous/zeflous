<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Aot;

use Zef\Framework\Container\DefinitionException;

/**
 * The AOT lane's refusal messages -- every way the knob, the dump or the
 * artifact can fail the lane, each naming the knob, the service or the
 * path it refused, and the way out where one exists.
 *
 * Kept its own archeology-sized unit the way every lane's refusals are
 * ({@see \Zef\Framework\Container\Autowiring\AutoconfigRefusals}): the
 * lane's units keep the verdicts, this class keeps the words.
 *
 * Two exception families, deliberately: the knob's grammar refusals are
 * declaration defects ({@see DefinitionException}, the family every
 * sibling knob refuses through), while the dump and artifact refusals are
 * machinery failures of the compile/dump lane itself
 * ({@see AotException}, the contracts' container root beneath it).
 */
final readonly class AotRefusals
{
    /** The type-given tail the shape refusals share (one spelling). */
    private const string GIVEN = ' given.';

    public static function notAMap(string $key, mixed $source): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}' configuration must be a map of AOT keys, "
            . get_debug_type($source) . self::GIVEN,
        );
    }

    public static function mapRequired(string $key): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}' configuration must be a map of AOT keys, list given.",
        );
    }

    public static function unknownKey(string $key, string $unknown): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}' configuration carries the unknown key '{$unknown}' (known keys: enabled, warm).",
        );
    }

    /**
     * @param string $knob the toggle's family-qualified name, as the
     *                     rejection spells it (`aot.enabled`)
     */
    public static function notAToggle(string $knob, mixed $value): DefinitionException
    {
        return new DefinitionException(
            "The {$knob} knob must be a boolean or one of the boolean string "
            . 'spellings (true/false, on/off, yes/no, 1/0), ' . get_debug_type($value) . self::GIVEN,
        );
    }

    /**
     * The lane's switch is on but no artifact location is wired: the boot
     * refuses rather than silently running the lane it was told to skip.
     */
    public static function enabledWithoutCache(): DefinitionException
    {
        return new DefinitionException(
            "The 'aot.enabled' knob is on but no container AOT cache is wired -- "
            . 'pass one to the application bootstrapper, or spell the knob false.',
        );
    }

    /**
     * A value the artifact boundary cannot carry: the dumped file is plain
     * PHP data, so an object, closure or resource anywhere in a rendered
     * position is refused at dump time -- with the path and the way out.
     * The render-refusal subtype carries the verdict the compile lane
     * degrades on ({@see AotUnrenderableException}).
     *
     * @param string $path the dotted position of the value
     */
    public static function notExportable(string $serviceId, string $path, mixed $value): AotUnrenderableException
    {
        return new AotUnrenderableException(
            "Service '{$serviceId}' carries a value the AOT artifact cannot export at "
            . "'{$path}' (" . get_debug_type($value) . ') -- move the computation behind '
            . 'the factory shape, or boot without the AOT lane.',
        );
    }

    /**
     * A digest input the fingerprint cannot fold: the digest must be stable
     * across processes, and an object's identity is not.
     *
     * @param string $path the dotted position of the value
     */
    public static function notDigestible(string $path, mixed $value): AotException
    {
        return new AotException(
            "The AOT digest cannot fold the value at '{$path}' ("
            . get_debug_type($value) . ') -- the AOT lane requires data-only '
            . 'configuration (arrays and scalars).',
        );
    }

    public static function artifactNotArray(string $file): AotException
    {
        return new AotException(
            "The container AOT artifact '{$file}' must return a map, "
            . 'non-map returned.',
        );
    }

    /**
     * A load of an artifact that is not there: the caller checks
     * existence before choosing the lane, so reaching this refusal is a
     * composition defect, not a cold cache.
     */
    public static function artifactMissing(string $file): AotException
    {
        return new AotException(
            "The container AOT artifact '{$file}' does not exist.",
        );
    }

    /**
     * @param int|string $id the malformed entry's own key, named in the message
     */
    public static function entryMalformed(int|string $id): AotException
    {
        return new AotException(
            "The artifact entry for service '{$id}' is malformed.",
        );
    }

    /**
     * A member that must be a non-empty string is not one: the member
     * named with the type it carried.
     *
     * @param int|string $id     the entry carrying the member
     * @param string     $member the member's own name
     */
    public static function entryMemberString(int|string $id, string $member, mixed $given): AotException
    {
        return new AotException(
            "The artifact entry for service '{$id}' member '{$member}' must be a "
            . 'non-empty string, ' . get_debug_type($given) . self::GIVEN,
        );
    }

    /**
     * A member that must be a list is not one: the member named with
     * the type it carried.
     *
     * @param int|string $id     the entry carrying the member
     * @param string     $member the member's own name
     */
    public static function entryMemberList(int|string $id, string $member, mixed $given): AotException
    {
        return new AotException(
            "The artifact entry for service '{$id}' member '{$member}' must be a "
            . 'list, ' . get_debug_type($given) . self::GIVEN,
        );
    }

    /**
     * A structural defect of the artifact's own envelope: the payload a
     * well-formed dump always writes, named by what is wrong with it.
     *
     * @param non-empty-string $what the structural complaint
     */
    public static function artifactShape(string $file, string $what): AotException
    {
        return new AotException(
            "The container AOT artifact '{$file}' is malformed: {$what}.",
        );
    }

    /**
     * The kind dispatch's own refusal fold: a kind member spelled as a
     * string names its own spelling; anything else -- absent, null, or
     * a type the artifact's grammar never carried -- is the malformed
     * entry it always was ({@see entryMalformed()}). One seam, so the
     * dispatch that reads the member never binds it to a variable of
     * its own.
     *
     * @param int|string $id   the entry whose kind is neither live spelling
     * @param mixed      $kind the kind member as the artifact carried it,
     *                         empty string included -- an empty spelling is
     *                         a defect the refusal must be able to name
     */
    public static function entryKindRefused(int|string $id, mixed $kind): AotException
    {
        return \is_string($kind)
            ? new AotException(
                "The artifact entry for service '{$id}' carries the unknown entry kind '{$kind}' "
                . '(known kinds: closure, build).',
            )
            : self::entryMalformed($id);
    }

    /**
     * @param int|string $id       the entry carrying the lifetime
     * @param string     $lifetime the spelling as the artifact carried it,
     *                             empty included -- an empty spelling is
     *                             a defect the refusal must be able to name
     */
    public static function entryLifetime(int|string $id, string $lifetime): AotException
    {
        return new AotException(
            "The artifact entry for service '{$id}' carries the unknown lifetime '{$lifetime}'.",
        );
    }

    /**
     * A torn or hand-edited artifact: the file exists but is not valid PHP
     * at all, the exact shape a partial write produces.
     */
    public static function artifactCorrupt(string $file, string $reason): AotException
    {
        return new AotException(
            "The container AOT artifact '{$file}' is not valid PHP ({$reason}) -- "
            . 'rebuild the artifact.',
        );
    }

    /**
     * The dump's write path failed: the artifact could not be laid down.
     */
    public static function artifactUnwritable(string $file): AotException
    {
        return new AotException(
            "Cannot write the container AOT artifact '{$file}'.",
        );
    }

    /**
     * The dump's rename could not finalise the atomic write.
     */
    public static function artifactUnfinalisable(string $file): AotException
    {
        return new AotException(
            "Cannot finalise the container AOT artifact '{$file}'.",
        );
    }

    /**
     * The artifact's cache directory does not exist: the dump refuses
     * rather than creating directories the host did not declare.
     */
    public static function cacheDirectoryMissing(string $file): AotException
    {
        return new AotException(
            'The container AOT cache directory "' . \dirname($file) . '" does not exist.',
        );
    }
}
