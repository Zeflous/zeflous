<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Adapters\Event\FileDeliveryStore;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Event\DeferredDeliveryStore;
use Zef\Framework\Middleware\StorageRootDeclaration;
use Zef\Framework\Storage\StorageLane;
use Zef\Framework\Storage\StorageLayout;

/**
 * The events family's wiring onto the `files/**` route map: the
 * composition fold that answers the durable delivery store's
 * wired default from the frozen configuration -- the `queue`
 * lane's first writer, the reservation three decision records
 * carried (KDD-DU10, KDD-DQ8, KDD-CS7) closed by the pull request
 * that lands this class.
 *
 * The question the fold owns is the store the knob's word
 * composes, spelled at the profile's own service identity
 * (`events.deferred.store`, the registration {@see
 * FrameworkDefaults} declares over this fold): the {@see
 * EventDeliveryDeclaration} census reads what the host declared,
 * and the derivation answers the address -- an explicitly spelled
 * `events.deferred.path` verbatim, else the storage layout's
 * `queue` lane over the declared `storage.root`, the same one-way
 * precedence every family integration in this cross-cut keeps.
 * The `file` word with neither an address nor a declared root is
 * the named halt the session family's combination refusal spelled
 * first ({@see Middleware\SessionFactory}): a directory the host
 * never asked for is never silently defaulted.
 *
 * The walk is the layout's own over the WHOLE tree ({@see
 * StorageLayout::resolve()}), fail-closed with the refusing seam
 * named -- the events family answers to the same map the
 * inspector prints, never a one-lane shortcut that could drift
 * from it (KDD-ES4's doctrine, carried the way the error, session
 * and cache families carry it). The privacy posture stays where
 * it already lives ({@see Adapters\Event\DeliveryStoreDirectory})
 * because this fold answers WHERE, never HOW PRIVATE -- two
 * questions, two seams, the route map's own boundary, so an
 * explicitly spelled address is judged by the store's own
 * constructor posture rather than re-spelled here.
 *
 * The silent declaration (the empty store word) is a refusal with
 * the way out spelled, never a null answer: a host resolving the
 * wired default's service identity is asking for the framework's
 * store, and the honest answer to an undeclared ask is the knob's
 * own grammar -- spell the word, or compose the arm by hand over
 * the port ({@see Event\EventDispatcher::durableDelivery()}) the
 * way every composition before this fold did.
 *
 * @api
 */
final readonly class EventStorageWiring
{
    /**
     * Answers the wired default store from the frozen repository:
     * the `file` word's filesystem answer over the address the
     * declaration and the route map spell together.
     *
     * @throws ConfigException when the declaration is silent or
     *                         malformed, the `file` word is spelled without an address to
     *                         compose over, or the storage map the derivation reads is
     *                         malformed
     */
    public static function store(Config $config): DeferredDeliveryStore
    {
        $eventDeliveryDeclaration = EventDeliveryDeclaration::fromConfig($config);

        if (!$eventDeliveryDeclaration->wantsWiredStore()) {
            throw new ConfigException(
                'The events.deferred.store wired default is not declared:'
                . " spell the 'file' word (events.deferred.store) or compose"
                . ' the durable arm over your own DeferredDeliveryStore.',
            );
        }

        return new FileDeliveryStore(
            self::directory($eventDeliveryDeclaration->path, $config),
            $eventDeliveryDeclaration->capacity,
        );
    }

    /**
     * The file answer's effective directory: the explicitly
     * spelled `events.deferred.path` verbatim, the storage
     * layout's `queue` lane address when the host declared a root
     * and left the knob empty, and the combination refusal when
     * the `file` word is spelled with neither.
     *
     * @throws ConfigException when the `file` word is spelled
     *                         without a directory to compose over, or the storage map the
     *                         derivation reads is malformed
     */
    private static function directory(string $declaredPath, Config $config): string
    {
        if ($declaredPath !== '') {
            return $declaredPath;
        }

        $root = StorageRootDeclaration::root($config);

        if ($root === null) {
            throw new ConfigException(
                'The events.deferred file store needs a directory: declare'
                . ' storage.root or spell events.deferred.path.',
            );
        }

        return StorageLayout::resolve($root)->path(StorageLane::Queue);
    }
}
