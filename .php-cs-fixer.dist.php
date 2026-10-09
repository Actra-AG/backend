<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

/** @var array<string, bool|array<string, mixed>> $rules */
$rules = require __DIR__ . '/vendor/actra/coding-standard/config/php-cs-fixer.php';
$header = (require __DIR__ . '/vendor/actra/coding-standard/config/php-cs-fixer-header.php')(
    copyright: 'Actra AG - https://www.actra.ch',
    // MIT for public libraries, proprietary for closed projects
    license: 'MIT',
    // Folders with code adapted from third-party libraries keep its license (see standards/php.md, section 2), e.g.
    // ['path' => '/src/phone/', 'license' => 'Apache-2.0']
    thirdParty: [],
);

return new Config()
    ->setRiskyAllowed(true)
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRules([
        ...$rules,
        'header_comment' => $header['rule'],
    ])
    ->setRuleCustomisationPolicy($header['policy'])
    ->setFinder(
        Finder::create()
            ->in([
                __DIR__ . '/src',
                __DIR__ . '/tests',
            ])
            ->append([__FILE__]),
    );
