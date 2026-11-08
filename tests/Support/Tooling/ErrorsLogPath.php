<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use InvalidArgumentException;
use Zef\Framework\Middleware\ErrorStorageWiring;
use Zef\Framework\Storage\StorageLane;
use Zef\Framework\Storage\StorageLayout;

/**
 * The errors family's log-address fold: where the JSONL log the report
 * and clear verbs read lives, lifted out of `bin/zef`'s joined branch
 * (#717's structural step).
 *
 * The one-way precedence is the storage integration's own (#653): a
 * positional path the operator spelled wins, then the `ZEF_ERROR_LOG`
 * environment spelling, then the storage layout's derived `logs`-lane
 * address with the canonical file name -- a refused tree surfacing as
 * the layout's own `InvalidArgumentException` (the command renders it
 * with its exit 1), never a guessed default. The symlink guard rides the
 * fold so both verbs refuse the same way: a symlinked log path is a
 * redirection the operator did not spell, exit 2's own refusal.
 */
final class ErrorsLogPath
{
    /**
     * Resolves the log path over the one-way precedence.
     *
     * @param ?string $positionalPath the path the operator spelled
     *                                positionally, when they did
     *
     * @return string the resolved log path
     *
     * @throws InvalidArgumentException when the storage layout refuses the
     *                                  derived address (the command's exit 1)
     * @throws ToolingException         when the resolved path is a symlink
     *                                  (the command's exit 2)
     */
    public static function resolved(?string $positionalPath): string
    {
        if (\is_string($positionalPath)) {
            $path = $positionalPath;
        } else {
            $environmentErrorLog = getenv('ZEF_ERROR_LOG');

            if (\is_string($environmentErrorLog) && $environmentErrorLog !== '') {
                $path = $environmentErrorLog;
            } else {
                $environmentStorageRoot = getenv('ZEF_STORAGE_ROOT');
                $storageRoot = \is_string($environmentStorageRoot) && $environmentStorageRoot !== ''
                    ? $environmentStorageRoot
                    : \dirname(__DIR__, 3) . '/files';

                $path = StorageLayout::resolve($storageRoot)->path(StorageLane::Logs)
                    . '/' . ErrorStorageWiring::LOG_FILE_NAME;
            }
        }

        if (is_link($path)) {
            throw new ToolingException('Refusing to follow a symlink log path.');
        }

        return $path;
    }
}
