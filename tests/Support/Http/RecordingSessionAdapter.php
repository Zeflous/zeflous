<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Override;
use Zef\Framework\Http\InMemorySessionAdapter;
use Zef\Framework\Http\SessionAdapterInterface;

/**
 * Recording session-adapter double: delegates to the in-memory driver
 * while recording every identifier the family asked the storage
 * about -- the shape the session family's boundary needs to evidence
 * which walks reached the storage at all (the grammar-refused value
 * that is never loaded, the destroyed fresh identifier that is
 * destroyed but never saved).
 */
final class RecordingSessionAdapter implements SessionAdapterInterface
{
    /** @var list<string> */
    public array $loadedIds = [];

    /** @var list<string> */
    public array $savedIds = [];

    /** @var list<string> */
    public array $destroyedIds = [];

    private InMemorySessionAdapter $adapter;

    public function __construct()
    {
        $this->adapter = new InMemorySessionAdapter(1800);
    }

    #[Override]
    public function load(string $id): ?array
    {
        $this->loadedIds[] = $id;

        return $this->adapter->load($id);
    }

    #[Override]
    public function save(string $id, array $data): void
    {
        $this->savedIds[] = $id;
        $this->adapter->save($id, $data);
    }

    #[Override]
    public function destroy(string $id): void
    {
        $this->destroyedIds[] = $id;
        $this->adapter->destroy($id);
    }
}
