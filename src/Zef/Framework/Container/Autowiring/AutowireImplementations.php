<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Zef\Framework\Container\Container;
use Zef\Framework\Container\FactoryDefinition;
use Zef\Framework\Container\InstanceDefinition;
use Zef\Framework\Container\ServiceDefinition;
use Zef\Framework\Container\UnplannedInstanceDefinition;

/**
 * The compile run's implementation collector: the declared identifiers
 * whose services are the declared implementations of one type -- the
 * answer a variadic parameter's collection is made of (the github v2.9
 * collectImplementations model, ported into this lane's declared-set
 * discipline).
 *
 * The source is the DECLARED set alone, snapshotted when the run opens:
 * a class-shaped declaration names its product by the class it spells,
 * and a factory-shaped declaration names its product by the return type
 * its static method declares. What the snapshot deliberately excludes:
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
 *   edge extraction already applies to factory targets); a factory
 *   whose target a declared or container identifier shadows joins no
 *   collection -- declare the implementation with a shape that names
 *   its type.
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
        if ($container === null) {
            throw new AutowiringException(
                'The implementation collector answers only inside an opened run '
                . '-- open(definitions, container) before collecting.',
            );
        }

        $ids = [];
        foreach ($this->declaredById as $id => $declaration) {
            $product = $this->productType($declaration, $container);
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

    /**
     * The type one declaration proves its service to be -- a class
     * shape's own class, a factory shape's static product, or null
     * for the shapes that name no type.
     */
    private function productType(ServiceDefinition $serviceDefinition, Container $container): ?string
    {
        if ($serviceDefinition instanceof InstanceDefinition) {
            return $serviceDefinition->class;
        }

        if ($serviceDefinition instanceof UnplannedInstanceDefinition) {
            return $serviceDefinition->class;
        }

        if ($serviceDefinition instanceof FactoryDefinition) {
            return AutowireFactoryProduct::type($serviceDefinition->factory, $this->declaredById, $container);
        }

        return null;
    }
}
