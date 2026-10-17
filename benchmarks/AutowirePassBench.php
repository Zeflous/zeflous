<?php

declare(strict_types=1);

namespace Zef\Benchmark;

use PhpBench\Attributes\Revs;
use Zef\Framework\Container\Autowiring\AutowireCompilerPass;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\DefinitionCompiler;
use Zef\Framework\Container\DefinitionMap;
use Zef\Test\Support\Container\Autowiring\DependentHouse;
use Zef\Test\Support\Container\Autowiring\TypedHouse;
use Zef\Test\Support\Container\Greeter;

/**
 * The autowiring lane's economics: reflection is paid ONCE per boot (the
 * compile pass), never per resolution -- the planned definition resolves
 * through the container's ordinary memoised factory lane, byte-identical
 * to a hand-written positional declaration (see `ROADMAP.md`, "CONTAINER"
 * area 2 -- the compile-pass discipline the github reference ports).
 */
final readonly class AutowirePassBench
{
    private const array SERVICES = [
        'app.greeter' => ['class' => Greeter::class, 'arguments' => ['salutation' => 'Hello']],
        Greeter::class => ['alias' => 'app.greeter'],
        'app.house' => [
            'class' => TypedHouse::class,
            'arguments' => ['channel' => 'ops'],
        ],
        'app.dep' => ['class' => DependentHouse::class, 'autowire' => true],
    ];

    private Container $container;

    public function __construct()
    {
        $this->container = $this->freshlyPlannedContainer();
        $this->container->get('app.house');
        $this->container->get('app.dep');
    }

    #[Revs(1000)]
    public function benchTheCompilePassPerBoot(): void
    {
        $this->freshlyPlannedContainer();
    }

    #[Revs(1000)]
    public function benchTheMemoisedResolutionPerRequest(): void
    {
        $this->container->get('app.dep');
    }

    private function freshlyPlannedContainer(): Container
    {
        $container = new Container();
        $autowireResult = new AutowireCompilerPass()->process(
            DefinitionMap::parse('services', self::SERVICES),
            $container,
        );

        $definitionCompiler = new DefinitionCompiler();
        foreach ($autowireResult->definitions as $definition) {
            $container->set($definition->id, $definitionCompiler->compile($definition));
        }

        return $container;
    }
}
