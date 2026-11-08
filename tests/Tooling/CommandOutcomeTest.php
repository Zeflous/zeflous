<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\CommandOutcome;

/**
 * The CLI verbs' own answer shape: the exit code the dispatch exits
 * with, the two terminal streams the verb's bytes gather into, and the
 * two named constructors the verb families answer through.
 *
 * @internal
 */
#[CoversClass(CommandOutcome::class)]
final class CommandOutcomeTest extends TestCase
{
    public function testTheAnswerShapeCarriesItsCodeAndStdout(): void
    {
        $outcome = CommandOutcome::answered("the answer\n");

        self::assertSame(0, $outcome->exitCode);
        self::assertSame("the answer\n", $outcome->stdout);
        self::assertSame('', $outcome->stderr);
    }

    public function testTheRefusalShapeCarriesItsCodeAndStderr(): void
    {
        $outcome = CommandOutcome::refused(2, "the refusal\n");

        self::assertSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stdout);
        self::assertSame("the refusal\n", $outcome->stderr);
    }

    public function testTheBareConstructorDefaultsBothStreamsToSilence(): void
    {
        $commandOutcome = new CommandOutcome(1);

        self::assertSame(1, $commandOutcome->exitCode);
        self::assertSame('', $commandOutcome->stdout);
        self::assertSame('', $commandOutcome->stderr);
    }
}
