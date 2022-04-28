<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\ControlStructure\YodaStyleFixer;
use PhpCsFixer\Fixer\PhpUnit\PhpUnitInternalClassFixer;
use PhpCsFixer\Fixer\PhpUnit\PhpUnitTestClassRequiresCoversFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Zing\CodingStandard\Set\ECSSetList;

return static function (ECSConfig $ecsConfig): void {
    $ecsConfig->sets([
        ECSSetList::PHP_72,
        ECSSetList::CUSTOM,
    ]);
    $ecsConfig->parallel();
    $ecsConfig->skip([
        YodaStyleFixer::class => null,
        PhpUnitInternalClassFixer::class,
        PhpUnitTestClassRequiresCoversFixer::class,
        \SlevomatCodingStandard\Sniffs\TypeHints\ReturnTypeHintSniff::class,
        \PHP_CodeSniffer\Standards\Squiz\Sniffs\Commenting\LongConditionClosingCommentSniff::class,
    ]);
    $ecsConfig->paths(
        [__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/ecs.php', __DIR__ . '/rector.php']
    );
};
