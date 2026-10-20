<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The lazy lane's deferral-request shell: the part of the `lazy` key's
 * grammar both constructing shapes share -- the key's truthiness
 * ({@see LazyKey}) and the one lifetime a deferred materialisation
 * cannot promise ({@see LazyRefusals::requestLifetime()}), answered
 * once so each shape's own derivation stays the one branch it is.
 *
 * The shapes keep what is theirs: the class shape derives its proxy
 * class from its own class ({@see InstanceShape}), the factory shape
 * from the factory method's declared return ({@see
 * LazyReturns::behindTheDeclaredReturn()}); this unit carries only
 * the request both spellings take identically.
 */
final readonly class LazyDeclarations
{
    /**
     * Whether the declaration asks to defer, refusing the one lifetime
     * whose materialisation could not name its scope.
     *
     * @param array<array-key, mixed> $entry           the declaration's raw map
     * @param null|ServiceLifetime    $serviceLifetime the declaration's parsed
     *                                                 lifetime, null the class
     *                                                 shape's default spelling
     */
    public static function deferred(string $id, array $entry, ?ServiceLifetime $serviceLifetime): bool
    {
        if (!LazyKey::flag($id, $entry['lazy'] ?? null)) {
            return false;
        }

        if ($serviceLifetime === ServiceLifetime::REQUEST) {
            throw LazyRefusals::requestLifetime($id);
        }

        return true;
    }
}
