<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\WebTargetPath;
use PHPUnit\Framework\TestCase;

final class WebTargetPathTest extends TestCase
{
    public function testReturnsNullForNull(): void
    {
        self::assertNull(WebTargetPath::validate(null));
    }

    public function testReturnsNullForEmptyString(): void
    {
        self::assertNull(WebTargetPath::validate(''));
    }

    public function testReturnsNullForRootOnly(): void
    {
        self::assertNull(WebTargetPath::validate('/'));
    }

    public function testReturnsNullForOversizePath(): void
    {
        $tooLong = '/' . str_repeat('a', 2050);
        self::assertNull(WebTargetPath::validate($tooLong));
    }

    public function testReturnsNullForPathTraversal(): void
    {
        self::assertNull(WebTargetPath::validate('/foo/../bar'));
    }

    public function testReturnsNullForDoubleSlash(): void
    {
        self::assertNull(WebTargetPath::validate('/foo//bar'));
    }

    public function testReturnsNullForAbsoluteHttpUrl(): void
    {
        self::assertNull(WebTargetPath::validate('http://evil.com/x'));
    }

    public function testReturnsNullForAbsoluteHttpsUrl(): void
    {
        self::assertNull(WebTargetPath::validate('https://evil.com/x'));
    }

    public function testReturnsNullForProtocolRelativeUrl(): void
    {
        self::assertNull(WebTargetPath::validate('//evil.com/x'));
    }

    public function testReturnsNullForLoginPath(): void
    {
        self::assertNull(WebTargetPath::validate('/login'));
    }

    public function testReturnsNullForLoginSubPath(): void
    {
        self::assertNull(WebTargetPath::validate('/login/foo'));
    }

    public function testReturnsNullForLogoutPath(): void
    {
        self::assertNull(WebTargetPath::validate('/logout'));
    }

    public function testReturnsNullForLogoutSubPath(): void
    {
        self::assertNull(WebTargetPath::validate('/logout/extra'));
    }

    public function testAcceptsSimpleRelativePath(): void
    {
        self::assertSame('/discover', WebTargetPath::validate('/discover'));
    }

    public function testAcceptsPathWithQuery(): void
    {
        self::assertSame('/x?y=1', WebTargetPath::validate('/x?y=1'));
    }

    public function testAcceptsNestedPath(): void
    {
        self::assertSame('/events/123/posts', WebTargetPath::validate('/events/123/posts'));
    }
}
