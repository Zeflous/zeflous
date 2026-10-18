<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * One call entry's shape parser (the entry-level grammar of the
 * method-call injection lane; {@see MethodCall::fromEntry()} composes
 * it with {@see MethodCallNames} into the value object).
 *
 * An entry is the closed-vocabulary map `['method' => ..., 'arguments'
 * => ...]` -- an unknown key is refused with the known list, the
 * configuration layer's own style.
 */
final readonly class MethodCallShape
{
    /** The call entry's closed key vocabulary. */
    private const array ENTRY_KEYS = ['method', 'arguments'];

    /**
     * The call entry as the closed-vocabulary map, fail-closed.
     *
     * @return array<array-key, mixed>
     */
    public static function entry(string $id, mixed $call): array
    {
        if (!\is_array($call)) {
            throw MethodCallRefusals::entryNotAMap($id, $call);
        }

        $unknown = DefinitionKeys::unknownIn($call, self::ENTRY_KEYS);
        if ($unknown !== null) {
            throw MethodCallRefusals::unknownEntryKey($id, $unknown);
        }

        return $call;
    }
}
