<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The session family's store vocabulary: the closed set of storage
 * drivers the `http.session.store` knob may name -- the composition
 * seam's ({@see \Zef\Framework\Middleware\SessionFactory}) own
 * question, read through the family's policy so every knob is read
 * strictly where the census can refuse a typo.
 *
 * The reading is case-insensitive (the environment's spelling
 * folds) and fail-closed, mirroring {@see RateLimitStores}: an
 * unknown name is rejected with the value spelled out, because the
 * driver a deployment named is a statement of storage topology --
 * `fle` is a typo of `file`, not a driver, and a deployment that
 * believes its sessions survive the process while the composed
 * middleware quietly kept the in-memory default is exactly the
 * silent topology miss the strict census exists to catch.
 *
 * The two words the vocabulary carries today: `memory` -- the
 * zero-dependency engine's own {@see InMemorySessionAdapter}, whose
 * honest scope is the process's own lifetime (the single-worker
 * deployment, the test suite, the local bootstrap); and `file` --
 * the filesystem answer over the same port ({@see
 * \Zef\Framework\Adapters\Session\FileSessionAdapter}), the
 * identifier-keyed record files whose directory the storage layout's
 * `sessions` lane provisions. The Redis and table drivers remain
 * named reservations over the same port, each a slice of its own
 * when a deployment asks for one.
 */
final class SessionStores
{
    /** The process-local zero-dependency driver ({@see InMemorySessionAdapter}). */
    public const string MEMORY = 'memory';

    /** The filesystem driver ({@see \Zef\Framework\Adapters\Session\FileSessionAdapter}). */
    public const string FILE = 'file';

    /**
     * Reads one store knob's value through the closed vocabulary.
     *
     * @throws HttpException when the value is not one of the known stores
     */
    public static function read(mixed $value): string
    {
        if (\is_string($value)) {
            $folded = mb_strtolower($value);

            if (\in_array($folded, [self::MEMORY, self::FILE], true)) {
                return $folded;
            }

            throw new HttpException(
                'The http.session store knob must be one of the known stores '
                . "(memory, file), '" . $value . "' given.",
            );
        }

        throw new HttpException(
            'The http.session store knob must be one of the known stores '
            . '(memory, file), ' . get_debug_type($value) . ' given.',
        );
    }
}
