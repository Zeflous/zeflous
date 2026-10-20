<?php

declare(strict_types=1);

namespace Zef\Test\Scripts;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Zef\Framework\Middleware\DebugToolbarFactory;
use Zef\Framework\Middleware\ErrorMappingFactory;

/**
 * Exercises the container analysis command end to end as a real
 * subprocess, the same way a host's shell invokes it -- the errors
 * lane's own CLI family discipline (`bin/zef errors:*`): guarded
 * arguments, honest exit codes (0 the answer, 1 the refused boot or
 * the undeclared query, 2 the usage or path defect).
 *
 * @internal
 */
#[CoversNothing]
final class ContainerInventoryCommandTest extends TestCase
{
    private const string COMMAND = 'bin/zef';

    public function testTheShippedProfileAnswersItsInventory(): void
    {
        [$exitCode, $output] = $this->runCommand([
            'container:inventory',
            \dirname(__DIR__, 2) . '/app/configs',
        ]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Container inventory -- ', $output);
        self::assertStringContainsString('boot: the aot knob is off', $output);
        self::assertStringContainsString(
            'http.errors  factory  singleton  ' . ErrorMappingFactory::class . '::fromConfig()',
            $output,
        );
    }

    public function testOneIdentifierAnswersItsFullEntry(): void
    {
        [$exitCode, $output] = $this->runCommand([
            'container:inventory',
            \dirname(__DIR__, 2) . '/app/configs',
            '--id=http.debug_toolbar',
        ]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('http.debug_toolbar', $output);
        self::assertStringContainsString('shape:        factory', $output);
        self::assertStringContainsString(
            'factory:      ' . DebugToolbarFactory::class . '::fromConfig',
            $output,
        );
        self::assertStringContainsString('dependencies: framework.config', $output);
    }

    public function testAnUndeclaredIdentifierRefusesWithExitOne(): void
    {
        [$exitCode, $output] = $this->runCommand([
            'container:inventory',
            \dirname(__DIR__, 2) . '/app/configs',
            '--id=app.absent',
        ]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString("The boot declared no service 'app.absent'.", $output);
    }

    public function testAMissingConfigurationDirectoryIsAUsageDefect(): void
    {
        [$exitCode, $output] = $this->runCommand(['container:inventory', '/does/not/exist']);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('Configuration directory does not exist: /does/not/exist', $output);
    }

    public function testNoArgumentsAnswersTheUsage(): void
    {
        [$exitCode, $output] = $this->runCommand(['container:inventory']);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString(
            'Usage: bin/zef container:inventory <config-directory> [--id=<service>]',
            $output,
        );
    }

    /**
     * A declaration the boot refuses surfaces the full descriptive
     * refusal on STDERR with exit 1 -- the operator's window carries
     * the chain the boot walked.
     */
    public function testARefusedBootSurfacesTheDescriptiveRefusal(): void
    {
        $configDirectory = sys_get_temp_dir() . '/zef-inventory-cli-' . uniqid('', false);
        mkdir($configDirectory, 0o777, true);
        file_put_contents(
            $configDirectory . '/services.php',
            '<?php return ["app.broken" => ["class" => "Vendor\DoesNotExist"]];',
        );

        try {
            [$exitCode, $output] = $this->runCommand(['container:inventory', $configDirectory]);

            self::assertSame(1, $exitCode);
            self::assertStringContainsString('The container boot refused:', $output);
            self::assertStringContainsString('app.broken', $output);
        } finally {
            unlink($configDirectory . '/services.php');
            rmdir($configDirectory);
        }
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{0: int, 1: string}
     */
    private function runCommand(array $arguments): array
    {
        $process = new Process(
            [\PHP_BINARY, \dirname(__DIR__, 2) . '/' . self::COMMAND, ...$arguments],
            \dirname(__DIR__, 2),
        );
        $process->run();

        return [$process->getExitCode() ?? 255, $process->getOutput() . $process->getErrorOutput()];
    }
}
