<?php

declare(strict_types=1);

use Doctum\Doctum;
use Doctum\Parser\NodeVisitor;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use Symfony\Component\Finder\Finder;
use Zef\Docs\EnumAsClassRewriter;

require_once __DIR__ . '/scripts/docs/EnumAsClassRewriter.php';

$sourceDirectory = __DIR__ . '/src';

$iterator = Finder::create()
    ->files()
    ->name('*.php')
    ->exclude(['Contracts/Psr'])
    ->in($sourceDirectory);

$options = [
    'title' => 'ZEF Framework API',
    'build_dir' => __DIR__ . '/build/docs',
    'cache_dir' => __DIR__ . '/build/doctum-cache',
    'default_opened_level' => 2,
];

$doctum = new Doctum($iterator, $options);

// Doctum 5.6 -- the newest release there is -- never visits an enum
// declaration, so an enum's file would get no generated page and the
// documentation-drift gate would (correctly) fail. The rewriter turns
// each enum into the final-class shape doctum's own visitor digests,
// cases becoming public constants, before that visitor runs; the
// wiring replaces the traverser service the parser is built from,
// keeping doctum's NameResolver and visitor order intact.
$traverser = new NodeTraverser();
$traverser->addVisitor(new NameResolver());
$traverser->addVisitor(new EnumAsClassRewriter());
$traverser->addVisitor(new NodeVisitor($doctum['parser_context']));
$doctum['php_traverser'] = $traverser;

return $doctum;
