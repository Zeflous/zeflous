<?php

/**
 * The suite's own bootstrap: the Composer autoloader plus the one
 * namespaced function double that must exist before any test runs.
 *
 * The double matters because of the engine's call-site binding: an
 * unqualified call inside a namespace resolves the namespace's own
 * function when it exists at the call site's first execution, and the
 * resolved binding sticks for the process lifetime -- so a middleware
 * call executed before the double is defined would pin itself to the
 * global engine function and stay pinned even after the double loads.
 * Loading the double here, before the first test, keeps every
 * unqualified hrtime call in the middleware namespace resolving to the
 * double from the first execution on -- and the double falls through
 * to the real engine hrtime whenever no test queued ticks, so every
 * other suite keeps its real timing.
 *
 * @internal
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

require __DIR__ . '/Support/Middleware/hrtime_double.php';
