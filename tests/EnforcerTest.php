<?php

declare(strict_types=1);

namespace Dirthara\Authorisation\Tests;

use stdClass;
use PHPUnit\Framework\TestCase;
use Dirthara\Authorisation\Enforcer;
use Dirthara\Authorisation\Authoriser;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Authorisation\AuthorisationDenial;
use Dirthara\Authorisation\AuthorisationResult;
use Dirthara\Authorisation\AuthorisationContext;
use Dirthara\Authorisation\Tests\Fixtures\FixedPolicy;
use Dirthara\Authorisation\Exception\AuthorisationDeniedException;

final class EnforcerTest extends TestCase
{
    #[Test]
    public function it_returns_an_allowed_result_with_its_policy(): void
    {
        $allowing = new FixedPolicy(AuthorisationResult::allowed());

        $result = new Enforcer(new Authoriser([$allowing]))->ensure(self::context());

        self::assertTrue($result->isAllowed());
        self::assertSame($allowing, $result->policy);
    }

    #[Test]
    public function it_throws_the_denied_result(): void
    {
        $denial = new AuthorisationDenial('You can only edit posts you own.');
        $denying = new FixedPolicy(AuthorisationResult::denied($denial));

        try {
            new Enforcer(new Authoriser([$denying]))->ensure(self::context());
            self::fail('The authorisation was not denied.');
        } catch (AuthorisationDeniedException $exception) {
            self::assertTrue($exception->result->isDenied());
            self::assertSame($denying, $exception->result->policy);
            self::assertSame($denial, $exception->result->denial);
            self::assertSame(
                'The authorisation of "edit" was denied by ' . FixedPolicy::class . '.',
                $exception->getMessage(),
            );
        }
    }

    #[Test]
    public function it_throws_when_no_policy_applies(): void
    {
        $authoriser = new Authoriser([new FixedPolicy(AuthorisationResult::notApplicable())]);

        try {
            new Enforcer($authoriser)->ensure(self::context());
            self::fail('The authorisation was not denied.');
        } catch (AuthorisationDeniedException $exception) {
            self::assertTrue($exception->result->isNotApplicable());
            self::assertSame(
                'The authorisation of "edit" was denied because no policy applies to it.',
                $exception->getMessage(),
            );
        }
    }

    #[Test]
    public function it_allows_only_an_allowed_result(): void
    {
        $context = self::context();
        $allowing = new Authoriser([new FixedPolicy(AuthorisationResult::allowed())]);
        $denying = new Authoriser([new FixedPolicy(AuthorisationResult::denied())]);

        self::assertTrue(new Enforcer($allowing)->allows($context));
        self::assertFalse(new Enforcer($denying)->allows($context));
        self::assertFalse(new Enforcer(new Authoriser([]))->allows($context));
    }

    private static function context(): AuthorisationContext
    {
        return new AuthorisationContext(new stdClass(), 'edit', new stdClass());
    }
}
