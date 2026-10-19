<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The decoration knob's parsed form: the `decorators` configuration entry
 * as validated chains (ROADMAP, "CONTAINER" area 7 -- the reference
 * container's programmatic `decorate()` surface, adapted to the
 * declarative grammar the way the contextual-binding lane adapted
 * `when()->needs()->give()`).
 *
 * The knob's grammar is a map of targets to decorator lists:
 *
 *   ['logger' => [['class' => CachedLogger::class, 'arguments' => [['service' => '@inner']]]]]
 *
 * -- one target key, one positional chain whose first entry is the
 * outermost wrapper, each entry parsed by {@see DecoratorEntries}. The
 * target itself is proven at the apply stage, where the definition set
 * is in hand ({@see DecoratorApplication}); the parse owns only the
 * knob's own shape.
 *
 * The lane carries a hard bound on the total number of declared
 * decorators -- 128, the reference container's own decoration budget --
 * so a configuration generation mistake fails the boot with a named
 * overflow instead of an unbounded compile.
 *
 * The empty knob -- absent, null, or an empty map -- is the valid
 * no-decoration boot: the framework declares the empty map itself
 * ({@see \Zef\Framework\FrameworkDefaults}), a host's chains arrive with
 * the host's configuration.
 */
final readonly class DecoratorMap
{
    /**
     * @param array<string, list<DecoratorEntry>> $decorations target => chain (first = outermost)
     */
    private function __construct(
        private array $decorations,
    ) {
    }

    /**
     * The validated chains: the map the boot apply walks.
     *
     * @return array<string, list<DecoratorEntry>>
     */
    public function decorations(): array
    {
        return $this->decorations;
    }

    /**
     * Parses the `decorators` configuration entry into its validated
     * chains, under the lane's budget.
     *
     * @param mixed $source the raw `decorators` value from the configuration
     */
    public static function parse(string $key, mixed $source): self
    {
        if ($source === null) {
            return new self([]);
        }

        if (!\is_array($source)) {
            throw DecoratorRefusals::notAMap($key, $source);
        }

        return new self(DecoratorTargets::decorations($source));
    }
}
