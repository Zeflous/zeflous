<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\ReplayRetention;
use Zef\Framework\Http\ReplaySnapshotAge;

/**
 * The retention seam's own contract, measured against a real directory: the
 * bound holds, the ordering witness is the filesystem's own modification
 * instant, and only the writer's own snapshot files are ever considered.
 *
 * @internal
 */
#[CoversClass(ReplayRetention::class)]
#[CoversClass(ReplaySnapshotAge::class)]
final class ReplayRetentionTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/zef-replay-retention-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0o700);
    }

    #[Override]
    protected function tearDown(): void
    {
        $files = glob($this->directory . '/*');
        foreach (\is_array($files) ? $files : [] as $file) {
            if (!is_file($file) && !is_link($file)) {
                continue;
            }

            unlink($file);
        }

        if (!is_dir($this->directory)) {
            return;
        }

        rmdir($this->directory);
    }

    public function testPrunesTheOldestSnapshotsBeyondTheBound(): void
    {
        $ids = $this->seedSnapshots(5, 1_000);

        ReplayRetention::prune($this->directory, 3);

        $remaining = $this->snapshotIds();
        self::assertSame([$ids[2], $ids[3], $ids[4]], $remaining, 'the two oldest snapshots are pruned');
    }

    public function testKeepsEverySnapshotAtOrBelowTheBound(): void
    {
        $ids = $this->seedSnapshots(3, 1_000);

        ReplayRetention::prune($this->directory, 3);
        self::assertSame($ids, $this->snapshotIds(), 'a directory at the bound is untouched');

        ReplayRetention::prune($this->directory, 4);
        self::assertSame($ids, $this->snapshotIds(), 'a directory below the bound is untouched');
    }

    public function testConsidersOnlyTheWritersOwnSnapshotFiles(): void
    {
        $ids = $this->seedSnapshots(3, 1_000);
        $foreign = $this->directory . '/operator-notes.txt';
        file_put_contents($foreign, 'kept');
        self::assertTrue(touch($foreign, 500));

        ReplayRetention::prune($this->directory, 2);

        self::assertSame([$ids[1], $ids[2]], $this->snapshotIds(), 'only the snapshot files are pruned');
        self::assertFileExists($foreign, 'a non-snapshot file is never touched');
        self::assertSame('kept', (string) file_get_contents($foreign));
    }

    public function testPrunesByAgeNotByDirectoryOrder(): void
    {
        // Seeded in directory order live-first-dead-last but aged in the
        // opposite direction: the pruning witness is the modification
        // instant, never the glob's directory order.
        $files = [
            'a' => 3_000,
            'b' => 1_000,
            'c' => 4_000,
            'd' => 2_000,
            'e' => 5_000,
        ];
        foreach ($files as $id => $instant) {
            $file = $this->directory . '/' . $id . '.json';
            file_put_contents($file, '{}');
            chmod($file, 0o600);
            self::assertTrue(touch($file, $instant));
        }

        ReplayRetention::prune($this->directory, 3);

        self::assertSame(
            ['a', 'c', 'e'],
            $this->snapshotIds(),
            'the two OLDEST snapshots are pruned, not the two first-listed',
        );
    }

    public function testTheAgeFoldAnswersZeroForUnstattableEntries(): void
    {
        $live = $this->directory . '/live.json';
        file_put_contents($live, '{}');
        chmod($live, 0o600);
        self::assertTrue(touch($live, 1_000_000));

        $dead = $this->directory . '/dead.json';
        self::assertTrue(symlink($this->directory . '/missing-target.json', $dead));

        set_error_handler(static fn (): bool => true);

        try {
            $ordered = ReplaySnapshotAge::orderedByAge($this->directory);
        } finally {
            restore_error_handler();
        }

        self::assertSame(
            [$dead, $live],
            array_keys($ordered),
            'the unstattable entry sorts as the oldest garbage there is',
        );
        self::assertSame([0, 1_000_000], array_values($ordered));
    }

    public function testAnEmptyDirectoryNeedsNoPruning(): void
    {
        ReplayRetention::prune($this->directory, 1);

        self::assertSame([], $this->snapshotIds());
    }

    /**
     * Seeds the directory with snapshot files carrying strictly increasing
     * modification instants, and answers their ids in the written order.
     *
     * @return list<string>
     */
    private function seedSnapshots(int $count, int $instantStep): array
    {
        $ids = [];

        for ($i = 0; $i < $count; ++$i) {
            $id = mb_str_pad((string) $i, 32, 'a', \STR_PAD_LEFT);
            $file = $this->directory . '/' . $id . '.json';
            file_put_contents($file, '{"id":"' . $id . '"}');
            chmod($file, 0o600);
            self::assertTrue(touch($file, 1_000_000 + ($i * $instantStep)));

            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * Answers the ids of the snapshot files left in the directory, ordered by
     * modification instant so the assertion reads as the retention's own
     * ordering rather than the glob's directory order.
     *
     * @return list<string>
     */
    private function snapshotIds(): array
    {
        $files = glob($this->directory . '/*.json');

        $byAge = [];
        foreach (\is_array($files) ? $files : [] as $file) {
            $modified = filemtime($file);
            $byAge[$file] = \is_int($modified) ? $modified : 0;
        }

        asort($byAge);

        $ids = [];
        foreach (array_keys($byAge) as $file) {
            $ids[] = basename($file, '.json');
        }

        return $ids;
    }
}
