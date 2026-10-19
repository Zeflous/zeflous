<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The interception entry grammar's key vocabulary: the spelling of
 * every accepted key for each knob that carries entries, and the
 * unknown-key checks that quote the offender (the
 * {@see DecoratorEntryKeys} posture -- one place owns the vocabulary,
 * so the entry parser's rejections report what *would* have been
 * accepted, never silently ignored).
 *
 * The two grammars differ by exactly one key: the interceptor entry
 * accepts `priority` -- the onion's order -- while the listener entries
 * refuse it, because a listener's firing order is its declaration
 * order and a priority spelling would silently blur it.
 */
final readonly class InterceptionEntryKeys
{
    /** The keys an `interceptors` entry accepts. */
    private const array INTERCEPTOR_KEYS = ['class', 'factory', 'arguments', 'priority'];

    /** The keys a `listeners` entry accepts. */
    private const array LISTENER_KEYS = ['class', 'factory', 'arguments'];

    /**
     * Rejects the interceptor entry's first key outside its vocabulary.
     *
     * @param array<array-key, mixed> $entry
     */
    public static function unknownInterceptorChecked(string $label, array $entry): void
    {
        $unknown = DefinitionKeys::unknownIn($entry, self::INTERCEPTOR_KEYS);
        if ($unknown !== null) {
            throw InterceptionRefusals::unknownInterceptorKey($label, $unknown);
        }
    }

    /**
     * Rejects the listener entry's first key outside its vocabulary --
     * `priority` among them, with its own refusal: the firing order is
     * the declaration order.
     *
     * @param array<array-key, mixed> $entry
     */
    public static function unknownListenerChecked(string $label, array $entry): void
    {
        $unknown = DefinitionKeys::unknownIn($entry, self::LISTENER_KEYS);
        if ($unknown !== null) {
            if ($unknown === 'priority') {
                throw InterceptionRefusals::listenerPriority($label);
            }

            throw InterceptionRefusals::unknownListenerKey($label, $unknown);
        }
    }
}
