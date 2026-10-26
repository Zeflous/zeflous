<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\MessGate;
use Zef\Test\Support\Tooling\MessReport;
use Zef\Test\Support\Tooling\ToolingException;

/**
 * @internal
 */
#[CoversClass(MessGate::class)]
#[CoversClass(MessReport::class)]
final class MessGateTest extends TestCase
{
    public function testAPayloadWithoutFilesCarriesNoViolation(): void
    {
        $report = MessReport::fromJson('{"version":"3.0.0","files":[]}', '/repo');

        self::assertFalse($report->hasViolations());
        self::assertSame([], $report->violations);
    }

    public function testAViolatingPayloadCarriesEveryFindingRelativisedAgainstTheRoot(): void
    {
        $report = MessReport::fromJson(
            '{"version":"3.0.0","files":[{"file":"/repo/src/A.php","violations":['
            . '{"beginLine":12,"rule":"EvalExpression","description":"eval() is forbidden"},'
            . '{"beginLine":40,"rule":"EvalExpression","description":"eval() is forbidden"}]},'
            . '{"file":"/repo/src/B.php","violations":['
            . '{"beginLine":3,"rule":"EmptyCatchBlock","description":"empty catch"}]}]}',
            '/repo',
        );

        self::assertTrue($report->hasViolations());
        self::assertSame(
            [
                [
                    'file' => 'src/A.php',
                    'line' => 12,
                    'rule' => 'EvalExpression',
                    'description' => 'eval() is forbidden',
                ],
                [
                    'file' => 'src/A.php',
                    'line' => 40,
                    'rule' => 'EvalExpression',
                    'description' => 'eval() is forbidden',
                ],
                [
                    'file' => 'src/B.php',
                    'line' => 3,
                    'rule' => 'EmptyCatchBlock',
                    'description' => 'empty catch',
                ],
            ],
            $report->violations,
        );
    }

    public function testARootSpelledWithATrailingSlashStillRelativises(): void
    {
        $report = MessReport::fromJson(
            '{"version":"3.0.0","files":[{"file":"/repo/src/A.php","violations":['
            . '{"beginLine":12,"rule":"EvalExpression","description":"d"}]}]}',
            '/repo/',
        );

        self::assertSame('src/A.php', $report->violations[0]['file']);
    }

    public function testAPathOutsideTheRootKeepsItsFullSpelling(): void
    {
        $report = MessReport::fromJson(
            '{"version":"3.0.0","files":[{"file":"/elsewhere/src/A.php","violations":['
            . '{"beginLine":12,"rule":"EvalExpression","description":"d"}]}]}',
            '/repo',
        );

        self::assertSame('/elsewhere/src/A.php', $report->violations[0]['file']);
    }

    public function testARootOutsideTheAsciiPlaneStillRelativises(): void
    {
        // The prefix's own length is measured in characters, not bytes
        // (mb_strlen over strlen), so a multibyte root relativises the
        // same file the ASCII root does -- the byte-count spelling would
        // cut the path mid-character and name a file that does not exist.
        $report = MessReport::fromJson(
            '{"version":"3.0.0","files":[{"file":"/répo/src/A.php","violations":['
            . '{"beginLine":12,"rule":"EvalExpression","description":"d"}]}]}',
            '/répo',
        );

        self::assertSame('src/A.php', $report->violations[0]['file']);
    }

    public function testANonListFilesSectionIsRefused(): void
    {
        $this->expectException(ToolingException::class);
        $this->expectExceptionMessageIsOrContains('non-list files section');

        MessReport::fromJson('{"files":"nope"}', '/repo');
    }

    public function testANonListViolationsSectionIsRefused(): void
    {
        $this->expectException(ToolingException::class);
        $this->expectExceptionMessageIsOrContains('non-list violations section');

        MessReport::fromJson('{"files":[{"file":"src/A.php","violations":"nope"}]}', '/repo');
    }

    public function testGarbageIsRefusedAsAPayload(): void
    {
        $this->expectException(ToolingException::class);
        $this->expectExceptionMessageIsOrContains('not valid JSON');

        MessReport::fromJson('{nope', '/repo');
    }

    public function testANonMapFileEntryIsRefused(): void
    {
        $this->expectException(ToolingException::class);
        $this->expectExceptionMessageIsOrContains('non-map file entry');

        MessReport::fromJson('{"files":["src/A.php"]}', '/repo');
    }

    public function testAViolationWithoutAStringFileIsRefused(): void
    {
        $this->expectException(ToolingException::class);
        $this->expectExceptionMessageIsOrContains('malformed violation');

        MessReport::fromJson(
            '{"files":[{"file":42,"violations":[{"beginLine":1,"rule":"R","description":"d"}]}]}',
            '/repo',
        );
    }

    public function testAViolationWithoutAnArrayShapeIsRefused(): void
    {
        $this->expectException(ToolingException::class);
        $this->expectExceptionMessageIsOrContains('malformed violation');

        MessReport::fromJson('{"files":[{"file":"src/A.php","violations":["nope"]}]}', '/repo');
    }

    public function testAViolationWithANonIntegerLineIsRefused(): void
    {
        $this->expectException(ToolingException::class);
        $this->expectExceptionMessageIsOrContains('malformed violation');

        MessReport::fromJson(
            '{"files":[{"file":"src/A.php","violations":[{"beginLine":"12","rule":"R","description":"d"}]}]}',
            '/repo',
        );
    }

    public function testAViolationWithoutAStringRuleIsRefused(): void
    {
        $this->expectException(ToolingException::class);
        $this->expectExceptionMessageIsOrContains('malformed violation');

        MessReport::fromJson(
            '{"files":[{"file":"src/A.php","violations":[{"beginLine":12,"rule":7,"description":"d"}]}]}',
            '/repo',
        );
    }

    public function testAViolationWithoutAStringDescriptionIsRefused(): void
    {
        $this->expectException(ToolingException::class);
        $this->expectExceptionMessageIsOrContains('malformed violation');

        MessReport::fromJson(
            '{"files":[{"file":"src/A.php","violations":[{"beginLine":12,"rule":"R","description":false}]}]}',
            '/repo',
        );
    }

    public function testTheGatePassesACleanReport(): void
    {
        $gateResult = new MessGate()
            ->evaluate(MessReport::fromJson('{"version":"3.0.0","files":[]}', '/repo'))
        ;

        self::assertTrue($gateResult->passed);
        self::assertSame(
            'Mess gate PASSED: no violation under the opted-in ruleset (phpmd.xml).',
            $gateResult->message,
        );
    }

    public function testTheGateFailsGroupingByRuleWithEveryLocationAndSortedRules(): void
    {
        $report = MessReport::fromJson(
            '{"version":"3.0.0","files":[{"file":"src/A.php","violations":['
            . '{"beginLine":12,"rule":"StaticAccess","description":"a"},'
            . '{"beginLine":40,"rule":"EvalExpression","description":"b"},'
            . '{"beginLine":55,"rule":"StaticAccess","description":"c"}]}]}',
            '/repo',
        );

        $gateResult = new MessGate()->evaluate($report);

        self::assertFalse($gateResult->passed);
        self::assertSame(
            'Mess gate FAILED: 3 violation(s) under the opted-in ruleset: '
            . 'EvalExpression x1 (src/A.php:40); StaticAccess x2 (src/A.php:12, src/A.php:55).',
            $gateResult->message,
        );
    }
}
