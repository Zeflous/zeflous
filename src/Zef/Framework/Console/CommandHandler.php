<?php

declare(strict_types=1);

namespace Zef\Framework\Console;

/**
 * The command handler contract: the one verb a service must answer for
 * a `console.commands` declaration to dispatch it -- the module
 * system's CLI extension point's runtime surface, the same shape the
 * event lane's {@see \Zef\Framework\Event\EventListener} contract
 * carries for its declarations.
 *
 * The handler owns its own argument surface: `run()` receives the
 * command-line arguments after the command's own name and the
 * configuration directory (the dispatch grammar is `bin/zef <command>
 * <config-directory> [args...]`), and the handler parses what it
 * declared -- the framework never interprets a module command's
 * arguments for it, exactly the way it never interprets a route
 * handler's request body.
 *
 * The return value is the process exit code, riding the CLI grammar's
 * own vocabulary: `0` the answer, `1` an operational refusal (the
 * command ran and the world refused -- a missing file, a failed
 * connection), `2` a usage defect (the operator's own arguments were
 * wrong). The dispatch passes the code through verbatim: the framework
 * owns the boot and the resolution, the handler owns everything after.
 *
 * Output is the handler's own affair -- the CLI process's STDOUT and
 * STDERR are the surfaces the builtin verbs themselves write to, and a
 * declared command answers on the same terminals.
 */
interface CommandHandler
{
    /**
     * Runs the command over the arguments the operator spelled after
     * the command name and the configuration directory.
     *
     * @param list<string> $arguments the raw arguments after the
     *                                command's own name and the configuration directory
     *
     * @return int the process exit code: 0 the answer, 1 an
     *             operational refusal, 2 a usage defect
     */
    public function run(array $arguments): int;
}
