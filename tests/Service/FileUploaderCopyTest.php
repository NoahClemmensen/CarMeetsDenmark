<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\FileUploader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class FileUploaderCopyTest extends TestCase
{
    public function testCopyDuplicatesFileAndReturnsNewName(): void
    {
        $dir = sys_get_temp_dir() . '/fu-' . uniqid('', true);
        mkdir($dir);
        file_put_contents($dir . '/banner-abc.png', 'PNGDATA');

        $uploader = new FileUploader(new AsciiSlugger());
        $newName = $uploader->copy($dir, 'banner-abc.png');

        self::assertNotSame('banner-abc.png', $newName);
        self::assertFileExists($dir . '/' . $newName);
        self::assertSame('PNGDATA', file_get_contents($dir . '/' . $newName));
        self::assertFileExists($dir . '/banner-abc.png'); // original untouched

        // cleanup
        @unlink($dir . '/banner-abc.png');
        @unlink($dir . '/' . $newName);
        @rmdir($dir);
    }

    public function testCopyOfMissingFileReturnsOriginalName(): void
    {
        $dir = sys_get_temp_dir() . '/fu-' . uniqid('', true);
        mkdir($dir);

        $uploader = new FileUploader(new AsciiSlugger());
        $result = $uploader->copy($dir, 'does-not-exist.png');

        self::assertSame('does-not-exist.png', $result);

        @rmdir($dir);
    }
}
