<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Closure;
use Override;
use Psr\Container\ContainerInterface;

/**
 * Minimal, reflection-free service locator implementing PSR-11.
 *
 * This is the seed of the ZEF container: it exposes the PSR-11 surface and
 * delegates storage and resolution to {@see ServiceStore}. Autowiring, compiler
 * passes, tags and per-request scopes described in `ROADMAP.md` are layered on
 * top of this class, not inside it.
 */
final readonly class Container implements ContainerInterface
{
    private ServiceStore $serviceStore;

    public function __construct()
    {
        $this->serviceStore = new ServiceStore();
    }

    /**
     * Registers a factory under an identifier.
     *
     * @param Closure(self): mixed $factory
     */
    public function set(string $id, Closure $factory): void
    {
        $this->serviceStore->set($id, $factory);
    }

    #[Override]
    public function has(string $id): bool
    {
        return $this->serviceStore->has($id);
    }

    #[Override]
    public function get(string $id): mixed
    {
        if ($this->serviceStore->has($id)) {
            return $this->serviceStore->resolve($id, $this);
        }

        throw new NotFoundException('Service "' . $id . '" is not defined.');
    }
}
