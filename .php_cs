<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/handlers')
    ->in(__DIR__ . '/src')
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true)
;

return PhpCsFixer\Config::create()
    ->setRules(array(
        '@Symfony' => true,

        'yoda_style' => false,
        'phpdoc_order' => true,
        'new_with_braces' => false,
        'short_scalar_cast' => true,
        'phpdoc_to_comment' => false,
        'single_line_throw' => false,
        'single_blank_line_at_eof' => true,
        'no_superfluous_phpdoc_tags' => false,
        'linebreak_after_opening_tag' => true,
        'class_attributes_separation' => false,
        'not_operator_with_successor_space' => false,
        'single_trait_insert_per_statement' => false,

        'concat_space' => [
            'spacing' => 'one',
        ],
        'binary_operator_spaces' => [
            'operators' => [
                '='  => 'align_single_space',
                '=>' => 'align_single_space',
            ],
        ],
        'array_syntax' => [
            'syntax' => 'short'
        ],
        'ordered_imports' => [
            'sort_algorithm' => 'length',
        ],
        'cast_spaces' => [
            'space' => 'none',
        ],
        'align_multiline_comment' => [
            'comment_type' => 'phpdocs_like',
        ],
        'phpdoc_align' => [
            'align' => 'vertical',
        ],
        'increment_style' => [
            'style' => 'post',
        ],
        'phpdoc_no_alias_tag' => [
            'type' => 'var',
            'link' => 'see',
        ],
        'no_extra_blank_lines' => [
            'tokens' => [],
        ],
    ))
    ->setFinder($finder)
;
