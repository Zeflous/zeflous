<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Storage\StoragePaths;
use Zef\Test\Support\Tooling\StorageMapJson;
use Zef\Test\Support\Tooling\StorageMapText;

/**
 * The storage map command's presentation family: the table form
 * ({@see StorageMapText}) and the JSON form ({@see StorageMapJson})
 * over a resolved map -- every lane in the vocabulary's declaration
 * order, each carrying its spelling, its canonical address and the
 * lane's own one-line purpose, closed by the lane count, the JSON
 * sharing the route inspection family's one encoding spelling.
 *
 * @internal
 */
#[CoversClass(StorageMapText::class)]
#[CoversClass(StorageMapJson::class)]
final class StorageMapTest extends TestCase
{
    public function testTheTableSpellsTheRootHeaderAndEveryLaneLine(): void
    {
        self::assertSame(
            "Storage map -- 5 lanes under /srv/app/files\n"
            . "  logs     /srv/app/files/logs -- the structured JSONL records the error and slow-request lanes write\n"
            . '  cache    /srv/app/files/cache -- the compiled configuration and container'
            . " artifacts a fast boot reads\n"
            . "  temp     /srv/app/files/temp -- the transient scratch a process may leave between requests\n"
            . "  uploads  /srv/app/files/uploads -- the inbound files the uploaded-file moves land in\n"
            . "  replay   /srv/app/files/replay -- the error lane's private request snapshots\n",
            StorageMapText::table($this->paths()),
        );
    }

    public function testTheJsonDocumentCarriesTheRootEveryLaneRecordAndTheCount(): void
    {
        $document = json_decode(StorageMapJson::rendered($this->paths()), true);

        self::assertIsArray($document);
        self::assertSame('/srv/app/files', $document['root']);
        self::assertSame(5, $document['count']);

        $lanes = $document['lanes'] ?? null;
        self::assertIsArray($lanes);

        $records = [];
        foreach ($lanes as $record) {
            self::assertIsArray($record);
            $lane = $record['lane'] ?? null;
            self::assertIsString($lane);
            $records[$lane] = $record;
        }

        self::assertSame(
            ['logs', 'cache', 'temp', 'uploads', 'replay'],
            array_keys($records),
            "The JSON lanes hold the vocabulary's declaration order.",
        );

        $uploads = $records['uploads'] ?? null;
        self::assertIsArray($uploads);
        self::assertSame('/srv/app/files/uploads', $uploads['path']);
        self::assertSame(
            'the inbound files the uploaded-file moves land in',
            $uploads['purpose'],
        );
    }

    public function testTheJsonDocumentLeavesSlashesUnescaped(): void
    {
        self::assertStringContainsString(
            '"root": "/srv/app/files"',
            StorageMapJson::rendered($this->paths()),
        );
    }

    /**
     * @return StoragePaths a resolved map over a stable fake root, the
     *                      declaration order held -- no filesystem tree
     *                      is walked to render one
     */
    private function paths(): StoragePaths
    {
        return new StoragePaths('/srv/app/files', [
            'logs' => '/srv/app/files/logs',
            'cache' => '/srv/app/files/cache',
            'temp' => '/srv/app/files/temp',
            'uploads' => '/srv/app/files/uploads',
            'replay' => '/srv/app/files/replay',
        ]);
    }
}
