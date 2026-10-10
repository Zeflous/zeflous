<?php

declare(strict_types=1);

namespace Zef\Framework\Config;

/**
 * The build-time `with*` mutation protocol of the configuration repository.
 *
 * Every method returns a *new* {@see Config} instance with the change applied
 * (CONTRIBUTING.md rule 3): the repository itself stays immutable, so an
 * instance built once can be shared safely across a persistent worker's
 * requests. The traversal mechanics live in {@see DotWriter} and
 * {@see DotListWriter}; this trait only binds them to the repository.
 */
trait ConfigMutations
{
    /**
     * Returns a new repository with the value written at the dot-notation
     * key, creating missing intermediate arrays on the way down.
     */
    public function withSet(string $key, mixed $value): self
    {
        return new self(DotWriter::set($this->data, DotKey::parse($key), $value));
    }

    /**
     * Returns a new repository without the value addressed by the key; an
     * absent path is a no-op that still yields a new instance.
     */
    public function withUnset(string $key): self
    {
        return new self(DotRemover::remove($this->data, DotKey::parse($key)));
    }

    /**
     * Returns a new repository with the value appended to the list addressed
     * by the key; the target must exist and be a list.
     */
    public function withAppend(string $key, mixed $value): self
    {
        return new self(DotListWriter::appendAt($this->data, DotKey::parse($key), $value));
    }

    /**
     * Returns a new repository with the value prepended to the list
     * addressed by the key; the target must exist and be a list.
     */
    public function withPrepend(string $key, mixed $value): self
    {
        return new self(DotListWriter::prependAt($this->data, DotKey::parse($key), $value));
    }
}
