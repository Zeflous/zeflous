<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\ConfigException;

/**
 * The view family's engine vocabulary: the closed set of engine
 * words the `views.engine` knob may name -- the wired default's own
 * question, read through the family's declaration ({@see
 * ViewDeclaration}) so every knob is read strictly where the census
 * can refuse a typo.
 *
 * The reading folds the environment's spelling ({@see
 * Http\SessionStores} and {@see EventDeliveryStores} lineages: an
 * `APP_VIEWS__ENGINE=PHP` overlay reads as the `php` word it meant
 * to spell) and is fail-closed: an unknown word is rejected with
 * the value spelled out, because the engine a deployment named is a
 * statement of presentation topology -- a `pfp` is a typo of `php`,
 * not an engine, and a deployment that believes its templates render
 * through the word while the resolution keeps refusing is exactly
 * the silent topology miss the strict census exists to catch.
 *
 * The words the vocabulary carries today: the empty string -- the
 * silent posture, no wired default declared, the presentation port
 * stays opt-in exactly as the unwired lane's own slice carried it;
 * `php` -- the native answer over the rendering port ({@see
 * \Zef\Framework\Adapters\View\PhpViewEngine}), the zero-dependency
 * proof of the port; and `twig` -- the Twig answer over the HOST
 * TREE'S OWN Twig ({@see Adapters\View\TwigViewEngine}),
 * the adoption the owner's recorded direction names, landed the way
 * KDD-VW1's recorded direction spells: over `class_exists` on the
 * host tree's own Twig, never a composer dependency of the framework
 * (the zero-dependency gate keeps Twig out of `require` forever;
 * the test side pins its own presence in require-dev the way the
 * export slice pinned its meta-schema validator).
 */
final class ViewEngines
{
    /** The native answer over the rendering port ({@see Adapters\View\PhpViewEngine}). */
    public const string PHP = 'php';

    /** The Twig answer over the host tree's own Twig ({@see Adapters\View\TwigViewEngine}). */
    public const string TWIG = 'twig';

    /**
     * Reads one engine knob's value through the closed vocabulary.
     *
     * @return string the folded word: '' the silent posture, 'php'
     *                the native answer, 'twig' the Twig answer over
     *                the host tree's own Twig
     *
     * @throws ConfigException when the value is not a string, or is
     *                         a word the wiring does not know
     */
    public static function read(mixed $value): string
    {
        if (!\is_string($value)) {
            throw new ConfigException('views.engine must be a string.');
        }

        $folded = mb_strtolower($value);

        if (\in_array($folded, ['', self::PHP, self::TWIG], true)) {
            return $folded;
        }

        throw new ConfigException(
            "The views.engine word '" . $value . "' is not one the wiring"
            . ' knows (known words: ' . self::PHP . ', ' . self::TWIG . ').',
        );
    }
}
