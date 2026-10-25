<?php

declare(strict_types=1);

namespace Zef\Test\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Storage\StorageLane;

/**
 * Pins the lane vocabulary the whole `files/**` route map hangs
 * from: the five canonical spellings, the one-line purpose each
 * lane's inspector line prints, and the declaration order every
 * walk and renderer holds -- the order IS the contract the JSON
 * record shape and the text table both spell.
 *
 * @internal
 */
#[CoversClass(StorageLane::class)]
final class StorageLaneTest extends TestCase
{
    public function testTheVocabularySpellsFiveLanesInItsDeclarationOrder(): void
    {
        self::assertSame(
            ['logs', 'cache', 'temp', 'uploads', 'replay'],
            array_map(static fn (StorageLane $storageLane): string => $storageLane->spelling(), StorageLane::cases()),
        );
    }

    public function testALaneIsAddressedByItsOwnSpelling(): void
    {
        self::assertSame('logs', StorageLane::Logs->spelling());
        self::assertSame('cache', StorageLane::Cache->spelling());
        self::assertSame('temp', StorageLane::Temp->spelling());
        self::assertSame('uploads', StorageLane::Uploads->spelling());
        self::assertSame('replay', StorageLane::Replay->spelling());
    }

    public function testEveryLaneCarriesItsOwnOneLinePurpose(): void
    {
        self::assertSame(
            'the structured JSONL records the error and slow-request lanes write',
            StorageLane::Logs->purpose(),
        );
        self::assertSame(
            'the compiled configuration and container artifacts a fast boot reads',
            StorageLane::Cache->purpose(),
        );
        self::assertSame(
            'the transient scratch a process may leave between requests',
            StorageLane::Temp->purpose(),
        );
        self::assertSame(
            'the inbound files the uploaded-file moves land in',
            StorageLane::Uploads->purpose(),
        );
        self::assertSame(
            "the error lane's private request snapshots",
            StorageLane::Replay->purpose(),
        );
    }

    public function testALaneIsBuiltFromItsOwnCanonicalSpelling(): void
    {
        foreach (StorageLane::cases() as $lane) {
            self::assertSame($lane, StorageLane::from($lane->spelling()));
        }
    }
}
