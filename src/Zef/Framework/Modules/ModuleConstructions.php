<?php

declare(strict_types=1);

namespace Zef\Framework\Modules;

use Throwable;

/**
 * The module class's construction guard: existent (the caller's own check
 * names the spelling it arrived from), contract-carrying, constructible
 * with no arguments -- a construction that throws is a refusal naming the
 * class and the reason, because discovery never hands the boot a module it
 * could not build.
 */
final class ModuleConstructions
{
    /**
     * Answers the module instance the class spelling builds.
     *
     * @throws ModuleException when the class does not carry the module
     *                         contract or its constructor throws
     */
    public static function answered(string $class): ModuleInterface
    {
        if (!is_a($class, ModuleInterface::class, true)) {
            throw ModuleBootRefusals::contractMissing($class);
        }

        try {
            $module = new $class();
        } catch (Throwable $throwable) {
            throw ModuleBootRefusals::constructionRefused($class, $throwable);
        }

        return $module;
    }
}
