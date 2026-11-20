<?php

declare(strict_types=1);

namespace Zef\Test\Container\Autowiring;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\Autowiring\AutowireBirths;
use Zef\Framework\Container\Autowiring\AutowireCompilerPass;
use Zef\Framework\Container\Autowiring\AutowireParameterSpec;
use Zef\Framework\Container\Autowiring\AutowireReuse;
use Zef\Framework\Container\Autowiring\AutowiringException;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\DefinitionMap;
use Zef\Framework\Container\InstanceDefinition;
use Zef\Test\Support\Container\Autowiring\BirthChainAlpha;
use Zef\Test\Support\Container\Autowiring\BirthChainBeta;
use Zef\Test\Support\Container\Autowiring\ChainRoot;
use Zef\Test\Support\Container\Autowiring\DoubleDepHouse;
use Zef\Test\Support\Container\Autowiring\SimpleDep;

/**
 * The mutation-killer round for the compile run's bookkeeping and fit
 * rules: every escape the filtered infection run surfaced, answered
 * with the test that pins it.
 *
 * @internal
 */
#[CoversClass(AutowireBirths::class)]
#[CoversClass(AutowireReuse::class)]
final class AutowireRunBookkeepingTest extends TestCase
{
    public function testACompletedBirthLeavesNoResidueOnTheChain(): void
    {
        try {
            new AutowireCompilerPass()->process(DefinitionMap::parse('services', [
                'app.double' => ['class' => DoubleDepHouse::class, 'autowire' => true],
                'app.root' => ['class' => ChainRoot::class, 'autowire' => true],
            ]), new Container());
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertSame(
                'The birth chain is circular: ' . BirthChainAlpha::class . ' -> '
                . BirthChainBeta::class . ' -> ' . BirthChainAlpha::class . '.',
                $autowiringException->getMessage(),
                'the completed SimpleDep birth closed its frame before the chain walk started',
            );
        }
    }

    public function testTheReuseQueryAnswersFalseBeforeTheRunOpens(): void
    {
        $autowireReuse = new AutowireReuse(new AutowireBirths());

        self::assertFalse($autowireReuse->known('app.anything'));
    }

    public function testTheReuseQueryRecordsEveryAnsweredIdentifier(): void
    {
        $autowireBirths = new AutowireBirths();
        $autowireReuse = new AutowireReuse($autowireBirths);
        $autowireReuse->open(
            [new InstanceDefinition('app.declared', SimpleDep::class, ['a'])],
            new Container(),
        );

        self::assertTrue($autowireReuse->known('app.declared'));
        self::assertSame(['app.declared'], $autowireBirths->reused());
        self::assertFalse($autowireReuse->known('app.unknown'));
    }

    public function testThePureProbeAnswersTheSameVerdictWithoutRecording(): void
    {
        // The consultative probe's own spelling (issue #800's finding 3):
        // the probe answers the same declared/birthed/carried verdict the
        // recording query answers, and writes no bookkeeping -- the report's
        // `reused` column counts the resolutions that actually happened,
        // never the consultations that merely asked.
        $autowireBirths = new AutowireBirths();
        $autowireReuse = new AutowireReuse($autowireBirths);
        $autowireReuse->open(
            [new InstanceDefinition('app.declared', SimpleDep::class, ['a'])],
            new Container(),
        );

        self::assertTrue($autowireReuse->peek('app.declared'));
        self::assertFalse($autowireReuse->peek('app.unknown'));
        self::assertSame([], $autowireBirths->reused(), 'the probe records nothing');

        // The resolvable consultation is pure the same way: the declared
        // identifier counts no reuse until the resolution arm asks for it.
        self::assertTrue($autowireReuse->resolvable('app.declared'));
        self::assertSame([], $autowireBirths->reused(), 'the resolvable consultation records nothing');
    }

    public function testADefaultedBindingsConsultationCountsNoPhantomReuse(): void
    {
        // The audit's own repro (issue #800's finding 3): a defaulted
        // parameter whose declared type binds nothing consults the reuse
        // query to decide whether the binding must yield to the
        // constructor's own default -- and the first spelling counted the
        // consultation itself as a reuse, so the report's `reused` column
        // overcounted references the run never planned. The pure probe
        // keeps the verdict and drops the phantom.
        $autowireBirths = new AutowireBirths();
        $autowireReuse = new AutowireReuse($autowireBirths);
        $autowireReuse->open(
            [new InstanceDefinition('app.declared', SimpleDep::class, ['a'])],
            new Container(),
        );

        // The consultation the defaulted binding's yield rule reads: the
        // declared identifier answers, but the resolution never runs for
        // it (the parameter takes its default), so nothing is recorded.
        self::assertTrue($autowireReuse->peek('app.declared'));
        self::assertSame([], $autowireBirths->reused());

        // The resolution arm alone records: the reference the plan
        // actually carries is the only reuse the report counts.
        self::assertTrue($autowireReuse->known('app.declared'));
        self::assertSame(['app.declared'], $autowireBirths->reused());
    }

    public function testTheBirthsAnswerTheirOwnMemoAndReport(): void
    {
        $autowireBirths = new AutowireBirths();
        $instanceDefinition = new InstanceDefinition(SimpleDep::class, SimpleDep::class, ['simple']);

        self::assertFalse($autowireBirths->has(SimpleDep::class));

        $autowireBirths->open(SimpleDep::class);
        $autowireBirths->guardChain(ChainRoot::class);

        $autowireBirths->close(SimpleDep::class, $instanceDefinition);
        self::assertTrue($autowireBirths->has(SimpleDep::class));
        self::assertSame([$instanceDefinition], $autowireBirths->definitions());
        self::assertSame([SimpleDep::class], $autowireBirths->generated());
    }

    public function testTheBookkeepingResetsBetweenRuns(): void
    {
        $autowireBirths = new AutowireBirths();
        $autowireBirths->open(SimpleDep::class);
        $autowireBirths->close(SimpleDep::class, new InstanceDefinition(SimpleDep::class, SimpleDep::class, []));
        $autowireBirths->recordReuse('app.declared');

        $autowireBirths->reset();

        self::assertFalse($autowireBirths->has(SimpleDep::class));
        self::assertSame([], $autowireBirths->definitions());
        self::assertSame([], $autowireBirths->generated());
        self::assertSame([], $autowireBirths->reused());
    }

    public function testAPlainScalarParameterAnswersTheEmptyIdentifier(): void
    {
        $autowireParameterSpec = new AutowireParameterSpec(
            name: 'salutation',
            isVariadic: false,
            isOptional: false,
            hasDefault: false,
        );

        self::assertSame('', $autowireParameterSpec->identifier());
        self::assertFalse($autowireParameterSpec->carriesBinding());
    }
}
