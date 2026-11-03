<?php

declare(strict_types=1);

namespace Zef\Framework;

use Psr\Container\ContainerInterface;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Schema\EventSchema;
use Zef\Framework\Container\Container;
use Zef\Framework\Event\EventDispatcher;
use Zef\Framework\Event\EventListener;
use Zef\Framework\Event\EventName;
use Zef\Framework\Event\EventShape;
use Zef\Framework\Event\ListenerPriority;

/**
 * The events family's listener wiring: the composition fold that answers
 * the wired dispatcher from the frozen repository -- the `listen` family's
 * own reader, the census-moment seam the events configuration's own
 * docblock named ("the module system's own events reader is the named
 * future that may widen this block") and the module system's event
 * extension point consumes ({@see ModuleEventsTable} joins every
 * declaring module's declarations under the same key).
 *
 * The fold reads the `events.listen` table -- the host's own entries and
 * the joined module entries riding one key, never a second source of
 * truth -- asserts every entry through the Standard Events Schema
 * ({@see EventSchema}, the direct composition's own validation -- the
 * same fail-closed read the database wiring carries), and answers the
 * dispatcher the declarations spell: every entry's name and fields
 * define the event (the {@see EventName} and {@see EventShape} grammars
 * refusing by name, the {@see EventDispatcher} own already-defined
 * refusal answering a name joined twice), and every listener entry
 * resolves through the PSR-11 container fail-closed -- the pipeline
 * factory's own doctrine ({@see Middleware\PipelineFactory}): a listener
 * service that is not registered, or whose resolved service does not
 * answer the {@see EventListener} contract, aborts the composition,
 * because a dispatcher that silently skipped a declared listener would be
 * a lie with a delivery count.
 *
 * The listener standing is the declaration-order default (`0`) -- the
 * priority vocabulary is the subscribe() caller's own, the same narrow
 * surface the middleware stack's declaration-order tiebreak keeps; a
 * module or host that needs a standing spells it through the dispatcher's
 * own vocabulary at its own composition seam. The silent posture (the
 * `listen` family absent entirely) is the honest no-key answer: a
 * dispatcher with no declarations, dispatching nothing until the host's
 * own code defines -- the zero-listening configuration is valid, exactly
 * the way a route-less one is.
 *
 * The registration the one-call boot composes this fold over ({@see
 * ApplicationBootstrapper}, the reserved {@see self::DISPATCHER_SERVICE}
 * identifier installed ahead of the definitions, so a host's
 * same-identifier rebind wins) defers behind the container's own
 * laziness: the factory runs at the host's first resolution, never at the
 * boot -- the never-at-the-boot discipline the events family's own
 * transient registration planted.
 *
 * @api
 */
final readonly class EventWiring
{
    /**
     * The wired dispatcher's service identity, the identifier the
     * one-call boot installs the family's reader under ahead of the
     * declarative definitions -- the reserved-services precedence, so a
     * host's same-identifier entry is an explicit rebind that wins.
     */
    public const string DISPATCHER_SERVICE = 'events.dispatcher';

    /**
     * Installs the wired dispatcher's reserved service ahead of the
     * definitions: the one-call boot composes this fold ({@see
     * ApplicationBootstrapper}), a host's same-identifier rebind wins
     * (the reserved-services precedence), and the factory defers behind
     * the container's own laziness -- the dispatcher is answered at the
     * host's first resolution, never at the boot.
     */
    public static function register(Container $container, Config $config): void
    {
        $container->set(
            self::DISPATCHER_SERVICE,
            static fn (Container $container): EventDispatcher => self::dispatcher(
                $config,
                $container,
            ),
        );
    }

    /**
     * Answers the wired dispatcher from the frozen repository: every
     * `events.listen` entry defined onto the dispatcher, every declared
     * listener resolved through the container and subscribed at the
     * declaration-order standing.
     *
     * @param ContainerInterface $container the listener resolution
     *                                      surface -- the PSR-11 read side, the same port the module
     *                                      registrar narrows
     *
     * @throws ConfigException when the `events` map carries an unknown
     *                         family, the `listen` value is not the list the join's validation
     *                         guarantees, or a declared listener's resolved service does not
     *                         answer the EventListener contract
     */
    public static function dispatcher(Config $config, ContainerInterface $container): EventDispatcher
    {
        $eventDispatcher = new EventDispatcher();

        $listen = EventFamilies::subtree($config->get('events'), 'listen');

        if ($listen === null) {
            return $eventDispatcher;
        }

        if (!\is_array($listen)) {
            throw new ConfigException(
                'The events.listen configuration must be a list of event declarations, '
                . get_debug_type($listen) . ' given.',
            );
        }

        $eventSchema = new EventSchema();

        foreach ($listen as $entry) {
            $eventSchema->assert($entry);

            /**
             * The schema's verdict is the entry's own shape proof -- the
             * assertion above checked the shape this reading trusts, the
             * same trust the route table's readers ride (and the direct
             * composition this fold's own @api surface answers keeps the
             * validation with the reader, never assuming the boot's seam
             * ran).
             *
             * @var array{name: string, fields: array<array-key, string>,
             *     listeners?: list<string>} $entry
             */
            $eventName = new EventName($entry['name']);
            $eventShape = new EventShape($entry['fields']);

            $eventDispatcher->define($eventName, $eventShape);

            foreach ($entry['listeners'] ?? [] as $listenerId) {
                $listener = $container->get($listenerId);

                if (!$listener instanceof EventListener) {
                    throw new ConfigException(
                        "The '{$entry['name']}' event declaration names the listener "
                        . "service '{$listenerId}' that does not implement EventListener.",
                    );
                }

                $eventDispatcher->subscribe(
                    $eventName,
                    $listener,
                    new ListenerPriority(0),
                );
            }
        }

        return $eventDispatcher;
    }
}
