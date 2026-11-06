<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

use InvalidArgumentException;

/**
 * The migration name's own grammar fold: the corridor a scaffolding
 * spelling must clear before it becomes a migration -- lowercase
 * words of letters and digits (a word may start with a digit, the
 * name itself must start with a letter, so a name never reads like
 * a version stamp) separated by single spaces or hyphens, at most
 * the registration grammar's own 128-byte ceiling ({@see
 * MigrationRegistration}).
 *
 * The separator is normalised, never invented: a name spelled with
 * hyphens and the same name spelled with spaces are ONE migration,
 * and the value answers the three spellings the template composes
 * from it -- the canonical name (words joined by single spaces, the
 * human-readable spelling {@see MigrationInterface::name()} carries),
 * the file slug (words joined by single hyphens, the file grammar's
 * own spelling) and the class-name words (capitalised, the version
 * stamp's neighbour inside the class name the template prints).
 *
 * The corridor refuses as a construction value ({@see
 * \InvalidArgumentException}, the CLI command name's own doctrine)
 * at the one construction verb, so every consumer of a
 * {@see MigrationName} holds a spelling the grammar already
 * accepted -- the template's own entries validate exactly once, at
 * the value's own boundary, and the validation's removal is visible
 * in the entry's own answer, never masked by a nested
 * re-validation.
 *
 * @internal
 */
final readonly class MigrationName
{
    private const string NAME_RE = '/^[a-z][a-z0-9]*(?:[- ][a-z0-9]+)*$/';

    private const int MAX_NAME_BYTES = 128;

    /**
     * @param list<string> $words the accepted spelling's own words
     */
    private function __construct(
        private array $words
    ) {
    }

    /**
     * The one construction verb: the spelling the grammar accepts,
     * or the refusal naming it.
     */
    public static function fromSpelling(string $name): self
    {
        if (
            preg_match(self::NAME_RE, $name) !== 1
            || mb_strlen($name, '8bit') > self::MAX_NAME_BYTES
        ) {
            throw new InvalidArgumentException(
                'Migration name must be lowercase words of letters and digits'
                . ' separated by single spaces or hyphens, 1..' . self::MAX_NAME_BYTES . ' bytes'
                . " (spelled '{$name}').",
            );
        }

        $words = preg_split('/[- ]/', $name);
        \assert(\is_array($words));

        return new self($words);
    }

    /**
     * The canonical name: the words joined by single spaces -- the
     * human-readable spelling the generated migration's own name
     * verb answers.
     *
     * @return non-empty-string the words joined by single spaces
     */
    public function canonical(): string
    {
        $canonicalName = implode(' ', $this->words);
        // The construction verb's corridor left at least one word,
        // so the joined spelling is never the empty one.
        \assert($canonicalName !== '');

        return $canonicalName;
    }

    /**
     * The file slug: the words joined by single hyphens -- the file
     * grammar's own spelling, `20261106143000-create-users.php`.
     */
    public function slug(): string
    {
        return implode('-', $this->words);
    }

    /**
     * The class-name words: the words capitalised, concatenated --
     * the version stamp's neighbour inside the class name the
     * template prints (`CreateUsers` beside `20261106143000`).
     */
    public function classNameWords(): string
    {
        $classNameWords = '';

        foreach ($this->words as $word) {
            $classNameWords .= ucfirst($word);
        }

        return $classNameWords;
    }
}
