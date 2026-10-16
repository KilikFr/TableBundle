<?php

$finder = (new PhpCsFixer\Finder())
    ->in(['src/', 'tests/'])
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'combine_consecutive_unsets' => true,
        // keep existing declare(strict_types=1) statements (removed by @Symfony:risky since PHP CS Fixer 3.9x)
        'declare_strict_types' => false,
        // adding ": void" to public or protected methods breaks child classes overriding them
        'void_return' => false,
        // keep inline @var annotations, used by static analysis
        'phpdoc_to_comment' => ['ignored_tags' => ['var']],
        'no_superfluous_phpdoc_tags' => true,
        'phpdoc_separation' => false,
        'phpdoc_types_order' => false,
        'native_function_invocation' => false,
        'single_line_throw' => false,
        'heredoc_to_nowdoc' => true,
        'no_extra_blank_lines' => ['tokens' => [
            'break', 'continue', 'extra', 'return', 'throw', 'use',
            'parenthesis_brace_block', 'square_brace_block', 'curly_brace_block',
        ]],
        'no_unreachable_default_argument_value' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'ordered_class_elements' => true,
        'ordered_imports' => true,
        'phpdoc_order' => true,
        'psr_autoloading' => true,
    ])
    ->setUsingCache(false)
    ->setRiskyAllowed(true)
    ->setFinder($finder)
;
