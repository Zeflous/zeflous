<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The interceptors knob's parsed form: the `interceptors` configuration
 * entry as validated builder entries (ROADMAP, "CONTAINER" area 9 --
 * the reference container's `ServiceMiddlewarePipeline` registrations,
 * adapted to the declarative grammar the way the decoration lane
 * adapted `decorate()`).
 *
 * The knob's grammar is a positional list of entries:
 *
 *   ['interceptors' => [
 *       ['class' => TimingInterceptor::class, 'arguments' => [...], 'priority' => 100],
 *       ['factory' => 'Hooks::interceptor', 'arguments' => [...]],
 *   ]]
 *
 * -- each entry parsed by {@see InterceptionEntryParser::interceptor()},
 * the priority ordering the onion HIGHEST FIRST with ties keeping
 * declaration order (the `middleware.stack` convention).
 *
 * The lane carries a hard bound on the declared interceptors -- 128,
 * the decoration budget's own number -- so a configuration generation
 * mistake fails the boot with a named overflow instead of an
 * unbounded onion.
 *
 * The empty knob -- absent, null, or an empty list -- is the valid
 * no-interception boot: the framework declares the empty list itself
 * ({@see \Zef\Framework\FrameworkDefaults}), a host's interceptors
 * arrive with the host's configuration.
 */
final readonly class InterceptorsMap
{
    /** The hard bound on declared interceptors. */
    public const int BUDGET = 128;

    /** @param list<InterceptionEntry> $interceptors the validated entries, in declaration order */
    private function __construct(
        private array $interceptors,
    ) {
    }

    /**
     * The validated entries: the list the boot materialises in
     * declaration order.
     *
     * @return list<InterceptionEntry>
     */
    public function interceptors(): array
    {
        return $this->interceptors;
    }

    /**
     * Parses the `interceptors` configuration entry into its validated
     * entries, under the lane's budget.
     *
     * @param mixed $source the raw `interceptors` value from the configuration
     */
    public static function parse(string $key, mixed $source): self
    {
        if ($source === null) {
            return new self([]);
        }

        if (!\is_array($source) || !array_is_list($source)) {
            throw InterceptionRefusals::interceptorsNotAList($key, $source);
        }

        if (\count($source) > self::BUDGET) {
            throw InterceptionRefusals::interceptorBudgetExceeded(self::BUDGET);
        }

        return new self(array_map(
            static fn (int $position, mixed $entry): InterceptionEntry => InterceptionEntryParser::interceptor(
                $key . '.' . $position,
                $entry,
            ),
            array_keys($source),
            $source,
        ));
    }
}
