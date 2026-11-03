<?php

declare(strict_types=1);

namespace Zef\Framework\View;

/**
 * The presentation lane's rendering port: the question a host asks in
 * one sentence -- "spell this template against this data, answered as
 * text" -- spelled as the one verb every template engine must answer.
 * The View lane owns the QUESTION and stays dependency-free (the
 * port-first posture every lane in this tree carries: the Event
 * lane's dispatcher, the Database lane's connection, the HTTP lane's
 * session storage); the ANSWER is an adapter's own business, and the
 * first one lives in the Adapters lane over plain PHP templates
 * ({@see \Zef\Framework\Adapters\View\PhpViewEngine}).
 *
 * The Twig answer the owner's recorded direction names is the lane's
 * own future slice over this same port, and the port's whole shape
 * is deliberately the one that answer needs: the name is
 * extension-free (each answer resolves it its own way -- the native
 * answer's `.php` file under its templates root, the Twig answer's
 * own loader namespace), the data bag is an honest
 * `array<string, mixed>` with no corridor the port itself promises,
 * and the escaping posture is owned by the ANSWER, never the caller.
 * The zero-dependency gate keeps Twig out of `require` forever
 * (CONTRIBUTING.md rule 1), so that adoption lands the way the
 * rate-limit family's phpredis boundary already spells: an optional
 * adapter probing `class_exists` on the host tree's own Twig, never
 * a composer dependency of the framework itself.
 *
 * The ESCAPING posture deserves its own sentence because it is the
 * lane's security doctrine: every value a template interpolates must
 * cross the engine's own escaping judgment -- the native answer
 * hands every template the one escape fold it spells values through
 * ({@see \Zef\Framework\Adapters\View\TemplateFace::escape()}), a
 * Twig answer's own auto-escape-on-output satisfies the posture
 * natively -- and the port promises the CALLER nothing about
 * escaping, because the caller hands values, the engine answers
 * text, and only the answer's author knows what its template syntax
 * means.
 *
 * Failures are the lane's whole story and travel the lane's typed
 * family ({@see ViewException}): a name the grammar refused never
 * constructs ({@see ViewName}), and a render-time miss, scope
 * refusal or mid-render crash raises the typed refusal the error
 * boundary renders as the server error it is -- the template name is
 * the application's own addressing, never the client's resource, so
 * a miss is the developer's defect (the internal posture), not a 404
 * the client earned.
 */
interface ViewEngine
{
    /**
     * Renders one template against one data bag, answered as text.
     *
     * The answer owns the whole rendering discipline: the buffers it
     * opens it also closes on every path (a template that fails
     * mid-render never leaks partial output), the nesting it permits
     * is bounded (a template rendering templates through the answer's
     * own face cannot recurse without end), and the template's
     * address resolves only inside the root the answer was composed
     * over.
     *
     * @param ViewName             $viewName the template's logical identity, extension-free
     * @param array<string, mixed> $data     the bag the template spells its values from
     *
     * @throws ViewException when the template does not answer under the answer's
     *                       root, escapes it, refuses the data bag's shape, or fails mid-render
     */
    public function render(ViewName $viewName, array $data): string;
}
