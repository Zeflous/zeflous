<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Container\Aot\AotOutcome;
use Zef\Framework\Container\Aot\ContainerAotCache;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\ContainerCompilePlan;
use Zef\Framework\Container\ContainerDumpLane;
use Zef\Framework\Container\ContainerServices;
use Zef\Framework\Container\InstanceDefinition;
use Zef\Framework\Container\NamespaceRadixTree;
use Zef\Framework\Container\ServiceTagIndex;
use Zef\Framework\ContainerKnobs;
use Zef\Test\Support\Container\Aot\EnumHouse;
use Zef\Test\Support\Container\Aot\PlainSink;
use Zef\Test\Support\Container\Aot\Tone;

/**
 * @internal
 */
#[CoversClass(ContainerDumpLane::class)]
final class ContainerDumpLaneTest extends TestCase
{
    private string $file;

    #[Override]
    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/zef-dump-lane-' . uniqid('', true) . '.php';
    }

    #[Override]
    protected function tearDown(): void
    {
        if (!is_file($this->file)) {
            return;
        }

        unlink($this->file);
    }

    /**
     * The dump is production's alone: a development boot never lays an
     * artifact down, whatever the AOT knob says.
     */
    public function testADevelopmentBootLaysNothingDown(): void
    {
        $lane = new ContainerDumpLane();

        $lane->layDown(new Container(), $this->plan(), $this->knobs('development'), new ContainerAotCache($this->file));

        self::assertFileDoesNotExist($this->file);
    }

    /**
     * A boot without a cache carries no artifact to lay.
     */
    public function testABootWithoutACacheLaysNothingDown(): void
    {
        $lane = new ContainerDumpLane();

        $lane->layDown(new Container(), $this->plan(), $this->knobs('production'), null);

        self::assertFileDoesNotExist($this->file);
    }

    public function testARenderablePlanLaysTheArtifactDown(): void
    {
        $lane = new ContainerDumpLane();

        $lane->layDown(new Container(), $this->plan(), $this->knobs('production'), new ContainerAotCache($this->file));

        self::assertFileExists($this->file);
        self::assertStringContainsString('Tone::CALM', (string) file_get_contents($this->file));
    }

    /**
     * A plan the boundary cannot render degrades: no artifact for the
     * next boot, and the outcome note that names the degradation --
     * never a crashed boot.
     */
    public function testAnUnrenderablePlanDegradesWithTheOutcomeNote(): void
    {
        $lane = new ContainerDumpLane();
        $container = new Container();
        $plan = new ContainerCompilePlan(
            [new InstanceDefinition('app.unrenderable', EnumHouse::class, [new PlainSink()])],
            ServiceTagIndex::fromDefinitions([]),
            [],
            new NamespaceRadixTree(['ids' => [], 'children' => []], [], 0, 0, 0),
        );

        $lane->layDown($container, $plan, $this->knobs('production'), new ContainerAotCache($this->file));

        self::assertFileDoesNotExist($this->file);
        $outcome = $container->get(ContainerServices::AOT_SERVICE);
        self::assertInstanceOf(AotOutcome::class, $outcome);
    }

    private function plan(): ContainerCompilePlan
    {
        return new ContainerCompilePlan(
            [new InstanceDefinition('app.tone', EnumHouse::class, [Tone::CALM])],
            ServiceTagIndex::fromDefinitions([]),
            [],
            new NamespaceRadixTree(['ids' => [], 'children' => []], [], 0, 0, 0),
        );
    }

    private function knobs(string $mode): ContainerKnobs
    {
        return ContainerKnobs::parsed(new Config([
            'aot' => ['enabled' => true],
            'container' => ['mode' => $mode],
        ]));
    }
}
