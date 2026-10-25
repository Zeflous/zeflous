<?php

declare(strict_types=1);

namespace Zef\Test\Storage;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Storage\StorageLane;

/**
 * Pins the repository's own storage skeleton against the lane
 * vocabulary: every lane the code spells exists under `files/`
 * carrying its `.gitkeep`, no directory the vocabulary does not
 * spell rides along, and the `.gitignore` discipline holds -- each
 * lane's contents ignored, each lane's `.gitkeep` kept. The pin is
 * the consistency instrument's own floor: a lane added to the
 * vocabulary without the tree, or a lane directory added to the
 * tree without the vocabulary, is drift this test reddens on before
 * any environment provisions a wrong-shaped tree.
 *
 * @internal
 */
#[CoversNothing]
final class StorageSkeletonTest extends TestCase
{
    private const string REPOSITORY_ROOT = __DIR__ . '/../..';

    public function testTheSkeletonCarriesEveryLaneTheVocabularySpells(): void
    {
        foreach (StorageLane::cases() as $lane) {
            $laneDirectory = self::REPOSITORY_ROOT . '/files/' . $lane->spelling();

            self::assertDirectoryExists($laneDirectory, $lane->spelling() . ' lane missing from files/');
            self::assertFileExists($laneDirectory . '/.gitkeep', $lane->spelling() . ' lane carries no .gitkeep');
        }
    }

    public function testTheSkeletonCarriesNoLaneTheVocabularyDoesNotSpell(): void
    {
        $spelled = [];
        foreach (StorageLane::cases() as $lane) {
            $spelled[$lane->spelling()] = true;
        }

        $entries = scandir(self::REPOSITORY_ROOT . '/files');
        self::assertNotFalse($entries, 'The files/ skeleton must be readable.');

        $directories = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || !is_dir(self::REPOSITORY_ROOT . '/files/' . $entry)) {
                continue;
            }

            $directories[] = $entry;
        }

        self::assertSame(
            [],
            array_values(array_diff($directories, array_keys($spelled))),
            'A files/ directory the lane vocabulary does not spell is drift.',
        );
    }

    public function testTheGitignoreDisciplineHoldsForEveryLane(): void
    {
        $gitignore = file_get_contents(self::REPOSITORY_ROOT . '/.gitignore');
        self::assertNotFalse($gitignore, 'The repository .gitignore must be readable.');

        foreach (StorageLane::cases() as $lane) {
            self::assertStringContainsString(
                '/files/' . $lane->spelling() . "/*\n",
                $gitignore,
                $lane->spelling() . ' lane contents must be ignored.',
            );
            self::assertStringContainsString(
                '!/files/' . $lane->spelling() . '/.gitkeep',
                $gitignore,
                $lane->spelling() . ' lane .gitkeep must be kept.',
            );
        }
    }
}
