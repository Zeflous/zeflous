<?php

declare(strict_types=1);

namespace Zef\Test\Modules\Fixtures;

use Override;
use Zef\Framework\Console\CommandHandler;

/**
 * The CLI fixture's command handler: the service the module's declared
 * command resolves through -- its `run()` answering on the fixture log
 * so the dispatch's evidence tape carries the arguments it received,
 * and its exit code spelled by the arguments themselves (the contract's
 * own vocabulary: 0 the answer, 1 the operational refusal, 2 the usage
 * defect -- a handler that answers each spelling proves the dispatch
 * passes the code through verbatim).
 */
final class PingHandler implements CommandHandler
{
    #[Override]
    public function run(array $arguments): int
    {
        FixtureLog::record('command', implode(',', $arguments));

        if ($arguments === ['--refuse']) {
            return 1;
        }

        if ($arguments === ['--usage']) {
            return 2;
        }

        return 0;
    }
}
