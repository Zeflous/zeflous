<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
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
        $containerDumpLane = new ContainerDumpLane();

        $containerDumpLane->layDown(
            new Container(),
            $this->plan(),
            $this->knobs('development'),
            new ContainerAotCache($this->file),
        );

        self::assertFileDoesNotExist($this->file);
    }

    /**
     * A boot without a cache carries no artifact to lay.
     */
    public function testABootWithoutACacheLaysNothingDown(): void
    {
        $containerDumpLane = new ContainerDumpLane();

        $containerDumpLane->layDown(new Container(), $this->plan(), $this->knobs('production'), null);

        self::assertFileDoesNotExist($this->file);
    }

    public function testARenderablePlanLaysTheArtifactDown(): void
    {
        $containerDumpLane = new ContainerDumpLane();

        $containerDumpLane->layDown(
            new Container(),
            $this->plan(),
            $this->knobs('production'),
            new ContainerAotCache($this->file),
        );

        self::assertFileExists($this->file);
        self::assertStringContainsString('Tone::CALM', (string) file_get_contents($this->file));
    }

    /**
     * A renderable plan names no degradation: the artifact landed, so
     * the outcome note the degraded boot answers stays absent -- a
     * note on a boot that dumped would report an unrenderable plan the
     * boundary just rendered.
     */
    public function testARenderablePlanNamesNoDegradedOutcome(): void
    {
        $container = new Container();
        $containerDumpLane = new ContainerDumpLane();

        $containerDumpLane->layDown(
            $container,
            $this->plan(),
            $this->knobs('production'),
            new ContainerAotCache($this->file),
        );

        self::assertFileExists($this->file);
        self::assertFalse(
            $container->has(ContainerServices::AOT_SERVICE),
            'a boot whose artifact landed carries no degraded outcome note',
        );
    }

    /**
     * A plan the boundary cannot render degrades: no artifact for the
     * next boot, and the outcome note that names the degradation --
     * never a crashed boot.
     */
    public function testAnUnrenderablePlanDegradesWithTheOutcomeNote(): void
    {
        $containerDumpLane = new ContainerDumpLane();
        $container = new Container();
        $containerCompilePlan = new ContainerCompilePlan(
            [new InstanceDefinition('app.unrenderable', EnumHouse::class, [new PlainSink()])],
            ServiceTagIndex::fromDefinitions([]),
            [],
            new NamespaceRadixTree(['ids' => [], 'children' => []], [], 0, 0, 0),
        );

        $containerDumpLane->layDown(
            $container,
            $containerCompilePlan,
            $this->knobs('production'),
            new ContainerAotCache($this->file),
        );

        self::assertFileDoesNotExist($this->file);
        $outcome = $container->get(ContainerServices::AOT_SERVICE);
        self::assertInstanceOf(AotOutcome::class, $outcome);
    }

    /**
     * The #792 crash order: the source configuration carries a live
     * object the digest cannot fold, and the fingerprint -- evaluated
     * inside the dump's own refusal boundary -- degrades before the
     * plan is ever consulted, whatever the plan itself carries. The
     * renderable plan here pins the order: an older spelling computed
     * the fingerprint as the degrade call's argument, and the refusal
     * escaped the lane and crashed the boot instead.
     */
    public function testAnUndigestibleConfigurationDegradesBeforeThePlanIsConsulted(): void
    {
        $containerDumpLane = new ContainerDumpLane();
        $container = new Container();

        $containerDumpLane->layDown(
            $container,
            $this->plan(),
            $this->knobsCarryingALiveObject('production'),
            new ContainerAotCache($this->file),
        );

        self::assertFileDoesNotExist($this->file, 'no artifact for a configuration the digest refuses');
        $outcome = $container->get(ContainerServices::AOT_SERVICE);
        self::assertInstanceOf(AotOutcome::class, $outcome);
        self::assertSame(
            'the configuration carries a value the AOT digest cannot fold -- '
            . 'the full lane answered this boot and laid no artifact down',
            $outcome->note,
        );
    }

    /**
     * The same refusal with an unrenderable plan behind it still names
     * the configuration, not the plan: the digest is the first thing
     * the dump asks for, so its refusal is the note the boot answers
     * with -- the order is the observable, not an implementation
     * detail.
     */
    public function testAnUndigestibleConfigurationNamesItselfOverTheUnrenderablePlan(): void
    {
        $containerDumpLane = new ContainerDumpLane();
        $container = new Container();
        $containerCompilePlan = new ContainerCompilePlan(
            [new InstanceDefinition('app.unrenderable', EnumHouse::class, [new PlainSink()])],
            ServiceTagIndex::fromDefinitions([]),
            [],
            new NamespaceRadixTree(['ids' => [], 'children' => []], [], 0, 0, 0),
        );

        $containerDumpLane->layDown(
            $container,
            $containerCompilePlan,
            $this->knobsCarryingALiveObject('production'),
            new ContainerAotCache($this->file),
        );

        self::assertFileDoesNotExist($this->file);
        $outcome = $container->get(ContainerServices::AOT_SERVICE);
        self::assertInstanceOf(AotOutcome::class, $outcome);
        self::assertSame(
            'the configuration carries a value the AOT digest cannot fold -- '
            . 'the full lane answered this boot and laid no artifact down',
            $outcome->note,
        );
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

    /**
     * The lane-legitimate configuration #792 named: a value shape
     * carrying a live object the full lane answers happily and the
     * digest's data-only grammar refuses to fold.
     */
    private function knobsCarryingALiveObject(string $mode): ContainerKnobs
    {
        return ContainerKnobs::parsed(new Config([
            'aot' => ['enabled' => true],
            'container' => ['mode' => $mode],
            'services' => [
                'app.live' => ['value' => new stdClass()],
            ],
        ]));
    }
}
