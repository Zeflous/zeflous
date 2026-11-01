<?php

declare(strict_types=1);

namespace Zef\Framework\Modules;

use Throwable;

/**
 * The discovery phase's refusals, each naming the subject it refuses: the
 * class that does not exist, the contract it does not carry, the
 * constructor that cannot answer, and the manifest whose class or
 * identity disagrees with it -- one fold per phase, so a phase's refusal
 * is spelled once and every caller carries the same sentence. The
 * configuration side's arms live in {@see ModuleConfigRefusals}, the
 * ordering fold's dead ends beside the walk in {@see ModuleOrderRefusals}.
 */
final class ModuleBootRefusals
{
    /**
     * @throws ModuleException always
     */
    public static function classMissing(string $entry): never
    {
        throw new ModuleException(
            "The module class '{$entry}' does not exist (is the autoloader "
            . 'aware of it?).',
        );
    }

    /**
     * @throws ModuleException always
     */
    public static function contractMissing(string $class): never
    {
        throw new ModuleException(
            "The module class '{$class}' does not implement the module "
            . 'contract (' . ModuleInterface::class . ').',
        );
    }

    /**
     * @throws ModuleException always
     */
    public static function constructionRefused(string $class, Throwable $throwable): never
    {
        throw new ModuleException(
            "The module class '{$class}' could not be constructed: "
            . $throwable->getMessage() . '.',
            0,
            $throwable,
        );
    }

    /**
     * @throws ModuleException always
     */
    public static function manifestClassMissing(string $class, string $entry): never
    {
        throw new ModuleException(
            "The module class '{$class}' (declared by the manifest "
            . "'{$entry}') does not exist (is the autoloader aware of it?).",
        );
    }

    /**
     * The declarative and the live spellings of one module must answer the
     * same identity -- whichever the boot believed, half the record would
     * be lying.
     *
     * @throws ModuleException when the two identities disagree
     */
    public static function assertedIdentityAgreement(
        ModuleMetadata $live,
        ModuleMetadata $declared,
        string $entry,
    ): void {
        if (
            $live->name === $declared->name
            && $live->version === $declared->version
            && $live->requires === $declared->requires
        ) {
            return;
        }

        throw new ModuleException(
            "The module class '{$live->name}' answers the identity "
            . "'{$live->name}' at '{$live->version}' requiring ["
            . implode(', ', $live->requires) . '] while its manifest '
            . "'{$entry}' declares '{$declared->name}' at "
            . "'{$declared->version}' requiring ["
            . implode(', ', $declared->requires) . ']; one module is one '
            . 'identity.',
        );
    }
}
