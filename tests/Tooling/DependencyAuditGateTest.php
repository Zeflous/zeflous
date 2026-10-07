<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\DependencyAudit;
use Zef\Framework\Tooling\DependencyAuditGate;
use Zef\Framework\Tooling\ToolingException;

/**
 * @internal
 */
#[CoversClass(DependencyAuditGate::class)]
#[CoversClass(DependencyAudit::class)]
final class DependencyAuditGateTest extends TestCase
{
    public function testParsesAdvisoriesAndAbandonedPackages(): void
    {
        $dependencyAudit = DependencyAudit::fromComposerAuditJson(
            '{"advisories":{"a/b":[{}]},"abandoned":{"c/d":"e/f"}}',
        );

        self::assertSame(['a/b'], $dependencyAudit->advisories);
        self::assertSame(['c/d'], $dependencyAudit->abandoned);
    }

    public function testMissingSectionsBecomeEmptyLists(): void
    {
        $dependencyAudit = DependencyAudit::fromComposerAuditJson('{}');

        self::assertSame([], $dependencyAudit->advisories);
        self::assertSame([], $dependencyAudit->abandoned);
    }

    public function testNonArraySectionsBecomeEmptyLists(): void
    {
        $dependencyAudit = DependencyAudit::fromComposerAuditJson('{"advisories":"nope","abandoned":42}');

        self::assertSame([], $dependencyAudit->advisories);
        self::assertSame([], $dependencyAudit->abandoned);
    }

    public function testThrowsWhenThePayloadIsNotJson(): void
    {
        $this->expectException(ToolingException::class);

        DependencyAudit::fromComposerAuditJson('nope');
    }

    public function testBlockedListsSubtractTheAllowlist(): void
    {
        $dependencyAudit = DependencyAudit::fromComposerAuditJson(
            '{"advisories":{"a/b":[{}],"c/d":[{}]},"abandoned":{"e/f":"x"}}',
        );

        self::assertSame(['c/d'], $dependencyAudit->blockedAdvisories(['a/b']));
        self::assertSame([], $dependencyAudit->blockedAbandoned(['e/f']));
    }

    public function testBlockedAbandonedIsReindexed(): void
    {
        $dependencyAudit = DependencyAudit::fromComposerAuditJson('{"abandoned":{"a/b":"x","c/d":"y"}}');

        self::assertSame(['c/d'], $dependencyAudit->blockedAbandoned(['a/b']));
    }

    public function testBlockedAdvisoriesIsReindexed(): void
    {
        $dependencyAudit = DependencyAudit::fromComposerAuditJson('{"advisories":{"a/b":[{}],"c/d":[{}]}}');

        self::assertSame(['c/d'], $dependencyAudit->blockedAdvisories(['a/b']));
    }

    public function testGatePassesWithNoFindings(): void
    {
        $gateResult = new DependencyAuditGate([])->evaluate(DependencyAudit::fromComposerAuditJson('{}'));

        self::assertTrue($gateResult->passed);
        self::assertStringContainsString('Strict audit PASSED', $gateResult->message);
    }

    public function testGateMentionsTheAllowlistWhenPresent(): void
    {
        $gateResult = new DependencyAuditGate(['x/y'])->evaluate(DependencyAudit::fromComposerAuditJson('{}'));

        self::assertTrue($gateResult->passed);
        self::assertStringContainsString('Allow-listed (documented exceptions): x/y', $gateResult->message);
    }

    public function testGateFailsOnAnUnallowListedAdvisory(): void
    {
        $dependencyAudit = DependencyAudit::fromComposerAuditJson('{"advisories":{"a/b":[{}]}}');
        $gateResult = new DependencyAuditGate([])->evaluate($dependencyAudit);

        self::assertFalse($gateResult->passed);
        self::assertStringContainsString('security advisories: a/b', $gateResult->message);
    }

    public function testGateFailsOnAnUnallowListedAbandonedPackage(): void
    {
        $dependencyAudit = DependencyAudit::fromComposerAuditJson('{"abandoned":{"a/b":"x"}}');
        $gateResult = new DependencyAuditGate([])->evaluate($dependencyAudit);

        self::assertFalse($gateResult->passed);
        self::assertStringContainsString('abandoned package(s) outside the allow-list: a/b', $gateResult->message);
    }

    public function testGatePassesWhenFindingsAreAllowListed(): void
    {
        $json = '{"advisories":{"a/b":[{}]},"abandoned":{"c/d":"x"}}';
        $dependencyAudit = DependencyAudit::fromComposerAuditJson($json);

        self::assertTrue(new DependencyAuditGate(['a/b', 'c/d'])->evaluate($dependencyAudit)->passed);
    }
}
