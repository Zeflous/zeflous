<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\CommandOutcome;
use Zef\Test\Support\Tooling\ContainerInventory;

/**
 * The `container:inventory` verb as a pure outcome: the argument
 * grammar's own refusals (the missing or flag-shaped positional, the
 * unresolved directory), the `--id=` filter's parsing (the last
 * spelling wins, an empty spelling answers the whole table), and the
 * delegation to the boot's own report -- a refused boot surfacing
 * with the boot's own chain, exactly the words the joined branch
 * spelled before the lift.
 *
 * @internal
 */
#[CoversClass(ContainerInventory::class)]
#[CoversClass(CommandOutcome::class)]
final class ContainerInventoryTest extends TestCase
{
    private const string CONFIGS = __DIR__ . '/../../app/configs';

    public function testNoArgumentsAnswerTheUsage(): void
    {
        $outcome = ContainerInventory::run([]);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stdout);
        self::assertSame(
            "Usage: bin/zef container:inventory <config-directory> [--id=<service>]\n",
            $outcome->stderr,
        );
    }

    public function testAFlagShapedPositionalAnswersTheUsage(): void
    {
        $outcome = ContainerInventory::run(['--id=http.debug_toolbar']);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stdout);
        self::assertSame(
            "Usage: bin/zef container:inventory <config-directory> [--id=<service>]\n",
            $outcome->stderr,
        );
    }

    public function testAMissingDirectoryIsAPathDefectNamingIt(): void
    {
        $outcome = ContainerInventory::run(['/does/not/exist']);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stdout);
        self::assertSame(
            "Configuration directory does not exist: /does/not/exist\n",
            $outcome->stderr,
        );
    }

    public function testTheShippedProfileAnswersItsInventory(): void
    {
        $outcome = ContainerInventory::run([self::CONFIGS]);

        self::assertSame(0, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
        self::assertStringContainsString('Container inventory -- ', $outcome->stdout);
        self::assertStringContainsString('boot: the aot knob is off', $outcome->stdout);
    }

    public function testTheIdentifierFilterAnswersItsFullEntry(): void
    {
        $outcome = ContainerInventory::run([self::CONFIGS, '--id=http.debug_toolbar']);

        self::assertSame(0, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
        self::assertStringContainsString('http.debug_toolbar', $outcome->stdout);
        self::assertStringContainsString('shape:', $outcome->stdout);
    }

    public function testAnEmptyIdSpellingAnswersTheWholeTable(): void
    {
        $outcome = ContainerInventory::run([self::CONFIGS, '--id=']);

        self::assertSame(0, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
        self::assertStringContainsString('Container inventory -- ', $outcome->stdout);
    }

    public function testTheLastIdSpellingWins(): void
    {
        $outcome = ContainerInventory::run([self::CONFIGS, '--id=http.errors', '--id=http.debug_toolbar']);

        self::assertSame(0, $outcome->exitCode);
        self::assertStringContainsString('http.debug_toolbar', $outcome->stdout);
        self::assertStringContainsString('shape:', $outcome->stdout);
    }

    public function testAnUndeclaredIdentifierSurfacesTheBootRefusalNamingIt(): void
    {
        $outcome = ContainerInventory::run([self::CONFIGS, '--id=app.absent']);

        self::assertSame(1, $outcome->exitCode);
        self::assertSame('', $outcome->stdout);
        self::assertSame(
            "The container boot refused: The boot declared no service 'app.absent'.\n",
            $outcome->stderr,
        );
    }

    /**
     * A declaration the boot refuses surfaces the full descriptive
     * refusal with exit 1 -- the operator's window carries the chain
     * the boot walked.
     */
    public function testARefusedBootSurfacesTheDescriptiveRefusal(): void
    {
        $configDirectory = sys_get_temp_dir() . '/zef-inventory-verb-' . uniqid('', false);
        mkdir($configDirectory, 0o777, true);
        file_put_contents(
            $configDirectory . '/services.php',
            '<?php return ["app.broken" => ["class" => "Vendor\DoesNotExist"]];',
        );

        try {
            $outcome = ContainerInventory::run([$configDirectory]);

            self::assertSame(1, $outcome->exitCode);
            self::assertSame('', $outcome->stdout);
            self::assertStringContainsString('The container boot refused: ', $outcome->stderr);
            self::assertStringContainsString('app.broken', $outcome->stderr);
        } finally {
            unlink($configDirectory . '/services.php');
            rmdir($configDirectory);
        }
    }
}
