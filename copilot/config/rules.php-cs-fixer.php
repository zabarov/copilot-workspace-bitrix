<?php

declare(strict_types=1);

$root = __DIR__ . '/../../..';
$moduleDir = $root . '/local/modules/your.module';
$paths = [];
foreach (['/lib', '/admin'] as $suffix) {
    if (is_dir($moduleDir . $suffix)) {
        $paths[] = $moduleDir . $suffix;
    }
}
if (!$paths) {
    $paths[] = $root;
}

$finder = PhpCsFixer\Finder::create()
    ->in($paths)
    ->exclude(['vendor', 'bitrix', 'upload']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'single_quote' => true,
        'blank_line_before_statement' => true,
        'no_trailing_whitespace' => true,
        'no_whitespace_in_blank_line' => true,
        'ordered_imports' => true,
        'strict_param' => true,
        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_no_useless_inheritdoc' => true,
    ])
    ->setFinder($finder);
