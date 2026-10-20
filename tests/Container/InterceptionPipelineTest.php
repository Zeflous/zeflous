<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Zef\Framework\Container\InterceptionPipeline;
use Zef\Test\Support\Container\Interception\InterceptionLog;
use Zef\Test\Support\Container\Interception\RecordingInterceptor;
use Zef\Test\Support\Container\Interception\ShortCircuitInterceptor;

/**
 * The interception onion's own mechanics: the priority order (highest
 * first -- the outermost link), the declaration-order tiebreak, the
 * nesting the links compose, and the short-circuit a link that never
 * calls `$next()` answers (ROADMAP area 9's runtime half).
 *
 * @internal
 */
#[CoversClass(InterceptionPipeline::class)]
final class InterceptionPipelineTest extends TestCase
{
    public function testAnEmptyPipelineAnswersTheTerminalUntouched(): void
    {
        $interceptionPipeline = new InterceptionPipeline([]);

        self::assertSame('terminal', $interceptionPipeline->run('id', static fn (): string => 'terminal'));
    }

    public function testTheHighestPriorityRunsOutermost(): void
    {
        $log = $this->log();
        $interceptionPipeline = new InterceptionPipeline([
            ['interceptor' => new RecordingInterceptor('inner', $log), 'priority' => 0],
            ['interceptor' => new RecordingInterceptor('outer', $log), 'priority' => 100],
        ]);

        self::assertSame('terminal', $interceptionPipeline->run('id', static fn (): string => 'terminal'));

        self::assertSame(
            ['outer > id', 'inner > id', 'inner < id', 'outer < id'],
            $log->lines(),
        );
    }

    public function testTiesKeepDeclarationOrder(): void
    {
        $log = $this->log();
        $interceptionPipeline = new InterceptionPipeline([
            ['interceptor' => new RecordingInterceptor('first', $log), 'priority' => 0],
            ['interceptor' => new RecordingInterceptor('second', $log), 'priority' => 0],
            ['interceptor' => new RecordingInterceptor('third', $log), 'priority' => 0],
        ]);

        $interceptionPipeline->run('id', static fn (): string => 'terminal');

        self::assertSame(
            ['first > id', 'second > id', 'third > id', 'third < id', 'second < id', 'first < id'],
            $log->lines(),
        );
    }

    public function testNegativePrioritiesRunBelowThePositive(): void
    {
        $log = $this->log();
        $interceptionPipeline = new InterceptionPipeline([
            ['interceptor' => new RecordingInterceptor('below', $log), 'priority' => -10],
            ['interceptor' => new RecordingInterceptor('above', $log), 'priority' => 10],
        ]);

        $interceptionPipeline->run('id', static fn (): string => 'terminal');

        self::assertSame(
            ['above > id', 'below > id', 'below < id', 'above < id'],
            $log->lines(),
        );
    }

    public function testASingleLinkWrapsTheTerminal(): void
    {
        $log = $this->log();
        $interceptionPipeline = new InterceptionPipeline([
            ['interceptor' => new RecordingInterceptor('single', $log), 'priority' => -25],
        ]);

        self::assertSame('terminal', $interceptionPipeline->run('id', static fn (): string => 'terminal'));
        self::assertSame(['single > id', 'single < id'], $log->lines());
    }

    public function testAShortCircuitAnswersWithoutTheTerminal(): void
    {
        $sentinel = new stdClass();
        $interceptionPipeline = new InterceptionPipeline([
            ['interceptor' => new ShortCircuitInterceptor($sentinel), 'priority' => 0],
        ]);

        $terminalRan = false;
        $answer = $interceptionPipeline->run('id', static function () use (&$terminalRan): string {
            $terminalRan = true;

            return 'terminal';
        });

        self::assertSame($sentinel, $answer);
        self::assertFalse($terminalRan, 'a link that never calls $next() never runs the terminal');
    }

    public function testAShortCircuitHidesTheInnerLinks(): void
    {
        $log = $this->log();
        $sentinel = new stdClass();
        $interceptionPipeline = new InterceptionPipeline([
            ['interceptor' => new ShortCircuitInterceptor($sentinel), 'priority' => 100],
            ['interceptor' => new RecordingInterceptor('bypassed', $log), 'priority' => 0],
        ]);

        self::assertSame($sentinel, $interceptionPipeline->run('id', static fn (): string => 'terminal'));
        self::assertSame([], $log->lines(), 'the outer short-circuit never reaches the inner links');
    }

    private function log(): InterceptionLog
    {
        return new InterceptionLog();
    }
}
