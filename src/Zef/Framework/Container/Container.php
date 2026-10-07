<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Closure;
use Override;
use Psr\Container\ContainerInterface;

/**
 * Minimal, reflection-free service locator implementing PSR-11.
 *
 * This is the seed of the ZEF container: it stores factories and memoises the
 * instances they build. Autowiring, compiler passes, tags and per-request scopes
 * described in `ROADMAP.md` are layered on top of this class, not inside it.
 *
 * @phpstan-type Factory Closure(self): mixed
 */
final class Container implements ContainerInterface
{
    /** @var array<string, mixed> */
    private array $resolved = [];

    /** @var array<string, Closure(self): mixed> */
    private array $factories = [];

    /**
     * Registers a factory under an identifier.
     *
     * @param Closure(self): mixed $factory
     */
    public function set(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->resolved[$id]);
    }

    #[Override]
    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->resolved)
            || \array_key_exists($id, $this->factories);
    }

    #[Override]
    public function get(string $id): mixed
    {
        if (\array_key_exists($id, $this->resolved)) {
            return $this->resolved[$id];
        }

        if (!\array_key_exists($id, $this->factories)) {
            throw new NotFoundException(\sprintf('Service "%s" is not defined.', $id));
        }

        $service = ($this->factories[$id])($this);

        $this->resolved[$id] = $service;

        return $service;
    }
}
