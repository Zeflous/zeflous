<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/benchmarks'])
    ->notPath('#Contracts/Psr#')
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new Config())
    ->setRiskyAllowed(true)
    ->setUsingCache(true)
    ->setCacheFile('.php-cs-fixer.cache')
    ->setRules([
        '@PSR12' => true,
        '@PSR12:risky' => true,
        '@PHP80Migration:risky' => true,
        '@PHP84Migration' => true,
        '@PhpCsFixer' => true,
        '@PhpCsFixer:risky' => true,
        'declare_strict_types' => true,
        'strict_param' => true,
        'strict_comparison' => true,
        'void_return' => true,
        'ordered_class_elements' => true,
        'ordered_interfaces' => true,
        // Natural (non-Yoda) comparisons: authoritative here, enforced by the
        // PHPCS Slevomat DisallowYodaComparison sniff. Overrides @PhpCsFixer.
        'yoda_style' => false,
        // Keep empty bodies multi-line: the PHPCS PSR2/Squiz sniffs require it.
        'single_line_empty_body' => false,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => null,
            'import_functions' => null,
        ],
        'native_function_invocation' => [
            'include' => ['@compiler_optimized'],
            'scope' => 'namespaced',
            'strict' => true,
        ],
        'native_constant_invocation' => ['strict' => true],
        'no_superfluous_phpdoc_tags' => [
            'allow_mixed' => false,
            'remove_inheritdoc' => false,
        ],
        'php_unit_strict' => true,
        'fully_qualified_strict_types' => true,
        'nullable_type_declaration_for_default_null_value' => true,
        'no_unneeded_control_parentheses' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'no_unreachable_default_argument_value' => true,
        'self_static_accessor' => true,
        'static_lambda' => true,
        'logical_operators' => true,
        'is_null' => true,
        'combine_nested_dirname' => true,
        'no_alias_functions' => true,
        'fopen_flags' => true,
        'non_printable_character' => true,
        'no_trailing_whitespace_in_string' => true,
        'ternary_to_null_coalescing' => true,
        'simplified_null_return' => true,
        'array_push' => true,
        'modernize_strpos' => true,
        'get_class_to_class_keyword' => true,
        'date_time_immutable' => true,
        'mb_str_functions' => true,
        'final_internal_class' => true,
    ])
    ->setFinder($finder);
