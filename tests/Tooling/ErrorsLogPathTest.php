<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\ErrorsLogPath;
use Zef\Test\Support\Tooling\ToolingException;

/**
 * The errors family's log-address fold: the one-way precedence
 * (positional path, then the environment spelling, then the storage
 * layout's derived `logs`-lane address), and the symlink refusal both
 * log verbs share.
 *
 * @internal
 */
#[CoversClass(ErrorsLogPath::class)]
final class ErrorsLogPathTest extends TestCase
{
    /**
     * Backs up the two environment spellings the fold reads so each test
     * spells its own and the suite leaves nothing behind.
     */
    private string $previousErrorLog;

    private string $previousStorageRoot;

    #[Override]
    protected function setUp(): void
    {
        $this->previousErrorLog = (string) getenv('ZEF_ERROR_LOG');
        $this->previousStorageRoot = (string) getenv('ZEF_STORAGE_ROOT');
        putenv('ZEF_ERROR_LOG=');
        putenv('ZEF_STORAGE_ROOT=');
    }

    #[Override]
    protected function tearDown(): void
    {
        putenv('ZEF_ERROR_LOG=' . $this->previousErrorLog);
        putenv('ZEF_STORAGE_ROOT=' . $this->previousStorageRoot);
    }

    public function testThePositionalPathWinsVerbatim(): void
    {
        self::assertSame('/tmp/positional.jsonl', ErrorsLogPath::resolved('/tmp/positional.jsonl'));
    }

    public function testTheEnvironmentSpellingAnswersWhenNoPositionalPathWasGiven(): void
    {
        putenv('ZEF_ERROR_LOG=/tmp/from-env.jsonl');

        self::assertSame('/tmp/from-env.jsonl', ErrorsLogPath::resolved(null));
    }

    public function testThePositionalPathWinsOverTheEnvironmentSpelling(): void
    {
        putenv('ZEF_ERROR_LOG=/tmp/from-env.jsonl');

        self::assertSame('/tmp/positional.jsonl', ErrorsLogPath::resolved('/tmp/positional.jsonl'));
    }

    public function testTheLayoutAnswersTheDerivedLogsLaneAddress(): void
    {
        $root = $this->provisionedStorageRoot();

        try {
            putenv('ZEF_STORAGE_ROOT=' . $root);

            self::assertSame(
                $root . '/logs/zef-errors.jsonl',
                ErrorsLogPath::resolved(null),
            );
        } finally {
            $this->removeStorageRoot($root);
        }
    }

    public function testTheStorageRootEnvironmentSpellingIsAnsweredToo(): void
    {
        $root = $this->provisionedStorageRoot();

        try {
            putenv('ZEF_ERROR_LOG=');
            putenv('ZEF_STORAGE_ROOT=' . $root);

            self::assertSame($root . '/logs/zef-errors.jsonl', ErrorsLogPath::resolved(null));
        } finally {
            $this->removeStorageRoot($root);
        }
    }

    public function testAnEmptyStorageRootEnvironmentSpellingFallsBackToTheCheckoutTree(): void
    {
        putenv('ZEF_STORAGE_ROOT=');

        self::assertSame(
            \dirname(__DIR__, 2) . '/files/logs/zef-errors.jsonl',
            ErrorsLogPath::resolved(null),
        );
    }

    public function testAMisprovisionedTreeRefusesWithTheLayoutsOwnWords(): void
    {
        $this->expectException(InvalidArgumentException::class);

        putenv('ZEF_STORAGE_ROOT=' . sys_get_temp_dir() . '/zef-logpath-absent-' . bin2hex(random_bytes(8)));

        ErrorsLogPath::resolved(null);
    }

    public function testASymlinkedPositionalPathIsRefused(): void
    {
        $target = sys_get_temp_dir() . '/zef-logpath-target-' . bin2hex(random_bytes(8)) . '.jsonl';
        $link = sys_get_temp_dir() . '/zef-logpath-link-' . bin2hex(random_bytes(8)) . '.jsonl';
        file_put_contents($target, '');
        symlink($target, $link);

        try {
            $this->expectException(ToolingException::class);
            $this->expectExceptionMessageIsOrContains('Refusing to follow a symlink log path.');

            ErrorsLogPath::resolved($link);
        } finally {
            unlink($link);
            unlink($target);
        }
    }

    public function testASymlinkedEnvironmentPathIsRefusedToo(): void
    {
        $target = sys_get_temp_dir() . '/zef-logpath-target-' . bin2hex(random_bytes(8)) . '.jsonl';
        $link = sys_get_temp_dir() . '/zef-logpath-link-' . bin2hex(random_bytes(8)) . '.jsonl';
        file_put_contents($target, '');
        symlink($target, $link);

        try {
            putenv('ZEF_ERROR_LOG=' . $link);

            $this->expectException(ToolingException::class);
            $this->expectExceptionMessageIsOrContains('Refusing to follow a symlink log path.');

            ErrorsLogPath::resolved(null);
        } finally {
            unlink($link);
            unlink($target);
        }
    }

    /**
     * A fully provisioned storage tree: every lane directory the layout's
     * fail-closed walk demands, writable and real.
     *
     * @return string the root's own path
     */
    private function provisionedStorageRoot(): string
    {
        $root = sys_get_temp_dir() . '/zef-logpath-' . bin2hex(random_bytes(8));
        foreach (['logs', 'cache', 'temp', 'uploads', 'replay', 'queue', 'sessions'] as $lane) {
            mkdir($root . '/' . $lane, 0o755, true);
        }

        return $root;
    }

    /**
     * Removes a provisioned storage tree.
     */
    private function removeStorageRoot(string $root): void
    {
        foreach (['logs', 'cache', 'temp', 'uploads', 'replay', 'queue', 'sessions'] as $lane) {
            rmdir($root . '/' . $lane);
        }

        rmdir($root);
    }
}
