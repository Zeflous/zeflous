<?php

declare(strict_types=1);

namespace Zef\Framework;

use Psr\Container\ContainerInterface;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Console\CommandHandler;
use Zef\Framework\Console\ConsoleCommandEntry;
use Zef\Framework\Console\ConsoleCommands;
use Zef\Framework\Container\Container;

/**
 * The console family's command wiring: the composition fold that
 * answers the command registry and the command handler from the frozen
 * repository -- the `commands` family's own reader, the census-moment
 * seam the module system's CLI extension point consumes ({@see
 * ModuleCliTable} joins every declaring module's declarations under
 * the same key).
 *
 * The fold reads the `console.commands` table -- the host's own
 * entries and the joined module entries riding one key, never a second
 * source of truth -- and answers the registry the declarations spell
 * ({@see ConsoleCommands} owns the table's single judgement: the
 * schema, the fields' bounds, the duplicate verbs, the framework-verb
 * collisions).
 *
 * The dispatch is deliberately lazy where the events wiring's
 * composition is eager: a request-scoped process resolves the wired
 * dispatcher once because requests repeat, but a CLI process runs one
 * command and exits, so the registry answers from the DECLARED table
 * (the `bin/zef list` directory renders it without instantiating a
 * single service) and the handler resolution happens for the ONE
 * command the operator asked for, fail-closed: a service that is not
 * registered, or whose resolved service does not answer the
 * {@see CommandHandler} contract, aborts the dispatch -- a dispatcher
 * that silently skipped a declared listener would be a lie with a
 * delivery count, and a dispatch that silently skipped a declared
 * command would be the same lie with an exit code.
 *
 * The silent posture (the `console` map absent entirely) is the honest
 * no-key answer: a registry with no declarations, answering `list`'s
 * honest empty and dispatch's unknown verb -- the command-less
 * configuration is valid, exactly the way a route-less one is.
 *
 * The registration the one-call boot composes this fold over ({@see
 * ApplicationBootstrapper}, the reserved {@see self::COMMANDS_SERVICE}
 * identifier installed ahead of the definitions, so a host's
 * same-identifier rebind wins) defers behind the container's own
 * laziness: the factory runs at the host's first resolution, never at
 * the boot -- the never-at-the-boot discipline the events family's
 * transient registration planted.
 *
 * @api
 */
final readonly class ConsoleWiring
{
    /**
     * The command registry's service identity, the identifier the
     * one-call boot installs the family's reader under ahead of the
     * declarative definitions -- the reserved-services precedence, so a
     * host's same-identifier entry is an explicit rebind that wins.
     */
    public const string COMMANDS_SERVICE = 'console.commands';

    /**
     * Answers the command registry from the frozen repository: the
     * joined `console.commands` table judged once, its entries carried
     * in their declaration order.
     *
     * @throws ConfigException when the `console` map carries an unknown
     *                         family, or the `commands` value is not the list the join's
     *                         validation guarantees
     */
    public static function commands(Config $config): ConsoleCommands
    {
        return new ConsoleCommands(
            ConsoleFamilies::subtree($config->get('console'), 'commands'),
        );
    }

    /**
     * Installs the registry's reserved service ahead of the definitions:
     * the one-call boot composes this fold ({@see ApplicationBootstrapper}),
     * a host's same-identifier rebind wins (the reserved-services
     * precedence), and the factory defers behind the container's own
     * laziness -- the registry is answered at the host's first
     * resolution, never at the boot.
     */
    public static function register(Container $container, Config $config): void
    {
        $container->set(
            self::COMMANDS_SERVICE,
            static fn (): ConsoleCommands => self::commands($config),
        );
    }

    /**
     * Answers the command's handler, resolved through the container
     * fail-closed -- the one service the operator's verb declared.
     *
     * @param ContainerInterface  $container           the handler resolution surface --
     *                                                 the PSR-11 read side, the same port the module registrar
     *                                                 narrows
     * @param ConsoleCommandEntry $consoleCommandEntry the declared command the handler
     *                                                 answers
     *
     * @throws ConfigException when the declared service does not answer
     *                         the CommandHandler contract -- the resolution's own refusal,
     *                         because the declaration is the repository's promise
     */
    public static function handler(
        ContainerInterface $container,
        ConsoleCommandEntry $consoleCommandEntry,
    ): CommandHandler {
        $handler = $container->get($consoleCommandEntry->service);

        if (!$handler instanceof CommandHandler) {
            throw new ConfigException(
                "The '{$consoleCommandEntry->name->toString()}' command declaration names the service "
                . "'{$consoleCommandEntry->service}' that does not implement CommandHandler.",
            );
        }

        return $handler;
    }
}
