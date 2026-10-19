<?php

declare(strict_types=1);

namespace Zef\Benchmark;

use PhpBench\Attributes\Revs;
use stdClass;
use Zef\Framework\Config\Config;
use Zef\Framework\Container\Aot\ContainerAotCache;
use Zef\Framework\Container\Container;
use Zef\Framework\ContainerBootstrapper;
use Zef\Test\Support\Container\Aot\CallsHouse;
use Zef\Test\Support\Container\Aot\FactorySource;
use Zef\Test\Support\Container\Aot\House;
use Zef\Test\Support\Container\Aot\Roof;
use Zef\Test\Support\Container\Aot\Window;

/**
 * The compile/dump lane's boot economics (ROADMAP area 8): the full
 * pipeline's once-per-process price against the artifact lane's, over
 * the same definition set.
 *
 * Not a victory claim. The honest cost structure these numbers pin: the
 * full-compile boot pays the reflection walk over every `autowire`
 * declaration, the four passes (plan, autoconfig, contextual, graph
 * validation) and the install loop's per-definition closure compilation
 * -- a cost that grows with the definition set; the artifact boot
 * replaces the pass work with one file `require`, a digest comparison
 * and the install loop over closures that were rendered as literal `new`
 * expressions at dump time. Both lanes pay the same knob-grammar parse
 * of the five source maps -- the fail-closed validation that must hold
 * whichever lane answers -- so on this twenty-six entry fixture, whose
 * arguments are all explicit (no `autowire` declarations, so the
 * reflection surface the artifact skips is near zero), the artifact
 * subject lands about a tenth under the full lane's band: the shared
 * parse dominates, and the delta widens with every autowire declaration
 * and every entry the host declares, which is exactly what the artifact
 * exists for.
 *
 * The memoised-resolution subjects pin the parity clause: after either
 * lane installs, a `get()` answer is the container's own memoisation
 * lane, so the two lanes must land in the same band -- the artifact is
 * an acceleration of the boot, never a second runtime.
 */
final readonly class AotBootBench
{
    private string $artifactFile;

    private Container $fullLane;

    private Container $artifactLane;

    public function __construct()
    {
        $this->artifactFile = sys_get_temp_dir() . '/zef-aot-boot-bench-' . uniqid('', false) . '.php';
        $containerAotCache = new ContainerAotCache($this->artifactFile);

        // The first boot lays the artifact down (full compile + dump);
        // every later boot on this cache loads it.
        new ContainerBootstrapper(containerAotCache: $containerAotCache)
            ->bootstrap(new Config($this->config(['enabled' => true])), new Container())
        ;

        $this->fullLane = new ContainerBootstrapper()
            ->bootstrap(new Config($this->config()), new Container())
        ;

        $this->artifactLane = new ContainerBootstrapper(containerAotCache: $containerAotCache)
            ->bootstrap(new Config($this->config(['enabled' => true])), new Container())
        ;

        // Prime both lanes' memoisation so the resolution subjects
        // measure the hot answer, not the first construction.
        $this->fullLane->get('bench.house.0');
        $this->artifactLane->get('bench.house.0');
    }

    public function __destruct()
    {
        if (!is_file($this->artifactFile)) {
            return;
        }

        unlink($this->artifactFile);
    }

    #[Revs(100)]
    public function benchFullCompileBootFromTheDeclarations(): void
    {
        new ContainerBootstrapper()
            ->bootstrap(new Config($this->config()), new Container())
        ;
    }

    #[Revs(100)]
    public function benchArtifactLoadBootFromTheDumpedFile(): void
    {
        new ContainerBootstrapper(containerAotCache: new ContainerAotCache($this->artifactFile))
            ->bootstrap(new Config($this->config(['enabled' => true])), new Container())
        ;
    }

    #[Revs(100)]
    public function benchMemoisedResolutionFromTheFullLane(): void
    {
        $this->fullLane->get('bench.house.0');
    }

    #[Revs(100)]
    public function benchMemoisedResolutionFromTheArtifactLane(): void
    {
        $this->artifactLane->get('bench.house.0');
    }

    /**
     * The definition set both lanes must boot identically: ten
     * window/house pairs carrying the three argument spellings, the
     * method-call injection shape, the alias, the value, the factory and
     * the transient lifetime -- every shape the artifact renders.
     *
     * @param array<string, mixed> $aot the AOT knob's own value
     *
     * @return array<string, mixed>
     */
    private function config(array $aot = []): array
    {
        $services = [];

        for ($index = 0; $index < 10; ++$index) {
            $services["bench.window.{$index}"] = ['class' => Window::class];
            $services["bench.house.{$index}"] = [
                'class' => House::class,
                'arguments' => [
                    ['service' => "bench.window.{$index}"],
                    $index,
                    ['style' => 'georgian', 'panes' => $index],
                ],
                'tags' => ['bench.frame'],
            ];
        }

        $services['bench.roof'] = ['class' => Roof::class];
        $services['bench.calls'] = [
            'class' => CallsHouse::class,
            'arguments' => [['service' => 'bench.window.0']],
            'calls' => [
                ['method' => 'addRoof', 'arguments' => [['service' => 'bench.roof']]],
                ['method' => 'noteWeather', 'arguments' => ['sunny', 21]],
            ],
        ];
        $services['bench.alias'] = ['alias' => 'bench.house.0'];
        $services['bench.answer'] = ['value' => ['plain' => 'data', 'numbers' => [1, 2, 3]]];
        $services['bench.built'] = [
            'factory' => FactorySource::class . '::make',
            'arguments' => [['service' => 'bench.window.0'], 'steel'],
        ];
        $services['bench.transient'] = ['class' => Window::class, 'lifetime' => 'transient'];
        $services['bench.carrier'] = ['class' => stdClass::class, 'tags' => ['bench.carrying']];

        return ['services' => $services, 'aot' => $aot];
    }
}
