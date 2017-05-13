<?php

/*
 * This file is part of the Active Collab Jobs Queue.
 *
 * (c) A51 doo <info@activecollab.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

$header = <<<EOF
This file is part of the Active Collab Jobs Queue.

(c) A51 doo <info@activecollab.com>

This source file is subject to the MIT license that is bundled
with this source code in the file LICENSE.
EOF;

return (new PhpCsFixer\Config('psr2'))
    ->setRiskyAllowed(true)
    ->setRules([
        'header_comment' => [
            'header' => $header,
            'location' => 'after_open',
        ],
        'function_typehint_space' => true,
        'method_argument_space' => true,
        'no_trailing_whitespace' => true,
        'no_whitespace_before_comma_in_array' => true,
        'whitespace_after_comma_in_array' => true,
        'no_multiline_whitespace_around_double_arrow' => true,
        'hash_to_slash_comment' => true,
        'include' => true,
        'no_alias_functions' => true,
        'trailing_comma_in_multiline_array' => true,
        'no_leading_namespace_whitespace' => true,
        'no_blank_lines_after_phpdoc' => true,
        'phpdoc_scalar' => true,
        'phpdoc_summary' => true,
        'self_accessor' => true,
        'no_trailing_comma_in_singleline_array' => true,
        'single_blank_line_before_namespace' => true,
        'space_after_semicolon' => true,
        'no_singleline_whitespace_before_semicolons' => true,
        'cast_spaces' => true,
        'standardize_not_equals' => true,
        'ternary_operator_spaces' => true,
        'trim_array_spaces' => true,
        'no_unused_imports' => true,
        'no_whitespace_in_blank_line' => true,
        'ordered_imports' => true,
        'array_syntax' => ['syntax' => 'short'],
        'phpdoc_align' => true,
        'return_type_declaration' => true,
        'single_quote' => true,
        'phpdoc_separation' => false,
        'phpdoc_no_package' => false,
        'no_mixed_echo_print' => false,
        'concat_space' => false,
        'simplified_null_return' => false,
        'single_blank_line_at_eof' => true,
    ])
    ->setFinder((new PhpCsFixer\Finder())->in([__DIR__ . '/src', __DIR__ . '/test']));
