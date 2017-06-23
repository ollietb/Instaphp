<?php
$header = <<<'EOF'
The MIT License (MIT)
Copyright © 2013 Randy Sesser <randy@instaphp.com>
Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the “Software”), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:
The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.
THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
@author Randy Sesser <randy@instaphp.com>
@filesource
EOF;

return PhpCsFixer\Config::create()
    ->setRiskyAllowed(true)
    ->setRules(array(
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'array_syntax' => array('syntax' => 'short'),
        'combine_consecutive_unsets' => true,
        // one should use PHPUnit methods to set up expected exception instead of annotations
        //'general_phpdoc_annotation_remove' => array('expectedException', 'expectedExceptionMessage', 'expectedExceptionMessageRegExp'),
        'header_comment' => array('header' => $header),
        'heredoc_to_nowdoc' => true,
        'no_extra_consecutive_blank_lines' => array('break', 'continue', 'extra', 'return', 'throw', 'use', 'parenthesis_brace_block', 'square_brace_block', 'curly_brace_block'),
        'no_unreachable_default_argument_value' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'ordered_class_elements' => true,
        'ordered_imports' => true,
        'php_unit_strict' => true,
        'php_unit_fqcn_annotation' => false,
        'phpdoc_add_missing_param_annotation' => true,
        'phpdoc_order' => true,
        'psr4' => true,
        //'strict_comparison' => true,
        //'strict_param' => true,
        'phpdoc_to_comment' => false,
        'binary_operator_spaces' => array('align_double_arrow' => true, 'align_equals' => true),
    ))
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->exclude('tests/Fixtures')
            ->in(__DIR__)
    )
;
