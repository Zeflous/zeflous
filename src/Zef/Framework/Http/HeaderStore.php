<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The case-insensitive header store behind every PSR-7 message.
 *
 * An immutable value: the mutations delegate to the {@see HeaderTable}
 * and {@see HeaderAppender} engines and return a new store, so the
 * composing message's copy-on-write is a clone plus one assignment.
 * Validation happens at the boundary -- every `with*` and `withAdded`
 * normalizes its raw PSR value through {@see HeaderGrammar} before the
 * table sees it.
 */
final class HeaderStore
{
    private const array NO_HEADERS = [];

    /**
     * @param array<string, list<string>> $headers already-normalized
     *                                             headers keyed by their
     *                                             stored spelling
     */
    public function __construct(private array $headers = self::NO_HEADERS)
    {
    }

    public function has(string $name): bool
    {
        return HeaderIndex::storedName($this->headers, $name) !== null;
    }

    /**
     * @return list<string> the values stored under the case-insensitive
     *                      name, or an empty list when absent
     */
    public function get(string $name): array
    {
        $storedName = HeaderIndex::storedName($this->headers, $name);

        return $storedName === null ? [] : $this->headers[$storedName];
    }

    /**
     * @return array<string, list<string>> every header, keyed by the
     *                                     original case as first set
     */
    public function all(): array
    {
        return $this->headers;
    }

    /**
     * Replaces any values stored under the case-insensitive name.
     *
     * @param mixed $value string or list<string> per the PSR contract
     *
     * @throws HttpException when the name or value is rejected by the grammar
     */
    public function with(string $name, mixed $value): self
    {
        $replacedHeaders = HeaderTable::replaced(
            $this->headers,
            $name,
            HeaderGrammar::normalize($name, $value),
        );

        return new self($replacedHeaders);
    }

    /**
     * Appends the value(s) to the case-insensitive name.
     *
     * @param mixed $value string or list<string> per the PSR contract
     *
     * @throws HttpException when the name or value is rejected by the grammar
     */
    public function withAdded(string $name, mixed $value): self
    {
        $appendedHeaders = HeaderAppender::appended(
            $this->headers,
            $name,
            HeaderGrammar::normalize($name, $value),
        );

        return new self($appendedHeaders);
    }

    /**
     * Removes the header stored under the case-insensitive name; an
     * absent name answers this store unchanged.
     */
    public function without(string $name): self
    {
        return new self(HeaderTable::removed($this->headers, $name));
    }
}
