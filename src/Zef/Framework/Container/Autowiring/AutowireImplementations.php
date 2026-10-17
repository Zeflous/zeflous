<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Zef\Framework\Container\Container;
use Zef\Framework\Container\ServiceDefinition;

/**
 * The compile run's implementation collector: the declared identifiers
 * whose services are the declared implementations of one type -- the
 * answer a variadic parameter's collection is made of (the github v2.9
 * collectImplementations model, ported into this lane's declared-set
 * discipline).
 *
 * The source is the DECLARED set alone, snapshotted when the run opens
 * ({@see AutowireProducts} reads each declaration's proven type). What
 * the snapshot deliberately excludes:
 *
 * - **births** -- an unregistered class a *different* parameter asked
 *   for is an incident of this run's planning, not a declaration of the
 *   type's ecosystem; including it would make the collection depend on
 *   the order parameters happen to plan in, and an order-dependent
 *   answer is a guess wearing a different hat;
 * - **alias and value shapes** -- an alias is a pointer whose target's
 *   type it does not spell, a value is a constant with none; the
 *   declaration that wants to join a collection says so with a class or
 *   factory shape;
 * - **instance-method factory targets** -- a `'service.id::method'`
 *   spelling resolves the service at call time, so the spelling cannot
 *   prove the product's type (the same shadowing rule the boot graph's
 *   edge extraction already applies to factory targets).
 */
final class AutowireImplementations
{
    /** @var array<string, ServiceDefinition> the declared set by identifier, the last declaration winning */
    private array $declaredById = [];

    private ?Container $container = null;

    /**
     * Opens the run: the declared set to collect from, the container
     * whose own identifiers shadow factory targets. A collector that
     * never opened answers nothing: the refusal below keeps the
     * un-opened state honest instead of silently empty.
     *
     * @param list<ServiceDefinition> $definitions
     */
    public function open(array $definitions, Container $container): void
    {
        $this->declaredById = [];
        $this->container = $container;

        foreach ($definitions as $definition) {
            $this->declaredById[$definition->id] = $definition;
        }
    }

    /**
     * The declared implementations of one type, in first-declaration
     * order -- an empty list when nothing declares the type (an empty
     * collection is a valid collection).
     *
     * @param string $type the class or interface the collection gathers
     *
     * @return list<string>
     */
    public function ids(string $type): array
    {
        $container = $this->container;
        if (!$container instanceof Container) {
            throw new AutowiringException(
                'The implementation collector answers only inside an opened run '
                . '-- open(definitions, container) before collecting.',
            );
        }

        $ids = [];
        foreach ($this->declaredById as $id => $declaration) {
            $product = AutowireProducts::of($declaration, $this->declaredById, $container);
            if ($product === null) {
                continue;
            }

            if (!is_a($product, $type, true)) {
                continue;
            }

            $ids[] = $id;
        }

        return $ids;
    }
}
