<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\ComposerManifest;
use Zef\Test\Support\Tooling\ZeroDependencyGate;

/**
 * The scope corpus of the zero-dependency gate.
 *
 * Issue #576 (the C-4 deferral of the round-2 audit, #554) deferred the
 * manifest-only scope question until a corpus existed, because widening a
 * live gate's failure surface without one risks breaking CI for the whole
 * repository. This corpus is the answer's record: the decision matrix over
 * the manifest's two input channels (the `require` map and the production
 * `packages` list), and the fence itself -- the channels that never reach
 * the verdict because the input type carries no field for them.
 *
 * The fence is the design, not a gap: rule 16 states the invariant over the
 * Composer manifest pair, and the gate is that pair's ratchet, not a
 * filesystem scanner. A committed vendor tree, a bundled phar, a runtime
 * include -- every dependency source outside the manifests is outside the
 * gate by construction, and widening the scope is an owner decision this
 * corpus makes conscious: the fence cases would have to move in the same
 * diff as the widening.
 *
 * The corpus is the ratchet, in the same spirit as the PHPStan baseline
 * gate ({@see BaselineGate}): the inventory test pins the per-family counts
 * so an entry cannot appear or disappear without the counts moving in the
 * same diff.
 *
 * @internal
 */
#[CoversClass(ZeroDependencyGate::class)]
#[CoversClass(ComposerManifest::class)]
final class ZeroDependencyScopeCorpusTest extends TestCase
{
    private string $composerJsonPath;

    private string $composerLockPath;

    #[Override]
    protected function setUp(): void
    {
        $this->composerJsonPath = (string) tempnam(sys_get_temp_dir(), 'zef-scope-json-');
        $this->composerLockPath = (string) tempnam(sys_get_temp_dir(), 'zef-scope-lock-');
    }

    #[Override]
    protected function tearDown(): void
    {
        unlink($this->composerJsonPath);
        unlink($this->composerLockPath);
    }

    /**
     * @dataProvider provideTheDecisionMatrix
     *
     * @param array<string, string>       $require
     * @param list<array<string, string>> $packages
     */
    #[DataProvider('provideTheDecisionMatrix')]
    public function testTheDecisionMatrix(array $require, array $packages, bool $expected): void
    {
        $gateResult = new ZeroDependencyGate()->evaluate(new ComposerManifest($require, $packages));

        self::assertSame($expected, $gateResult->passed);
    }

    /**
     * @dataProvider provideTheFenceCorpus
     */
    #[DataProvider('provideTheFenceCorpus')]
    public function testTheFence(string $composerJson, string $composerLock): void
    {
        file_put_contents($this->composerJsonPath, $composerJson);
        file_put_contents($this->composerLockPath, $composerLock);

        $gateResult = new ZeroDependencyGate()->evaluate(
            ComposerManifest::fromFiles($this->composerJsonPath, $this->composerLockPath),
        );

        self::assertSame(
            'Zero-dependency gate PASSED: composer.json require is {"php": "^8.4"} and composer.lock packages is [].',
            $gateResult->message,
        );
    }

    /**
     * The ratchet odometer: the corpus is complete when the family counts
     * hold, so the scope cannot widen or narrow quietly.
     */
    public function testTheCorpusCountsAreTheRatchet(): void
    {
        self::assertSame(6, count([...self::provideTheDecisionMatrix()]));
        self::assertSame(2, count([...self::provideTheFenceCorpus()]));
    }

    /**
     * The decision matrix over the manifest's two channels: what each channel
     * contributes to the verdict, from the one green cell to every refusal.
     *
     * @return iterable<non-empty-string, array{array<string, string>, list<array<string, string>>, bool}>
     */
    public static function provideTheDecisionMatrix(): iterable
    {
        yield 'holds: the php platform alone keeps the lane green' => [
            ['php' => '^8.4'],
            [],
            true,
        ];

        yield 'holds: any extra require entry fails the lane' => [
            ['php' => '^8.4', 'monolog/monolog' => '^3.0'],
            [],
            false,
        ];

        yield 'holds: a changed php constraint fails the lane' => [
            ['php' => '^8.3'],
            [],
            false,
        ];

        yield 'holds: an emptied require fails the lane' => [
            [],
            [],
            false,
        ];

        yield 'holds: a single locked production package fails the lane' => [
            ['php' => '^8.4'],
            [['name' => 'monolog/monolog']],
            false,
        ];

        yield 'holds: two locked production packages fail the lane' => [
            ['php' => '^8.4'],
            [['name' => 'monolog/monolog'], ['name' => 'psr/log']],
            false,
        ];
    }

    /**
     * The fence: the channels that never reach the verdict. The dev
     * dependency tree is none of the gate's business -- a `require-dev`
     * section and a locked `packages-dev` list both pass by construction,
     * because the input type carries no field that could read them.
     *
     * @return iterable<non-empty-string, array{string, string}>
     */
    public static function provideTheFenceCorpus(): iterable
    {
        yield 'outside: a require-dev section never reaches the verdict' => [
            '{"name":"zeflous/zeflous","require":{"php":"^8.4"},"require-dev":{"phpunit/phpunit":"^11.0"}}',
            '{"content-hash":"abc123","packages":[],"packages-dev":[]}',
        ];

        yield 'outside: a locked packages-dev tree never reaches the verdict' => [
            '{"name":"zeflous/zeflous","require":{"php":"^8.4"}}',
            '{"content-hash":"abc123","packages":[],"packages-dev":[{"name":"phpunit/phpunit","version":"11.0.0"}]}',
        ];
    }
}
