<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\NamespaceBudget;
use Zef\Framework\Container\NamespacePrefixGrammar;
use Zef\Framework\Container\NamespaceRefusals;
use Zef\Framework\Container\NamespaceScopeKinds;
use Zef\Framework\Container\NamespaceScopeParser;
use Zef\Framework\Container\NamespaceScopesSource;
use Zef\Framework\Container\NamespacesMap;
use Zef\Framework\Container\NamespacesSource;

/**
 * The namespaces knob's parse behaviour: the grammar's happy path and
 * every fail-closed refusal it owes the boot (ROADMAP area 9's
 * namespace half -- the policy the enforcement walk reads).
 *
 * @internal
 */
#[CoversClass(NamespacesMap::class)]
#[CoversClass(NamespacesSource::class)]
#[CoversClass(NamespaceBudget::class)]
#[CoversClass(NamespaceScopeParser::class)]
#[CoversClass(NamespaceScopesSource::class)]
#[CoversClass(NamespacePrefixGrammar::class)]
#[CoversClass(NamespaceScopeKinds::class)]
#[CoversClass(NamespaceRefusals::class)]
final class NamespacesMapTest extends TestCase
{
    public function testTheNullSourceIsTheUngovernedBoot(): void
    {
        $map = NamespacesMap::parse('namespaces', null);

        self::assertSame([], $map->scopes());
        self::assertSame(0, $map->maxCrossScopeRefs());
    }

    public function testTheEmptyMapIsTheUngovernedBoot(): void
    {
        $map = NamespacesMap::parse('namespaces', []);

        self::assertSame([], $map->scopes());
        self::assertSame(0, $map->maxCrossScopeRefs());
    }

    public function testTheEmptyScopesMemberIsTheUngovernedBoot(): void
    {
        $map = NamespacesMap::parse('namespaces', ['scopes' => []]);

        self::assertSame([], $map->scopes());
        self::assertSame(0, $map->maxCrossScopeRefs());
    }

    public function testTheAbsentMembersDefaultToTheirEmptySelves(): void
    {
        $map = NamespacesMap::parse('namespaces', ['max_cross_scope_refs' => 3]);

        self::assertSame([], $map->scopes());
        self::assertSame(3, $map->maxCrossScopeRefs());
    }

    public function testTheHappyPathNormalisesSortsAndCarriesTheBudget(): void
    {
        $map = NamespacesMap::parse('namespaces', [
            'scopes' => [
                'zeta\\' => 'internal',
                'App\Internal\\' => 'internal',
                'alpha' => 'module',
            ],
            'max_cross_scope_refs' => 4,
        ]);

        self::assertSame(
            ['App.Internal.' => 'internal', 'alpha.' => 'module', 'zeta.' => 'internal'],
            $map->scopes(),
        );
        self::assertSame(4, $map->maxCrossScopeRefs());
    }

    public function testANonMapSourceIsRefused(): void
    {
        try {
            NamespacesMap::parse('namespaces', ['internal']);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'namespaces' configuration must be a map of the namespace lane's keys "
                . '(scopes, max_cross_scope_refs), array given.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnUnknownKeyIsRefusedWithTheKnownList(): void
    {
        try {
            NamespacesMap::parse('namespaces', ['scope' => []]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'namespaces' configuration carries the unknown key 'scope' "
                . '(known keys: scopes, max_cross_scope_refs).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAListScopesMemberIsRefused(): void
    {
        try {
            NamespacesMap::parse('namespaces', ['scopes' => ['internal']]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'namespaces.scopes' configuration must be a map of namespace prefixes to scope kinds, "
                . 'array given.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testANonArraySourceIsRefused(): void
    {
        try {
            NamespacesMap::parse('namespaces', 'internal');
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'namespaces' configuration must be a map of the namespace lane's keys "
                . '(scopes, max_cross_scope_refs), string given.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testANonMapScopesMemberIsRefused(): void
    {
        try {
            NamespacesMap::parse('namespaces', ['scopes' => 'internal']);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'namespaces.scopes' configuration must be a map of namespace prefixes to scope kinds, "
                . 'string given.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnUnknownScopeKindIsRefused(): void
    {
        try {
            NamespacesMap::parse('namespaces', ['scopes' => ['app' => 'secret']]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'namespaces.scopes' entry for 'app' carries the unknown scope kind 'secret' "
                . '(known kinds: public, internal, module).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testANonStringScopeKindIsRefusedWithItsType(): void
    {
        try {
            NamespacesMap::parse('namespaces', ['scopes' => ['app' => 2]]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'namespaces.scopes' entry for 'app' carries the unknown scope kind 'int' "
                . '(known kinds: public, internal, module).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testANonStringPrefixIsRefused(): void
    {
        try {
            NamespacesMap::parse('namespaces', ['scopes' => [7 => 'internal']]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'namespaces.scopes' keys must be non-empty namespace prefixes, int given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnEmptyPrefixIsRefused(): void
    {
        try {
            NamespacesMap::parse('namespaces', ['scopes' => ['' => 'internal']]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'namespaces.scopes' keys must be non-empty namespace prefixes, "
                . 'string given.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testTheRootPrefixIsRefused(): void
    {
        try {
            NamespacesMap::parse('namespaces', ['scopes' => ['\\' => 'internal']]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'namespaces.scopes' prefix '\\' annotates nothing -- a bare separator "
                . 'governs no subtree; spell the namespace the scope is for.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnEmptySegmentPrefixIsRefused(): void
    {
        try {
            NamespacesMap::parse('namespaces', ['scopes' => ['app..gate' => 'internal']]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'namespaces.scopes' prefix 'app..gate' spells an empty namespace segment "
                . '(two separators with nothing between them).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testTwoSpellingsOfOneNamespaceAreRefusedWithBothNamed(): void
    {
        try {
            NamespacesMap::parse('namespaces', [
                'scopes' => [
                    'App\Internal\\' => 'internal',
                    'App.Internal' => 'internal',
                ],
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'namespaces.scopes' prefixes 'App\\Internal\\' and 'App.Internal' both spell the namespace "
                . "'App.Internal.' -- one subtree, one annotation.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testTheScopeBudgetIsRefusedWhenExceeded(): void
    {
        $scopes = [];

        for ($i = 0; $i < 65; ++$i) {
            $scopes['prefix' . $i] = 'internal';
        }

        try {
            NamespacesMap::parse('namespaces', ['scopes' => $scopes]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'namespaces.scopes' configuration declares more than 64 annotations "
                . "-- the lane's hard bound.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testTheScopeBudgetAcceptsItsFullPopulation(): void
    {
        $scopes = [];

        for ($i = 0; $i < 64; ++$i) {
            $scopes['prefix' . $i] = 'internal';
        }

        $map = NamespacesMap::parse('namespaces', ['scopes' => $scopes]);

        self::assertCount(64, $map->scopes());
    }

    public function testANonIntegerBudgetIsRefused(): void
    {
        try {
            NamespacesMap::parse('namespaces', ['max_cross_scope_refs' => '4']);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'namespaces.max_cross_scope_refs' configuration must be an integer, string given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testANegativeBudgetIsRefused(): void
    {
        try {
            NamespacesMap::parse('namespaces', ['max_cross_scope_refs' => -1]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'namespaces.max_cross_scope_refs' configuration must be zero or more, -1 given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAZeroBudgetIsTheValidDenyAll(): void
    {
        $map = NamespacesMap::parse('namespaces', ['max_cross_scope_refs' => 0]);

        self::assertSame(0, $map->maxCrossScopeRefs());
    }
}
