<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/benchmarks',
    ])
    ->withSkip([
        __DIR__ . '/src/Contracts/Psr',
        __DIR__ . '/vendor',
        // PHP's stream-wrapper protocol fixes the fixture methods' signatures
        // (stream_open(stream read, ...)); the protocol's unused parameters
        // must not be pruned away.
        __DIR__ . '/tests/Support/Http',
        // Kept authoritative by php-cs-fixer; avoid a Rector/CS-Fixer tug-of-war.
        'Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector',
        // Kept authoritative by the vendored PSR contracts; psalm's
        // ParamNameMismatch wins over the type-derived renaming, so an
        // implementation of an interface method keeps the interface's
        // parameter names (withBody(StreamInterface $body), not $stream).
        // ETagMiddleware::process() and CorsMiddleware::process() are the
        // PSR-15 face of the same rule: the vendored
        // MiddlewareInterface declares ($request, $handler).
        // MiddlewarePipeline::handle() extends it to the pipeline's own
        // RequestHandlerInterface::handle($request, $handler) face.
        // SecurityHeadersMiddleware::process() and RateLimitMiddleware::
        // process() are the family's fifth and sixth PSR-15 implementations
        // and carry the same face. CsrfMiddleware::process() is the seventh.
        // AuthMiddleware::process() is the eighth.
        'Rector\Naming\Rector\ClassMethod\RenameParamToMatchTypeRector' => [
            __DIR__ . '/src/Zef/Framework/Http/Message.php',
            __DIR__ . '/src/Zef/Framework/Http/ETagMiddleware.php',
            __DIR__ . '/src/Zef/Framework/Http/CorsMiddleware.php',
            __DIR__ . '/src/Zef/Framework/Http/ErrorMappingMiddleware.php',
            __DIR__ . '/src/Zef/Framework/Http/HeadMiddleware.php',
            __DIR__ . '/src/Zef/Framework/Http/SecurityHeadersMiddleware.php',
            __DIR__ . '/src/Zef/Framework/Http/RateLimitMiddleware.php',
            __DIR__ . '/src/Zef/Framework/Http/CsrfMiddleware.php',
            __DIR__ . '/src/Zef/Framework/Http/AuthMiddleware.php',
            __DIR__ . '/src/Zef/Framework/Middleware/MiddlewarePipeline.php',
        ],
        // The rule resolves the expected name from a method-call chain's
        // return type differently on different environments (a fresh CI
        // runner renames $response -> $message for an interface-typed
        // wither while a warm local checkout wants it renamed back),
        // which turns every run into a rename ping-pong. Test variable
        // naming is cosmetic; the analysers and the fixer stay in charge.
        'Rector\Naming\Rector\Assign\RenameVariableToMatchMethodCallReturnTypeRector' => [
            __DIR__ . '/tests',
        ],
        // The phpredis boundary's reply shapes are load-bearing: the
        // RedisCommandsInterface contract declares the union the phpredis
        // replies carry (INCR answers an int, or a numeric string under
        // non-default reply options), and narrowing that union to the one
        // shape this environment happens to produce would quietly break
        // every other implementation of the seam. The interface wins.
        'Rector\\DeadCode\\Rector\\FunctionLike\\NarrowWideUnionReturnTypeRector' => [
            __DIR__ . '/src/Zef/Framework/Http/PhpRedisCommands.php',
        ],
        // The closure form is load-bearing: psalm narrows array_filter()
        // through the typed closure parameter (mixed -> string), but not
        // through the first-class callable the rule would delegate to,
        // so the conversion trades a style win for a MixedReturnTypeCoercion
        // regression. The analysers win.
        'Rector\CodingStyle\Rector\ArrowFunction\ArrowFunctionDelegatingCallToFirstClassCallableRector' => [
            __DIR__ . '/src/Zef/Framework/Http/EmittedHeaders.php',
        ],
    ])
    ->withPhpSets(php84: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        naming: true,
        instanceOf: true,
        earlyReturn: true,
        rectorPreset: true,
        phpunitCodeQuality: true,
    );
