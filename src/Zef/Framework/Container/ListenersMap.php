<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The listeners knob's parsed form: the `listeners` configuration entry
 * as its two validated builder lists (ROADMAP, "CONTAINER" area 9 --
 * the reference container's `ContainerEventListenerRegistry`
 * registrations, adapted to the declarative grammar).
 *
 * The knob's grammar is a map of exactly two known kinds:
 *
 *   ['listeners' => [
 *       'resolving' => [ ['class' => SlowQueryLog::class, 'arguments' => [...]] ],
 *       'resolved'  => [ ['factory' => 'Metrics::wrap'] ],
 *   ]]
 *
 * -- the map's shape proven by {@see ListenersShapes}, each kind's list
 * parsed by {@see ListenerList} under the kind's own label and budget.
 * The firing order is the declaration order (a listener entry carrying
 * `priority` is refused: the honest spelling a priority would blur).
 *
 * The empty knob -- absent, null, or an empty map -- is the valid
 * no-listener boot: the framework declares the empty map itself
 * ({@see \Zef\Framework\FrameworkDefaults}), a host's listeners arrive
 * with the host's configuration.
 */
final readonly class ListenersMap
{
    /**
     * @param list<InterceptionEntry> $resolving the validated resolving entries, in declaration order
     * @param list<InterceptionEntry> $resolved  the validated resolved entries, in declaration order
     */
    private function __construct(
        private array $resolving,
        private array $resolved,
    ) {
    }

    /**
     * @return list<InterceptionEntry>
     */
    public function resolving(): array
    {
        return $this->resolving;
    }

    /**
     * @return list<InterceptionEntry>
     */
    public function resolved(): array
    {
        return $this->resolved;
    }

    /**
     * Parses the `listeners` configuration entry into its validated
     * lists.
     *
     * @param mixed $source the raw `listeners` value from the configuration
     */
    public static function parse(string $key, mixed $source): self
    {
        if ($source === null) {
            return new self([], []);
        }

        $shapes = ListenersShapes::checked($key, $source);

        return new self(
            ListenerList::parsed($key, 'resolving', $shapes['resolving'] ?? null),
            ListenerList::parsed($key, 'resolved', $shapes['resolved'] ?? null),
        );
    }
}
