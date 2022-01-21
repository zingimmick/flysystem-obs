<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\ReturnNotation\ReturnAssignmentFixer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symplify\EasyCodingStandard\ValueObject\Option;
use Zing\CodingStandard\Set\ECSSetList;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->import(ECSSetList::PHP_72);
    $containerConfigurator->import(ECSSetList::CUSTOM);

    $parameters = $containerConfigurator->parameters();
    $parameters->set(Option::PARALLEL, true);
    $parameters->set(Option::SKIP, [
        \PHP_CodeSniffer\Standards\PSR1\Sniffs\Methods\CamelCapsMethodNameSniff::class => [
            __DIR__ . '/tests/ObsAdapterTest.php',
        ],
        \PhpCsFixer\Fixer\PhpUnit\PhpUnitMethodCasingFixer::class => [__DIR__ . '/tests/ObsAdapterTest.php'],
        \PhpCsFixer\Fixer\PhpUnit\PhpUnitTestAnnotationFixer::class => [__DIR__ . '/tests/ObsAdapterTest.php'],
        // bug
        ReturnAssignmentFixer::class,
    ]);
    $parameters->set(
        Option::PATHS,
        [__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/ecs.php', __DIR__ . '/rector.php']
    );
};
