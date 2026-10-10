<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Every PHP file declares strict types, also outside the paths of PHP-CS-Fixer. The `.gitignore` whitelists the tracked
 * files, so the test scans the root files and the tracked directories with PHP code.
 */
final class StrictTypesTest extends TestCase
{
    private const array DIRECTORIES = ['db', 'docs', 'src', 'tests'];

    public function testEveryPhpFileDeclaresStrictTypes(): void
    {
        $root = dirname(path: __DIR__, levels: 2);
        $rootFiles = glob(pattern: $root . '/*.php');
        $paths = $rootFiles === false ? [] : $rootFiles;
        foreach (StrictTypesTest::DIRECTORIES as $directory) {
            $files = new RecursiveIteratorIterator(
                iterator: new RecursiveDirectoryIterator(
                    directory: $root . '/' . $directory,
                    flags: FilesystemIterator::SKIP_DOTS,
                ),
            );
            foreach ($files as $file) {
                if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
                    $paths[] = $file->getPathname();
                }
            }
        }
        $missing = [];
        foreach ($paths as $path) {
            $code = file_get_contents(filename: $path);
            if ($code === false || preg_match(pattern: '/^declare\(strict_types=1\);$/m', subject: $code) !== 1) {
                $missing[] = $path;
            }
        }

        $this->assertNotSame([], $paths);
        $this->assertSame([], $missing);
    }
}
