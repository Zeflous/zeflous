<?php

declare(strict_types=1);

namespace Zef\Framework\Console;

use InvalidArgumentException;

/**
 * A command name's grammar: the spelling an operator types after
 * `bin/zef` -- the framework's own verbs (`route:list`,
 * `migrations:status`, `errors:replay`) spell the convention this fold
 * judges by: lowercase labels, hyphens inside a label, at most one
 * colon separating the labels, and a bounded whole.
 *
 * The grammar is deliberately narrower than a free-form string because
 * a command name is typed by hand: every character this fold refuses
 * is a character an operator cannot mistype into a different command.
 * The bounds keep the `bin/zef list` directory's name column honest
 * (a name that outgrows the terminal is a rendering defect this fold
 * refuses upstream) and keep the dispatch matching a single
 * comparison, never a normalisation.
 *
 * Construction-time grammar violations refuse with
 * `InvalidArgumentException` -- the construction-value doctrine the
 * storage lane's validators carry (`CONTRIBUTING.md` rule 17): a
 * command name is a value the host or module author declares, not a
 * request the runtime parses.
 */
final readonly class CliCommandName
{
    /**
     * The whole-name bound: one verb, optionally one colon, labels of
     * lowercase alphanumerics and hyphens -- the exact surface the
     * framework's own verbs spell.
     */
    private const string PATTERN = '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*(?::[a-z][a-z0-9]*(?:-[a-z0-9]+)*)?$/D';

    /**
     * The length bound the `bin/zef list` directory's name column
     * renders without truncation.
     */
    private const int MAXIMUM_LENGTH = 64;

    /**
     * @param string $name the command's own verb spelling as declared
     *                     -- the grammar fold refuses the empty spelling itself
     */
    public function __construct(public string $name)
    {
        if ($this->name === '') {
            throw new InvalidArgumentException('The command name must not be empty.');
        }

        if (mb_strlen($this->name) > self::MAXIMUM_LENGTH) {
            throw new InvalidArgumentException(\sprintf(
                "The command name '%s' is longer than the %d characters the CLI directory renders.",
                $this->name,
                self::MAXIMUM_LENGTH,
            ));
        }

        if (preg_match(self::PATTERN, $this->name) !== 1) {
            throw new InvalidArgumentException(\sprintf(
                "The command name '%s' must be lowercase labels of letters, digits and hyphens,"
                . " separated by at most one colon (the 'route:list' convention).",
                $this->name,
            ));
        }
    }

    /**
     * Answers the spelling an operator types -- the value the dispatch
     * matches and the directory renders, carried verbatim.
     */
    public function toString(): string
    {
        return $this->name;
    }
}
