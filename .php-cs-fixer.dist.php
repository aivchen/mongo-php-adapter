<?php

use PHPyh\CodingStandard\PhpCsFixerCodingStandard;

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__);

$config = (new PhpCsFixer\Config())
    ->setFinder($finder);

(new PhpCsFixerCodingStandard())->applyTo($config, [
    'final_class' => false,
    'final_public_method_for_abstract_class' => false,
    'date_time_immutable' => false,
    'declare_strict_types' => false,
    'strict_comparison' => false,
    'php_unit_data_provider_static' => false
]);

return $config;
