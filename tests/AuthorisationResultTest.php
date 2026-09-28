<?php

declare(strict_types=1);

namespace Dirthara\Auth\Tests;

use PHPUnit\Framework\TestCase;
use Dirthara\Auth\AuthorisationResult;
use Dirthara\Auth\AuthorisationStatus;
use PHPUnit\Framework\Attributes\Test;

final class AuthorisationResultTest extends TestCase
{
    #[Test]
    public function it_allows(): void
    {
        $result = AuthorisationResult::allowed();

        self::assertSame(AuthorisationStatus::Allowed, $result->status);
        self::assertTrue($result->isAllowed());
        self::assertFalse($result->isDenied());
        self::assertFalse($result->isNotApplicable());
    }

    #[Test]
    public function it_denies(): void
    {
        $result = AuthorisationResult::denied();

        self::assertSame(AuthorisationStatus::Denied, $result->status);
        self::assertFalse($result->isAllowed());
        self::assertTrue($result->isDenied());
        self::assertFalse($result->isNotApplicable());
    }

    #[Test]
    public function it_does_not_apply(): void
    {
        $result = AuthorisationResult::notApplicable();

        self::assertSame(AuthorisationStatus::NotApplicable, $result->status);
        self::assertFalse($result->isAllowed());
        self::assertFalse($result->isDenied());
        self::assertTrue($result->isNotApplicable());
    }
}
