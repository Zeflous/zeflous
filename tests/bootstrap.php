<?php

/**
 * The suite's own bootstrap: the Composer autoloader plus the namespaced
 * function doubles that must exist before any test runs.
 *
 * The doubles matter because of the engine's call-site binding: an
 * unqualified call inside a namespace resolves the namespace's own
 * function when it exists at the call site's first execution, and the
 * resolved binding sticks for the process lifetime -- so a middleware
 * or boundary call executed before the double is defined would pin
 * itself to the global engine function and stay pinned even after the
 * double loads. Loading every double here, before the first test,
 * keeps each shadowed call resolving to its double from the first
 * execution on -- and every double falls through to the real engine
 * function whenever no test armed an answer, so every other suite
 * keeps its real behaviour: the toolbar keeps its real timing, and
 * the upload and stream transfer paths that share the replay
 * boundary's namespace keep their real writes.
 *
 * @internal
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

require __DIR__ . '/Support/Http/replay_snapshot_function_doubles.php';

require __DIR__ . '/Support/Adapters/Event/delivery_file_function_doubles.php';

require __DIR__ . '/Support/Adapters/Session/session_file_function_doubles.php';

require __DIR__ . '/Support/Adapters/Redis/resp_socket_function_doubles.php';

require __DIR__ . '/Support/Logging/json_line_function_doubles.php';

require __DIR__ . '/Support/Middleware/hrtime_double.php';
