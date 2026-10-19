<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\NamespaceAnnotationGrammar;
use Zef\Framework\Container\NamespaceBuiltRefusals;
use Zef\Framework\Container\NamespaceIdentifierGrammar;
use Zef\Framework\Container\NamespaceTreeBuilder;
use Zef\Framework\Container\NamespaceTreeCounters;
use Zef\Framework\Container\NamespaceTreeInsertions;
use Zef\Framework\Container\NamespaceTreeSealing;
use Zef\Framework\Container\RadixEdgeLabels;
use Zef\Framework\Container\RadixTreeHop;
use Zef\Framework\Container\RadixTreeNavigator;

/**
 * The namespace radix tree's mutable build phase: the insert walk, the
 * scope annotations, and the sealed-once discipline the builder owes
 * the boot (ROADMAP area 9's namespace half -- the reference's
 * freeze-time seal, adapted to the builder-versus-product split).
 *
 * @internal
 */
#[CoversClass(NamespaceTreeBuilder::class)]
#[CoversClass(NamespaceBuiltRefusals::class)]
#[CoversClass(NamespaceIdentifierGrammar::class)]
#[CoversClass(NamespaceAnnotationGrammar::class)]
#[CoversClass(NamespaceTreeInsertions::class)]
#[CoversClass(NamespaceTreeCounters::class)]
#[CoversClass(NamespaceTreeSealing::class)]
#[CoversClass(RadixEdgeLabels::class)]
#[CoversClass(RadixTreeHop::class)]
#[CoversClass(RadixTreeNavigator::class)]
final class NamespaceTreeBuilderTest extends TestCase
{
    public function testTheEmptyIdentifierIsRefused(): void
    {
        $namespaceTreeBuilder = new NamespaceTreeBuilder();

        try {
            $namespaceTreeBuilder->insert('');
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                'A namespace-indexed service identifier must be a non-empty string.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnIdentifierWithAnEmptySegmentIsRefused(): void
    {
        $namespaceTreeBuilder = new NamespaceTreeBuilder();

        try {
            $namespaceTreeBuilder->insert('app..window');
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The service identifier 'app..window' spells an empty namespace segment "
                . '(two separators with nothing between them).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testASpellingCollisionIsRefusedWithBothNamed(): void
    {
        $namespaceTreeBuilder = new NamespaceTreeBuilder();
        $namespaceTreeBuilder->insert('App\Widget');

        try {
            $namespaceTreeBuilder->insert('App.Widget');
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The service identifiers 'App\\Widget' and 'App.Widget' spell the same namespace "
                . 'position -- the namespace layer cannot serve both.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testReinsertingTheSameIdentifierIsIdempotent(): void
    {
        $namespaceTreeBuilder = new NamespaceTreeBuilder();
        $namespaceTreeBuilder->insert('app.window');
        $namespaceTreeBuilder->insert('app.window');

        $tree = $namespaceTreeBuilder->seal();

        self::assertSame(['app.window'], $tree->idsUnderPrefix('app'));
        self::assertSame(1, $tree->stats()['serviceIds']);
    }

    public function testTheSealRefusesFurtherBuildSteps(): void
    {
        $namespaceTreeBuilder = new NamespaceTreeBuilder();
        $namespaceTreeBuilder->insert('app.window');
        $namespaceTreeBuilder->seal();

        try {
            $namespaceTreeBuilder->insert('app.door');
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                'The namespace radix tree is sealed -- insert() is not allowed.',
                $definitionException->getMessage(),
            );
        }

        try {
            $namespaceTreeBuilder->annotate('app', 'internal');
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                'The namespace radix tree is sealed -- annotate() is not allowed.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testASecondSealIsTheSealedOnceRefusal(): void
    {
        $namespaceTreeBuilder = new NamespaceTreeBuilder();
        $namespaceTreeBuilder->insert('app.window');
        $namespaceTreeBuilder->seal();

        try {
            $namespaceTreeBuilder->seal();
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                'The namespace tree builder is already sealed -- the boot seals it once.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnnotateRefusesTheUnknownKind(): void
    {
        $namespaceTreeBuilder = new NamespaceTreeBuilder();

        try {
            $namespaceTreeBuilder->annotate('app', 'secret');
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The namespace annotation for 'app' carries the unknown scope kind "
                . "'secret' (known kinds: public, internal, module).",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnnotateRefusesTheRootPrefix(): void
    {
        $namespaceTreeBuilder = new NamespaceTreeBuilder();

        try {
            $namespaceTreeBuilder->annotate('\\', 'internal');
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The namespace annotation prefix '\\' governs no subtree -- a bare "
                . 'separator annotates nothing.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnnotateRefusesTheKindDisagreement(): void
    {
        $namespaceTreeBuilder = new NamespaceTreeBuilder();
        $namespaceTreeBuilder->annotate('App\Internal', 'internal');

        try {
            $namespaceTreeBuilder->annotate('App.Internal.', 'module');
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The namespace 'App.Internal.' is annotated both 'internal' and 'module' "
                . '-- one subtree, one annotation.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testReannotatingWithTheSameKindIsIdempotent(): void
    {
        $namespaceTreeBuilder = new NamespaceTreeBuilder();
        $namespaceTreeBuilder->annotate('app', 'internal');
        $namespaceTreeBuilder->annotate('app.', 'internal');

        $tree = $namespaceTreeBuilder->seal();

        self::assertSame(['app.' => 'internal'], $tree->annotations());
    }

    public function testTheSealAnswersTheSealedProduct(): void
    {
        $namespaceTreeBuilder = new NamespaceTreeBuilder();
        $namespaceTreeBuilder->insert('app.window');
        $namespaceTreeBuilder->annotate('app.module', 'internal');

        $tree = $namespaceTreeBuilder->seal();

        self::assertSame(['app.window'], $tree->idsUnderPrefix('app'));
        self::assertSame('internal', $tree->scopeOf('app.module.hidden')?->kind);
        self::assertTrue($tree->stats()['sealed']);
    }

    /**
     * The refusal vocabulary's own spelling of the sealed-once word,
     * pinned so the message cannot drift from the discipline it names.
     */
    public function testTheAlreadySealedWord(): void
    {
        self::assertSame(
            'The namespace tree builder is already sealed -- the boot seals it once.',
            NamespaceBuiltRefusals::alreadySealed()->getMessage(),
        );
    }
}
