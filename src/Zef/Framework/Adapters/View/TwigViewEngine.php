<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\View;

use InvalidArgumentException;
use Override;
use Throwable;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Loader\FilesystemLoader;
use Zef\Framework\View\ViewEngine;
use Zef\Framework\View\ViewException;
use Zef\Framework\View\ViewName;

/**
 * The presentation port's Twig answer: the optional adapter over the
 * HOST TREE'S OWN Twig, the adoption the owner's recorded direction
 * names and the port's own record spelled the landing for (KDD-VW1:
 * the zero-dependency gate keeps Twig out of `require` forever, so
 * the adoption lands the way the rate-limit family's phpredis
 * boundary already spells -- an optional adapter probing
 * `class_exists` on the host tree's own Twig, never a composer
 * dependency of the framework itself; the test side pins its own
 * presence in require-dev the way the export slice pinned its
 * meta-schema validator).
 *
 * The CONSTRUCTION is fail-closed at the one seam the boundary owns:
 * the templates root resolves through the lane's shared posture fold
 * ({@see ViewTemplateDirectory} -- existing, readable, non-symlink,
 * owner-only, outside the public document root, one spelling of the
 * WHERE/HOW-PRIVATE question for both answers), and the Twig
 * presence is probed BEFORE the environment composes -- a host tree
 * that never installed Twig is a deployment defect the constructor
 * names with the remediation spelled out ({@see PhpRedisConnection}'s
 * unavailable posture: an environment-refusal, the family's typed
 * root, never a class-not-found fatal a caller could do nothing
 * with). The probe reads the one class the answer composes
 * (`Twig\Environment`); the loader travels beside it on the same
 * composer package, so one probe answers the whole presence.
 *
 * The environment composes with the lane's ESCAPING DOCTRINE pinned
 * explicitly (`autoescape => 'html'`): Twig's own
 * auto-escape-on-output is the posture the port's record names as
 * satisfying the doctrine natively, and the explicit spelling keeps
 * a future Twig default change from silently moving the lane's
 * security posture. Every other environment posture stays Twig's
 * own default -- the answer ships no knob the census has no reader
 * for ({@see \Zef\Framework\ViewKnobs} lineage), and a deployment
 * wanting its own environment (a compiled-template cache, its own
 * options) composes an engine by hand over the {@see ViewEngine}
 * port, the way out every silent knob already spells.
 *
 * The RENDER answers the port's one verb over the loader's own
 * namespace: the extension-free name travels to the loader joined
 * with the answer's own `.twig` extension ({@see ViewName} -- the
 * identity that survives the port boundary; a deployment that
 * swaps engines swaps resolutions, never names), and the data bag
 * rides the Twig context VERBATIM -- the native corridor's reserved
 * names are the native scope's own ($_view and the extraction
 * carriers, {@see ViewVariables}), a corridor the Twig context does
 * not carry, so the bag inherits Twig's own context semantics
 * exactly as the native fold's docblock recorded it would.
 *
 * The WRAP discipline is the native answer's own law, replayed at
 * the Twig boundary: the lane's miss vocabulary stays uniform
 * across answers (a {@see LoaderError} translates into the lane's
 * own miss refusal -- the template named by the caller's own
 * extension-free spelling, the cause chained, never the loader's
 * path-bearing message as the refusal's own words), and every other
 * foreign throwable a template raised -- a {@see \Twig\Error\SyntaxError}
 * from bad authoring, a {@see \Twig\Error\RuntimeError} from a
 * failing payload, any engine failure beneath them -- is wrapped
 * into the family with the template named and the cause chained
 * (the native twin's mid-render message, byte-identical), so the
 * lane never leaks a raw Twig crash to the caller, the same
 * no-driver-type-leaks posture the database family spells.
 *
 * The nesting ledger the native answer carries ({@see
 * ViewRenderDepth}) is deliberately absent: the ledger bounds the
 * FACE-mediated recursion (a template rendering templates through
 * the engine's own handle back into the engine), and the Twig
 * answer hands its templates no face -- the render verb is not
 * reachable from the template side, so the recursion through the
 * port is bounded by construction, while the include-nesting a
 * template authors is Twig's own machinery the deployment chose
 * with the word (the same authoring trust the native answer extends
 * to a template's own function calls).
 */
final readonly class TwigViewEngine implements ViewEngine
{
    /** The Twig answer's own file extension -- the adapter's vocabulary, never the port's. */
    private const string TEMPLATE_EXTENSION = '.twig';

    private Environment $twigEnvironment;

    /**
     * @param string $templateDirectory the templates root (validated by
     *                                  {@see ViewTemplateDirectory}: existing, readable,
     *                                  non-symlink, owner-only, outside the public
     *                                  document root)
     *
     * @throws InvalidArgumentException when the root refuses the shared posture
     * @throws ViewException            when the host tree carries no Twig to compose over
     */
    public function __construct(string $templateDirectory)
    {
        $root = ViewTemplateDirectory::resolve($templateDirectory);

        if (!class_exists(Environment::class)) {
            throw new ViewException(
                "The views twig engine needs Twig on the host tree's own dependency tree:"
                . ' install twig/twig (the framework never carries it) or spell the'
                . " 'php' word (views.engine).",
            );
        }

        $this->twigEnvironment = new Environment(
            new FilesystemLoader($root),
            ['autoescape' => 'html'],
        );
    }

    #[Override]
    public function render(ViewName $viewName, array $data): string
    {
        try {
            return $this->twigEnvironment->render(
                $viewName->spelling() . self::TEMPLATE_EXTENSION,
                $data,
            );
        } catch (LoaderError $miss) {
            throw new ViewException(
                "The view template '" . $viewName->spelling() . "' does not answer"
                . ' under the templates root.',
                0,
                $miss,
            );
        } catch (Throwable $templateFailure) {
            throw new ViewException(
                "The view template '" . $viewName->spelling() . "' failed mid-render.",
                0,
                $templateFailure,
            );
        }
    }
}
