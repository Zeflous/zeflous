<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\CliList;
use Zef\Test\Support\Tooling\CommandOutcome;

/**
 * The `list` verb as a pure outcome: the argument grammar's own
 * refusals (the missing or flag-shaped positional, the unresolved
 * directory), the `--json` flag's two renderings, and the delegation
 * to the inspection boot's own listing -- a refused inspection
 * surfacing with the boot's own chain, exactly the words the joined
 * branch spelled before the lift.
 *
 * @internal
 */
#[CoversClass(CliList::class)]
#[CoversClass(CommandOutcome::class)]
final class CliListVerbTest extends TestCase
{
    private const string CONFIGS = __DIR__ . '/../../app/configs';

    public function testNoArgumentsAnswerTheUsage(): void
    {
        $outcome = CliList::run([]);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stdout);
        self::assertSame("Usage: bin/zef list <config-directory> [--json]\n", $outcome->stderr);
    }

    public function testAFlagShapedPositionalAnswersTheUsage(): void
    {
        $outcome = CliList::run(['--json']);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stdout);
        self::assertSame("Usage: bin/zef list <config-directory> [--json]\n", $outcome->stderr);
    }

    public function testAMissingDirectoryIsAPathDefectNamingIt(): void
    {
        $outcome = CliList::run(['/does/not/exist']);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stdout);
        self::assertSame("Configuration directory does not exist: /does/not/exist\n", $outcome->stderr);
    }

    public function testTheShippedProfileAnswersItsTable(): void
    {
        $outcome = CliList::run([self::CONFIGS]);

        self::assertSame(0, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
        self::assertStringContainsString('Zef commands -- ', $outcome->stdout);
        self::assertStringContainsString('framework:', $outcome->stdout);
    }

    public function testTheJsonFlagAnswersTheMachineForm(): void
    {
        $outcome = CliList::run([self::CONFIGS, '--json']);

        self::assertSame(0, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
        self::assertStringEndsWith("\n", $outcome->stdout);

        $decoded = json_decode(mb_rtrim($outcome->stdout, "\n"), true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('framework', $decoded);
        self::assertArrayHasKey('commands', $decoded);
        self::assertArrayHasKey('count', $decoded);
        self::assertIsInt($decoded['count']);
    }

    /**
     * A declaration the inspection boot refuses surfaces the full
     * descriptive refusal with exit 1 -- the operator's window
     * carries the chain the boot walked.
     */
    public function testARefusedInspectionSurfacesTheDescriptiveRefusal(): void
    {
        $configDirectory = sys_get_temp_dir() . '/zef-list-verb-' . uniqid('', false);
        mkdir($configDirectory, 0o777, true);
        file_put_contents(
            $configDirectory . '/services.php',
            '<?php return ["app.broken" => ["class" => "Vendor\DoesNotExist"]];',
        );

        try {
            $outcome = CliList::run([$configDirectory]);

            self::assertSame(1, $outcome->exitCode);
            self::assertSame('', $outcome->stdout);
            self::assertSame(
                "The CLI inspection refused: Service 'app.broken' class 'Vendor\\DoesNotExist' does not exist.\n",
                $outcome->stderr,
            );
        } finally {
            unlink($configDirectory . '/services.php');
            rmdir($configDirectory);
        }
    }
}
