<?php

declare(strict_types=1);

namespace Zef\Framework\Console;

use InvalidArgumentException;

/**
 * One command declaration's entry: the name an operator types, the
 * one-line description the CLI directory renders beside it, and the
 * container service the dispatch resolves the handler through.
 *
 * The entry is built over a map the Standard CLI Schema has already
 * judged ({@see \Zef\Framework\Config\Schema\CliSchema} answers the
 * shape question: the three fields present and strings); this fold
 * owns the fields' own bounds -- the display safety the schema
 * deliberately does not carry. A description with a control byte
 * would ride the operator's terminal verbatim (the error report's
 * own display lesson: an escape sequence inside a stored field is a
 * rendering defect), so it is refused here, at the value's
 * construction, never stripped at render time -- a description whose
 * only content was control bytes would otherwise render as an empty
 * column a script cannot see.
 *
 * Construction-time violations refuse with `InvalidArgumentException`
 * -- the construction-value doctrine ({@see CliCommandName} for the
 * posture).
 */
final readonly class ConsoleCommandEntry
{
    /**
     * The description's length bound: the CLI directory renders the
     * description on one line beside the name.
     */
    private const int MAXIMUM_DESCRIPTION_LENGTH = 200;

    /**
     * @param non-empty-string $service the container service identifier
     *                                  the dispatch resolves the handler through
     */
    private function __construct(
        public CliCommandName $name,
        public string $description,
        public string $service,
    ) {
    }

    /**
     * Answers the entry a Standard CLI Schema-validated declaration
     * map carries, its fields' own bounds judged.
     *
     * @param array<string, string> $declaration the schema-validated
     *                                           declaration map (the Standard CLI Schema guarantees the three
     *                                           fields present and strings)
     *
     * @throws InvalidArgumentException when the name fails its grammar, or
     *                                  the description or service fails its bounds
     */
    public static function fromDeclaration(array $declaration): self
    {
        $description = $declaration['description'];

        if ($description === '' || mb_strlen($description) > self::MAXIMUM_DESCRIPTION_LENGTH) {
            throw new InvalidArgumentException(\sprintf(
                "The '%s' command declaration's description must be between 1 and %d characters.",
                $declaration['name'],
                self::MAXIMUM_DESCRIPTION_LENGTH,
            ));
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $description) === 1) {
            throw new InvalidArgumentException(\sprintf(
                "The '%s' command declaration's description must not carry control bytes"
                . " (the CLI directory renders it in the operator's terminal).",
                $declaration['name'],
            ));
        }

        $service = $declaration['service'];

        if ($service === '') {
            throw new InvalidArgumentException(\sprintf(
                "The '%s' command declaration's service must not be empty.",
                $declaration['name'],
            ));
        }

        return new self(
            new CliCommandName($declaration['name']),
            $description,
            $service,
        );
    }
}
