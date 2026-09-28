<?php

declare(strict_types=1);

namespace Dirthara\Auth\Tests;

use PHPUnit\Framework\TestCase;
use Dirthara\Auth\AuthorisationResult;
use Dirthara\Auth\AuthorisationStatus;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Auth\Tests\Fixtures\FixedPolicy;

final class AuthorisationResultTest extends TestCase
{
    #[Test]
    public function it_allows(): void
    {
        $result = AuthorisationResult::allowed();

        self::assertSame(AuthorisationStatus::Allowed, $result->status);
        self::assertNull($result->policy);
        self::assertTrue($result->isAllowed());
        self::assertFalse($result->isDenied());
        self::assertFalse($result->isNotApplicable());
    }

    #[Test]
    public function it_denies(): void
    {
        $result = AuthorisationResult::denied();

        self::assertSame(AuthorisationStatus::Denied, $result->status);
        self::assertNull($result->policy);
        self::assertFalse($result->isAllowed());
        self::assertTrue($result->isDenied());
        self::assertFalse($result->isNotApplicable());
    }

    #[Test]
    public function it_does_not_apply(): void
    {
        $result = AuthorisationResult::notApplicable();

        self::assertSame(AuthorisationStatus::NotApplicable, $result->status);
        self::assertNull($result->policy);
        self::assertFalse($result->isAllowed());
        self::assertFalse($result->isDenied());
        self::assertTrue($result->isNotApplicable());
    }

    #[Test]
    public function it_attaches_a_policy_to_an_allowed_result_as_a_new_result(): void
    {
        $result = AuthorisationResult::allowed();
        $policy = new FixedPolicy($result);

        $withPolicy = $result->withPolicy($policy);

        self::assertNotSame($result, $withPolicy);
        self::assertTrue($withPolicy->isAllowed());
        self::assertSame($policy, $withPolicy->policy);
        self::assertTrue($result->isAllowed());
        self::assertNull($result->policy);
    }

    #[Test]
    public function it_attaches_a_policy_to_a_denied_result_as_a_new_result(): void
    {
        $result = AuthorisationResult::denied();
        $policy = new FixedPolicy($result);

        $withPolicy = $result->withPolicy($policy);

        self::assertNotSame($result, $withPolicy);
        self::assertTrue($withPolicy->isDenied());
        self::assertSame($policy, $withPolicy->policy);
        self::assertTrue($result->isDenied());
        self::assertNull($result->policy);
    }

    #[Test]
    public function it_keeps_the_exact_policy_instance(): void
    {
        $result = AuthorisationResult::allowed();
        $policy = new FixedPolicy($result);
        $otherPolicy = new FixedPolicy($result);

        $withPolicy = $result->withPolicy($policy);

        self::assertSame($policy, $withPolicy->policy);
        self::assertNotSame($otherPolicy, $withPolicy->policy);
        self::assertSame($otherPolicy, $withPolicy->withPolicy($otherPolicy)->policy);
        self::assertSame($policy, $withPolicy->policy);
    }
}
