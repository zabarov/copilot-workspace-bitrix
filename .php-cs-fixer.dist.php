<?php

$directories = array_filter([
    __DIR__ . '/lib',
    __DIR__ . '/admin',
], 'is_dir');

$finder = PhpCsFixer\Finder::create()
    ->in($directories ?: [__DIR__]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        'ordered_imports' => true,
        'strict_param' => true,
        'declare_strict_types' => false,
        'no_unused_imports' => true,
        'single_quote' => true,
    ])
    ->setFinder($finder);
