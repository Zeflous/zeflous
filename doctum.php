<?php

declare(strict_types=1);

use Doctum\Doctum;
use Symfony\Component\Finder\Finder;

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

return new Doctum($iterator, $options);
