<?php

declare(strict_types=1);

namespace Dirthara\Auth\Tests;

use stdClass;
use Generator;
use Dirthara\Auth\Authoriser;
use PHPUnit\Framework\TestCase;
use Dirthara\Auth\Contract\Policy;
use Dirthara\Auth\AuthorisationResult;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Auth\AuthorisationContext;
use Dirthara\Auth\Tests\Fixtures\FixedPolicy;
use Dirthara\Auth\Exception\AmbiguousPolicyException;

final class AuthoriserTest extends TestCase
{
    #[Test]
    public function it_does_not_apply_without_policies(): void
    {
        self::assertTrue(
            new Authoriser([])
                ->authorise(self::context())
                ->isNotApplicable(),
        );
    }

    #[Test]
    public function it_does_not_apply_when_no_policy_applies(): void
    {
        $authoriser = new Authoriser([
            new FixedPolicy(AuthorisationResult::notApplicable()),
            new FixedPolicy(AuthorisationResult::notApplicable()),
        ]);

        self::assertTrue($authoriser->authorise(self::context())->isNotApplicable());
    }

    #[Test]
    public function it_returns_the_result_of_the_one_policy_that_applies(): void
    {
        $allowed = AuthorisationResult::allowed();
        $denied = AuthorisationResult::denied();
        $notApplicable = new FixedPolicy(AuthorisationResult::notApplicable());

        self::assertSame(
            $allowed,
            new Authoriser([$notApplicable, new FixedPolicy($allowed), $notApplicable])->authorise(self::context()),
        );
        self::assertSame(
            $denied,
            new Authoriser([$notApplicable, new FixedPolicy($denied), $notApplicable])->authorise(self::context()),
        );
    }

    #[Test]
    public function it_asks_every_policy_about_the_same_context(): void
    {
        $first = new FixedPolicy(AuthorisationResult::allowed());
        $second = new FixedPolicy(AuthorisationResult::notApplicable());
        $context = self::context();

        new Authoriser([$first, $second])->authorise($context);

        self::assertSame([$context], $first->contexts);
        self::assertSame([$context], $second->contexts);
    }

    #[Test]
    public function it_refuses_to_decide_when_more_than_one_policy_applies(): void
    {
        $allowing = new FixedPolicy(AuthorisationResult::allowed());
        $denying = new FixedPolicy(AuthorisationResult::denied());
        $authoriser = new Authoriser([$allowing, new FixedPolicy(AuthorisationResult::notApplicable()), $denying]);

        try {
            $authoriser->authorise(self::context());
            self::fail('Expected an AmbiguousPolicyException.');
        } catch (AmbiguousPolicyException $exception) {
            self::assertSame([FixedPolicy::class, FixedPolicy::class], $exception->context['policies']);
        }
    }

    #[Test]
    public function it_refuses_to_decide_when_policies_that_apply_agree(): void
    {
        $authoriser = new Authoriser([
            new FixedPolicy(AuthorisationResult::allowed()),
            new FixedPolicy(AuthorisationResult::allowed()),
        ]);

        $this->expectException(AmbiguousPolicyException::class);

        $authoriser->authorise(self::context());
    }

    #[Test]
    public function it_takes_the_policies_from_a_generator(): void
    {
        $allowed = AuthorisationResult::allowed();
        $policies = self::generate(new FixedPolicy(AuthorisationResult::notApplicable()), new FixedPolicy($allowed));

        self::assertSame($allowed, new Authoriser($policies)->authorise(self::context()));
    }

    #[Test]
    public function it_keeps_every_policy_when_a_generator_repeats_a_key(): void
    {
        $policies = self::generateUnderOneKey(
            new FixedPolicy(AuthorisationResult::denied()),
            new FixedPolicy(AuthorisationResult::allowed()),
        );

        $this->expectException(AmbiguousPolicyException::class);

        new Authoriser($policies)->authorise(self::context());
    }

    private static function context(): AuthorisationContext
    {
        return new AuthorisationContext(new stdClass(), 'edit', new stdClass());
    }

    /**
     * @return Generator<int, Policy>
     */
    private static function generate(Policy ...$policies): Generator
    {
        foreach ($policies as $policy) {
            yield $policy;
        }
    }

    /**
     * @return Generator<string, Policy>
     */
    private static function generateUnderOneKey(Policy ...$policies): Generator
    {
        foreach ($policies as $policy) {
            yield 'policy' => $policy;
        }
    }
}
