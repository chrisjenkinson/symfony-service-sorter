<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/bin',
    ])
    ->withPreparedSets(psr12: true)
    ->withConfiguredRule(\PhpCsFixer\Fixer\Import\FullyQualifiedStrictTypesFixer::class, [
        'import_symbols' => true,
    ]);
