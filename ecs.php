<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Import\FullyQualifiedStrictTypesFixer;
use PhpCsFixer\Fixer\Import\NoUnusedImportsFixer;
use PhpCsFixer\Fixer\StringNotation\SingleQuoteFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withPaths([
        __DIR__ . '/ecs.php',
        __DIR__ . '/bin',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPreparedSets(psr12: true)
    ->withRules([
        NoUnusedImportsFixer::class,
    ])
    ->withConfiguredRule(SingleQuoteFixer::class, [])
    ->withConfiguredRule(FullyQualifiedStrictTypesFixer::class, [
        'import_symbols' => true,
    ]);
