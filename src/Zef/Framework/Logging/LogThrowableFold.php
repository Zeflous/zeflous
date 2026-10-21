<?php

declare(strict_types=1);

namespace Zef\Framework\Logging;

use Throwable;

/**
 * The throwable fold the context sanitiser renders for a wrapped
 * diagnostic ({@see LogContextSanitizer} keeps the fold's caller).
 *
 * The exception's own three facts plus the wrapped chain it carries
 * -- the previous link is the root cause a wrapped diagnostic exists
 * to name, and a record that drops it answers a causeless failure.
 * The chain folds under its own bound: a pathological wrap chain
 * stops at the marker, never at the stack. Each link the same three
 * facts, the budget's marker the honest end of a pathological chain,
 * every message redacted through the credential shapes
 * ({@see LogCredentialRedaction}) a diagnostic can carry.
 */
final class LogThrowableFold
{
    /**
     * The throwable fold's chain budget: wrap links beyond it stop at
     * the marker, never at the stack.
     */
    private const int CHAIN_BUDGET = 10;

    /**
     * @return array<string, mixed>
     */
    public static function fold(Throwable $throwable): array
    {
        $entry = [
            'class' => $throwable::class,
            'message' => LogCredentialRedaction::redact($throwable->getMessage()),
            'code' => $throwable->getCode(),
        ];

        $previous = $throwable->getPrevious();

        if ($previous instanceof Throwable) {
            $entry['previous'] = self::link($previous, 1);
        }

        return $entry;
    }

    /**
     * One wrapped link of the chain, nested under its parent the way
     * the wrap itself nests.
     *
     * @return array<string, mixed>
     */
    private static function link(Throwable $throwable, int $depth): array
    {
        $nested = [
            'class' => $throwable::class,
            'message' => LogCredentialRedaction::redact($throwable->getMessage()),
            'code' => $throwable->getCode(),
        ];

        $previous = $throwable->getPrevious();

        if ($previous instanceof Throwable) {
            if ($depth >= self::CHAIN_BUDGET) {
                $nested['_truncated'] = true;

                return $nested;
            }

            $nested['previous'] = self::link($previous, $depth + 1);
        }

        return $nested;
    }
}
