<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\View;

use InvalidArgumentException;
use Override;
use Throwable;
use Zef\Framework\View\ViewEngine;
use Zef\Framework\View\ViewException;
use Zef\Framework\View\ViewName;

/**
 * The presentation port's first answer: the native PHP-template
 * engine -- the zero-dependency proof of the port, the same role
 * the in-memory stores play in the session and rate-limit families.
 * One root the engine is composed over, one face handed to every
 * template, one scope per render, and the whole discipline spelled
 * by the folds the engine composes: the root posture
 * ({@see ViewTemplateDirectory}), the resolution and its containment
 * ({@see ViewTemplateFile}), the data key corridor ({@see ViewVariables}),
 * the nesting ledger ({@see ViewRenderDepth}), the buffering
 * discipline ({@see ViewRenderScope}) and the template's own handle
 * ({@see TemplateFace}).
 *
 * The engine is READONLY while its ledger is not: the composition's
 * own properties never reassign (the root and the ledger are
 * constructed once), and the mutable depth counter the ledger owns
 * mutates behind its own fold -- the same readonly-over-mutable
 * posture the PDO adapter's connection carries over its
 * transaction-depth fold.
 *
 * The WRAP discipline is the engine's own law: the family's own
 * refusals travel VERBATIM (a miss names the template that missed,
 * a partial's refusal names the partial -- the deepest failure's
 * own words reach the caller), and every foreign throwable a
 * template raised is wrapped into the family with the template
 * named and the cause chained -- the lane never leaks a raw
 * template crash to the caller, the same no-driver-type-leaks
 * posture the database family spells over its PDO mapping.
 */
final readonly class PhpViewEngine implements ViewEngine
{
    private string $templatesRoot;

    private ViewRenderDepth $viewRenderDepth;

    /**
     * @param string $templateDirectory the templates root (validated by
     *                                  {@see ViewTemplateDirectory}: existing, readable,
     *                                  non-symlink, owner-only, outside the public
     *                                  document root)
     *
     * @throws InvalidArgumentException when the root refuses the posture
     */
    public function __construct(string $templateDirectory)
    {
        $this->templatesRoot = ViewTemplateDirectory::resolve($templateDirectory);
        $this->viewRenderDepth = new ViewRenderDepth();
    }

    #[Override]
    public function render(ViewName $viewName, array $data): string
    {
        ViewVariables::guard($data);

        $file = ViewTemplateFile::resolve($this->templatesRoot, $viewName);

        $this->viewRenderDepth->enter($viewName);

        try {
            try {
                return ViewRenderScope::render($file, new TemplateFace($this), $data, $viewName);
            } catch (ViewException $viewException) {
                throw $viewException;
            } catch (Throwable $templateFailure) {
                throw new ViewException(
                    "The view template '" . $viewName->spelling() . "' failed mid-render.",
                    0,
                    $templateFailure,
                );
            }
        } finally {
            $this->viewRenderDepth->leave();
        }
    }
}
