<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

use actra\autoloader\Autoloader;
use actra\autoloader\AutoloaderPath;

require __DIR__ . '/../vendor/autoload.php';

// yuf has no Composer autoload configuration: its classes are loaded by actra/autoloader, as in the projects
$autoloaderCacheFilePath = __DIR__ . '/../.phpunit.cache/autoloader.php';
if (file_exists(filename: $autoloaderCacheFilePath)) {
    // Avoid stale class paths after files have been moved
    unlink(filename: $autoloaderCacheFilePath);
}
$autoloader = Autoloader::register(cacheFilePath: $autoloaderCacheFilePath);
$autoloader->addPath(
    autoloaderPath: new AutoloaderPath(
        path: __DIR__ . '/../vendor/actra/yuf/src/',
        prefix: 'actra\\yuf\\',
    ),
);
