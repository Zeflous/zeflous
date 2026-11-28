<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\ConfigException;

/**
 * The events family's store vocabulary: the closed set of store
 * words the `events.deferred.store` knob may name -- the wired
 * default's own question, read through the family's declaration
 * ({@see EventDeliveryDeclaration}) so every knob is read strictly
 * where the census can refuse a typo.
 *
 * The reading folds the environment's spelling ({@see
 * Http\SessionStores} and {@see Http\RateLimitStores} lineages: an
 * `APP_EVENTS__DEFERRED__STORE=DATABASE` overlay reads as the
 * `database` word it meant to spell) and is fail-closed: an
 * unknown word is rejected with the value spelled out, because the
 * store a deployment named is a statement of persistence topology --
 * `databse` is a typo of `database`, not a store, and a deployment
 * that believes its deferred entries survive the process while the
 * resolution keeps refusing is exactly the silent topology miss
 * the strict census exists to catch.
 *
 * The words the vocabulary carries today: the empty string -- the
 * silent posture, no wired default declared, the durable arm stays
 * opt-in exactly as `EventDispatcher::durableDelivery()` spells it
 * structurally; `file` -- the filesystem answer over the
 * `DeferredDeliveryStore` port ({@see
 * \Zef\Framework\Adapters\Event\FileDeliveryStore}), the `queue`
 * lane's first writer; and `database` -- the database family's
 * answer over the same port ({@see
 * \Zef\Framework\Adapters\Event\PdoDeliveryStore}), composed over
 * the named connection the `events.deferred.connection` knob
 * spells (the default connection when the knob stays silent), the
 * word the transactional/outbox lane's own slice lands -- the
 * cross-process answers' first wired spelling, the Redis and bus
 * transports the roadmap names still composed by hand over the
 * port until a deployment's ask spells their words.
 */
final class EventDeliveryStores
{
    /** The filesystem answer over the `queue` lane ({@see Adapters\Event\FileDeliveryStore}). */
    public const string FILE = 'file';

    /** The database answer over the named connection ({@see Adapters\Event\PdoDeliveryStore}). */
    public const string DATABASE = 'database';

    /**
     * Reads one store knob's value through the closed vocabulary.
     *
     * @return string the folded word: '' the silent posture, 'file'
     *                the filesystem answer, 'database' the database
     *                family's answer
     *
     * @throws ConfigException when the value is not a string, or is
     *                         a word the wiring does not know
     */
    public static function read(mixed $value): string
    {
        if (!\is_string($value)) {
            throw new ConfigException('events.deferred.store must be a string.');
        }

        $folded = mb_strtolower($value);

        if (\in_array($folded, ['', self::FILE, self::DATABASE], true)) {
            return $folded;
        }

        throw new ConfigException(
            "The events.deferred.store word '" . $value . "' is not one the"
            . ' wiring knows (known words: ' . self::FILE . ', '
            . self::DATABASE . ').',
        );
    }
}
