<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class StrictTypesTest extends TestCase
{
    // Paths relative to the project root without own PHP code, with generated code or with local scratch files
    private const array EXCLUDED = ['.ddev', '.git', '.idea', '.phpunit.cache', '.scratch-tmp', 'vendor'];

    public function testEveryPhpFileDeclaresStrictTypes(): void
    {
        $root = dirname(path: __DIR__, levels: 2);
        $files = new RecursiveIteratorIterator(
            iterator: new RecursiveCallbackFilterIterator(
                iterator: new RecursiveDirectoryIterator(directory: $root, flags: FilesystemIterator::SKIP_DOTS),
                callback: static fn(SplFileInfo $file): bool => !in_array(
                    needle: substr(string: $file->getPathname(), offset: strlen(string: $root) + 1),
                    haystack: self::EXCLUDED,
                    strict: true,
                ),
            ),
        );
        $missing = [];
        foreach ($files as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $code = file_get_contents(filename: $file->getPathname());
            if ($code === false || preg_match(pattern: '/^declare\(strict_types=1\);$/m', subject: $code) !== 1) {
                $missing[] = $file->getPathname();
            }
        }

        self::assertSame([], $missing);
    }
}
