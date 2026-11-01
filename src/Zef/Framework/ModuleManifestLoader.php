<?php

declare(strict_types=1);

namespace Zef\Framework;

use Throwable;
use Zef\Framework\Modules\ModuleException;

/**
 * The loader for the module side's PHP files -- a `module.php` manifest, a
 * configuration schema -- every one of which must return an array.
 *
 * The module engine keeps its grammar folds pure (a payload in, a
 * declaration out); this fold is the one place the engine touches the
 * engine's own file system, and it fails closed in every direction: a
 * missing file, a file that throws while loading, and a file that returns
 * anything but an array are refusals that name the path and the subject --
 * never a warning, a null, or a silent skip. The configuration layer's own
 * file-loader posture, spelled at the module lane's smaller scale.
 */
final readonly class ModuleManifestLoader
{
    /**
     * Loads the PHP file at the absolute path and answers its array
     * payload.
     *
     * @return array<array-key, mixed>
     *
     * @throws ModuleException when the file does not exist, throws while
     *                         loading, or returns a non-array
     */
    public function load(string $absolutePath, string $subject): array
    {
        if (!is_file($absolutePath)) {
            throw new ModuleException(
                "The {$subject} '{$absolutePath}' does not exist (a module-side "
                . 'path is spelled absolute, rooted at its own directory).',
            );
        }

        try {
            $payload = require $absolutePath;
        } catch (Throwable $throwable) {
            throw new ModuleException(
                "The {$subject} '{$absolutePath}' failed to load: "
                . $throwable->getMessage() . '.',
                0,
                $throwable,
            );
        }

        if (!\is_array($payload)) {
            throw new ModuleException(
                "The {$subject} '{$absolutePath}' must return an array; "
                . get_debug_type($payload) . ' arrived.',
            );
        }

        return $payload;
    }
}
