<?php

declare(strict_types=1);

namespace Zef\Framework\View;

use InvalidArgumentException;

/**
 * The template's logical identity: an extension-free name the caller
 * spells and every engine answer resolves its own way -- the native
 * answer's `.php` file under its templates root, a future Twig
 * answer's own loader namespace -- so a deployment that swaps engines
 * swaps resolutions, never names. The name is the identity that
 * survives the port boundary, the same doctrine the session port's
 * identifier and the durable lane's entry identity carry.
 *
 * The grammar is the lane's first wall, and it refuses by shape
 * rather than by enumeration: segments are `/`-separated, each
 * letter-or-digit led and carrying only letters, digits, dots,
 * underscores or hyphens, so a segment that begins with a dot never
 * constructs (`..` the traversal, `.` the self-reference, `.env` the
 * dotfile -- one refusal closes all three), an empty segment never
 * constructs (the leading `/`, the trailing `/` and the doubled `//`
 * alike), and a backslash, a colon, a NUL byte or any control
 * character never constructs because the segment class never carried
 * them. The BYTE ceilings mirror the filesystem's own corridors (the
 * name at most 4096 bytes, each segment at most 255) -- the same
 * byte-counting spelling the migrations lane's name corridor uses.
 *
 * The second wall -- the resolution fold's realpath containment
 * ({@see \Zef\Framework\Adapters\View\ViewTemplateFile}) -- trusts
 * this grammar not at all: a name could never spell an escape, and
 * the containment still refuses one, because two walls that trust
 * each other are one wall.
 */
final readonly class ViewName
{
    /** The whole-name corridor: at most 4096 bytes, the filesystem's own path ceiling. */
    private const int NAME_BYTES = 4096;

    /** The per-segment corridor: at most 255 bytes, the filesystem's own component ceiling. */
    private const int SEGMENT_BYTES = 255;

    private string $name;

    /**
     * @throws InvalidArgumentException when the name is empty, exceeds the byte
     *                                  ceilings, or a segment is empty or outside the grammar
     */
    public function __construct(string $name)
    {
        if ($name === '') {
            throw new InvalidArgumentException('A view name must not be empty.');
        }

        if (mb_strlen($name, '8bit') > self::NAME_BYTES) {
            throw new InvalidArgumentException(
                'A view name must be at most ' . self::NAME_BYTES . ' bytes, got '
                . mb_strlen($name, '8bit') . ' bytes.',
            );
        }

        foreach (explode('/', $name) as $segment) {
            if ($segment === '') {
                throw new InvalidArgumentException(
                    'A view name must not carry an empty segment.',
                );
            }

            if (mb_strlen($segment, '8bit') > self::SEGMENT_BYTES) {
                throw new InvalidArgumentException(
                    'A view name segment must be at most ' . self::SEGMENT_BYTES
                    . ' bytes, got ' . mb_strlen($segment, '8bit') . ' bytes.',
                );
            }

            if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/D', $segment) !== 1) {
                throw new InvalidArgumentException(
                    'A view name segment must be letter-or-digit led and carry only'
                    . " letters, digits, dots, underscores or hyphens, got '" . $segment . "'.",
                );
            }
        }

        $this->name = $name;
    }

    /**
     * The canonical spelling -- the one string an answer resolves, a
     * partial renders and a refusal names.
     */
    public function spelling(): string
    {
        return $this->name;
    }
}
