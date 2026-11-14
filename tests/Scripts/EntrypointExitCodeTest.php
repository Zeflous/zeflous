<?php

declare(strict_types=1);

namespace Zef\Test\Scripts;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * The CLI entrypoint's own exit-code contract, exercised end to end as
 * real subprocesses the way a host's shell invokes them (issue #773):
 * an invocation the dispatch cannot answer is the operator's own
 * arguments being wrong -- the usage defect the `CommandHandler`
 * vocabulary spells exit 2, answered on STDERR with the words that name
 * the mistake -- never the silent success the fall-through answered
 * with, the answer a wrapping script reads as "nothing needed doing".
 *
 * @internal
 */
#[CoversNothing]
final class EntrypointExitCodeTest extends TestCase
{
    private const string COMMAND = 'bin/zef';

    private const string FIXTURE = 'tests/Scripts/fixtures/configs';

    /**
     * The audit's own reproduction: a verb nothing answers and no
     * directory rides behind it -- the seam's positional refusal, the
     * arm the skip-guards once swallowed into the silent zero.
     */
    public function testAnUnanswerableVerbWithoutItsDirectoryAnswersTheUsageRefusal(): void
    {
        [$exitCode, $output] = $this->runCommand(['bogus']);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('Configuration directory does not exist:', $output);
    }

    /**
     * The naked invocation -- no verb at all: the final usage arm the
     * entrypoint never owned in code (the fall-through's implicit exit
     * 0), the contract's own final answer now spelled on STDERR.
     */
    public function testANakedInvocationAnswersTheFinalUsage(): void
    {
        [$exitCode, $output] = $this->runCommand([]);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString(
            'Usage: bin/zef <command> <config-directory> [args...]',
            $output,
        );
        self::assertStringContainsString(
            "Run 'bin/zef list <config-directory>' to see the commands.",
            $output,
        );
    }

    /**
     * A flag standing in the configuration-directory position: the
     * path defect naming the operator's own spelling, the second arm
     * the vestigial skip-guard once answered with silence.
     */
    public function testAFlagShapedPositionalAnswersThePathDefectNamingIt(): void
    {
        [$exitCode, $output] = $this->runCommand(['shop:ping', '--flag']);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('Configuration directory does not exist: --flag', $output);
    }

    /**
     * The unknown verb over a real directory: the seam's own answer,
     * reached from the entrypoint for every non-empty verb -- the
     * directory command named one step from the mistake.
     */
    public function testAnUnknownVerbOverARealDirectoryNamesTheDirectoryCommand(): void
    {
        [$exitCode, $output] = $this->runCommand(['bogus', self::FIXTURE]);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString(
            "Unknown command 'bogus'. Run 'bin/zef list <config-directory>'",
            $output,
        );
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
