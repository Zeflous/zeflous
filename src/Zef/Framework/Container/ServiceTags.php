<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * A definition's declared tag list -- the grouping label set of the
 * tags & grouping lane (ROADMAP, "CONTAINER" area 6: a tag carries no
 * built-in meaning; the meaning belongs to the logic that reads it).
 *
 * The grammar is deliberately narrow, the same discipline a job header
 * name carries: one to 128 characters of ASCII letters, digits, dots,
 * underscores and hyphens. The raw spelling is judged as declared --
 * surrounding whitespace is not trimmed away, because a tag the
 * declaration did not literally spell is a repair this grammar refuses
 * to guess (the reference lineage trims first; that silently rewrites
 * the declaration, and a rewritten declaration is a swallowed case).
 *
 * A repeated tag is the ambiguity it is: refused naming the repeat,
 * never quietly collapsed -- the declaration that survives boot is the
 * declaration the host actually wrote.
 */
final readonly class ServiceTags
{
    /** The tag grammar, spelled once for the refusals that quote it. */
    private const string GRAMMAR = '[A-Za-z0-9._-]{1,128}';

    /** @var list<string> */
    private array $declared;

    /**
     * @param array<array-key, mixed> $tags the raw declared list; every
     *                                      entry is validated here
     */
    public function __construct(array $tags = [])
    {
        $declared = [];
        foreach ($tags as $tag) {
            if (!\is_string($tag)) {
                throw new DefinitionException(
                    'A service tag must match ' . self::GRAMMAR . ', ' . get_debug_type($tag) . ' given.',
                );
            }

            self::assert($tag);

            if (\in_array($tag, $declared, true)) {
                throw new DefinitionException(
                    "The service tag '{$tag}' is declared more than once (a tag is declared once per definition).",
                );
            }

            $declared[] = $tag;
        }

        $this->declared = $declared;
    }

    /**
     * Judges one tag spelling against the grammar every tag must
     * match -- the single authority the declaration parser and the
     * lookup surface both consult, so a lookup can never accept a
     * spelling a declaration was refused (or the converse).
     */
    public static function assert(string $tag): void
    {
        if (preg_match('/^' . self::GRAMMAR . '$/', $tag) !== 1) {
            throw new DefinitionException(
                'A service tag must match ' . self::GRAMMAR . ", '{$tag}' given.",
            );
        }
    }

    /**
     * The declared tags, in declaration order.
     *
     * @return list<string>
     */
    public function all(): array
    {
        return $this->declared;
    }

    /**
     * Whether the declaration carries one exact tag.
     */
    public function carries(string $tag): bool
    {
        return \in_array($tag, $this->declared, true);
    }
}
