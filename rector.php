<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\BooleanOr\RepeatedOrEqualToInArrayRector;
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\BooleanAnd\BinaryOpNullableToInstanceofRector;

return RectorConfig::configure()
    ->withPaths([__DIR__.'/packages', __DIR__.'/tests', __DIR__.'/workbench'])
    ->withPhpSets(php85: true)
    ->withPreparedSets(deadCode: true, codeQuality: true, typeDeclarations: true, earlyReturn: true)
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withSkip([
        // `$x === null` reads better than `! $x instanceof SomeClass` for nullable objects.
        FlipTypeControlToUseExclusiveTypeRector::class,
        BinaryOpNullableToInstanceofRector::class,
        // `$a === null || $a === ''` reads better than in_array() with a strict flag.
        RepeatedOrEqualToInArrayRector::class,
    ]);
