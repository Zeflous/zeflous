<?php

declare(strict_types=1);

namespace Zef\Benchmark;

use PhpBench\Attributes\Revs;
use stdClass;
use Zef\Framework\Config\Config;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\TaggedServices;
use Zef\Framework\ContainerBootstrapper;

/**
 * The tags lane's read-side economics: the index questions are plain
 * map lookups (the boot-built tag => ids map, `has()` and `ids()` the
 * same order as any direct array read), while `services()` pays one
 * container resolution per carrier -- the honest price of a collection
 * that resolves what it names (see `ROADMAP.md`, "CONTAINER" area 6 --
 * the github v2.9 TaggedServiceLocator model this lane ports, over the
 * boot-built index instead of a lazily-built registry view).
 */
final readonly class TaggedServicesBench
{
    private TaggedServices $taggedServices;

    public function __construct()
    {
        $services = [];
        for ($index = 0; $index < 10; ++$index) {
            $services["app.carrier.{$index}"] = [
                'class' => stdClass::class,
                'tags' => ['app.carrying'],
            ];
        }

        $container = new ContainerBootstrapper()->bootstrap(
            new Config(['services' => $services]),
            new Container(),
        );

        $locator = $container->get('framework.tags');
        \assert($locator instanceof TaggedServices);
        $this->taggedServices = $locator;
    }

    /** @return list<string> */
    #[Revs(10000)]
    public function benchIdsForATag(): array
    {
        return $this->taggedServices->ids('app.carrying');
    }

    #[Revs(10000)]
    public function benchHasForATag(): bool
    {
        return $this->taggedServices->has('app.carrying');
    }

    /** @return list<mixed> */
    #[Revs(2000)]
    public function benchServicesForATag(): array
    {
        return $this->taggedServices->services('app.carrying');
    }
}
