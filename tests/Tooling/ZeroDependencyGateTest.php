<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\ComposerManifest;
use Zef\Framework\Tooling\GateResult;
use Zef\Framework\Tooling\ZeroDependencyGate;

/**
 * @internal
 */
#[CoversClass(ZeroDependencyGate::class)]
final class ZeroDependencyGateTest extends TestCase
{
    public function testPassesOnACleanManifest(): void
    {
        $gateResult = $this->evaluate(['php' => '^8.4'], []);

        self::assertTrue($gateResult->passed);
        // The exact message (not a substring): a mutated verdict, swapped
        // fragments or a dropped figure would otherwise survive independent
        // substring assertions.
        self::assertSame(
            'Zero-dependency gate PASSED: composer.json require is {"php": "^8.4"} and composer.lock packages is [].',
            $gateResult->message,
        );
        self::assertSame(0, $gateResult->exitCode());
    }

    public function testPassesWhenOnlyDevPackagesAreLocked(): void
    {
        // Dev dependencies are none of the gate's business: only the
        // production `packages` array is asserted, so a packages-dev-only
        // lock stays a pass.
        $gateResult = $this->evaluate(['php' => '^8.4'], []);

        self::assertTrue($gateResult->passed);
    }

    public function testFailsWhenRequireDeclaresAnExtraPackage(): void
    {
        $gateResult = $this->evaluate(['php' => '^8.4', 'monolog/monolog' => '^3.0'], []);

        self::assertFalse($gateResult->passed);
        self::assertSame(1, $gateResult->exitCode());
        self::assertSame(
            'Zero-dependency gate FAILED: composer.json require is {"php":"^8.4","monolog/monolog":"^3.0"},'
            . ' expected exactly {"php": "^8.4"}.',
            $gateResult->message,
        );
    }

    public function testFailsWhenThePhpConstraintChanges(): void
    {
        $gateResult = $this->evaluate(['php' => '^8.3'], []);

        self::assertFalse($gateResult->passed);
        self::assertSame(
            'Zero-dependency gate FAILED: composer.json require is {"php":"^8.3"}, expected exactly {"php": "^8.4"}.',
            $gateResult->message,
        );
    }

    public function testFailsWhenRequireIsMissingEntirely(): void
    {
        $gateResult = $this->evaluate([], []);

        self::assertFalse($gateResult->passed);
        self::assertSame(
            'Zero-dependency gate FAILED: composer.json require is [], expected exactly {"php": "^8.4"}.',
            $gateResult->message,
        );
    }

    public function testFailsWhenASingleProductionPackageIsLocked(): void
    {
        $gateResult = $this->evaluate(['php' => '^8.4'], [['name' => 'monolog/monolog']]);

        self::assertFalse($gateResult->passed);
        self::assertSame(
            'Zero-dependency gate FAILED: composer.lock declares 1 production package(s);'
            . ' the packages array must stay empty.',
            $gateResult->message,
        );
    }

    public function testFailsWhenTwoProductionPackagesAreLocked(): void
    {
        $gateResult = $this->evaluate(
            ['php' => '^8.4'],
            [['name' => 'monolog/monolog'], ['name' => 'psr/log']],
        );

        self::assertFalse($gateResult->passed);
        self::assertStringContainsString('declares 2 production package(s)', $gateResult->message);
    }

    public function testFailsWhenBothInvariantsAreViolated(): void
    {
        $gateResult = $this->evaluate(['ext-pcntl' => '*'], [['name' => 'psr/log']]);

        self::assertFalse($gateResult->passed);
        self::assertSame(
            'Zero-dependency gate FAILED: composer.json require is {"ext-pcntl":"*"}, expected exactly {"php": "^8.4"};'
            . ' composer.lock declares 1 production package(s); the packages array must stay empty.',
            $gateResult->message,
        );
    }

    public function testReportsAnUnencodableRequireSection(): void
    {
        // A require map json_encode cannot serialise (invalid UTF-8 key) must
        // still produce an actionable failure, never a crash or a silent pass.
        $gateResult = $this->evaluate(["\xB1\x80" => '^8.4'], []);

        self::assertFalse($gateResult->passed);
        self::assertSame(
            'Zero-dependency gate FAILED: composer.json require is <unencodable>, expected exactly {"php": "^8.4"}.',
            $gateResult->message,
        );
    }

    /**
     * @param array<string, string>       $require
     * @param list<array<string, string>> $packages
     */
    private function evaluate(array $require, array $packages): GateResult
    {
        return new ZeroDependencyGate()->evaluate(new ComposerManifest($require, $packages));
    }
}
