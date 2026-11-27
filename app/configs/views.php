<?php

declare(strict_types=1);

/*
 * The view family's configuration file: everything under this key
 * is the `views` map the framework's wired default reads -- the
 * presentation port's declaration surface.
 *
 * The view family
 * ---------------
 *
 *   'engine'                     (string, default '')
 *       The wired default's engine word over the rendering port
 *       (`ViewEngine`): '' (the shipped default -- no wired default
 *       is declared, the presentation port stays opt-in and a host
 *       composes its own engine at the composition seam), 'php'
 *       (the native answer, `PhpViewEngine` over plain PHP
 *       templates -- the zero-dependency proof of the port) or
 *       'twig' (the Twig answer, `TwigViewEngine` over the HOST
 *       TREE'S OWN Twig -- the adoption the owner's recorded
 *       direction names, landed over `class_exists` on the host
 *       tree's own Twig, never a composer dependency of the
 *       framework: the adapter's own boundary probes the presence
 *       and names the remediation, the test side pins its own
 *       presence in require-dev).
 *
 *       The word is read through a closed vocabulary: a typo is a
 *       named halt, never a silently defaulted engine.
 *
 *       Resolving the framework's `views.engine` service while
 *       this knob stays empty is the halt that names the way out:
 *       spell the 'php' word, or compose the engine by hand over
 *       the port.
 *
 *   'path'                       (string, default '')
 *       The wired engine's templates root, whichever word composes
 *       over it: the deployment's own explicit address, judged by
 *       the answers' own privacy posture -- existing, readable,
 *       non-symlink, owner-only, outside the public document root
 *       -- a root the engines only ever answer includes from,
 *       never write into (the read-only root doctrine). The
 *       `files/**` route map owns no `views` lane because templates
 *       are the application's own source, not state the framework
 *       writes, so there is nothing to derive: a wired word with
 *       an empty path is a named halt -- a directory the host never
 *       spelled is never silently defaulted.
 *
 * The escaping posture, the render discipline and the data key
 * corridor stay deliberately OUT of this block: they are the
 * answer's own business (the port promises the caller nothing
 * about escaping -- escaping is opt-in at each interpolation),
 * consumed at the engine's own seam -- a knob without a reader is
 * a dead key.
 */

return [
    'engine' => '',
    'path' => '',
];
