<?php

declare(strict_types=1);

namespace Dirthara\Authorisation\Tests\Testing;

use stdClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Authorisation\AuthorisationDenial;
use Dirthara\Authorisation\AuthorisationResult;
use Dirthara\Authorisation\Contract\Authoriser;
use Dirthara\Authorisation\AuthorisationContext;
use Dirthara\Authorisation\Testing\FakeAuthoriser;

final class FakeAuthoriserTest extends TestCase
{
    #[Test]
    public function it_returns_the_same_result_for_every_context(): void
    {
        $result = AuthorisationResult::denied(new AuthorisationDenial('You can only edit posts you own.'));
        $authoriser = new FakeAuthoriser($result);

        self::assertInstanceOf(Authoriser::class, $authoriser);
        self::assertSame($result, $authoriser->authorise(self::context('edit')));
        self::assertSame($result, $authoriser->authorise(self::context('delete')));
    }

    #[Test]
    public function it_asks_a_closure_for_each_result(): void
    {
        $authoriser = new FakeAuthoriser(
            static fn(AuthorisationContext $context): AuthorisationResult => $context->ability === 'edit'
                ? AuthorisationResult::allowed()
                : AuthorisationResult::denied(),
        );

        self::assertTrue($authoriser->authorise(self::context('edit'))->isAllowed());
        self::assertTrue($authoriser->authorise(self::context('delete'))->isDenied());
    }

    #[Test]
    public function it_records_every_context_in_order(): void
    {
        $authoriser = FakeAuthoriser::allowing();
        $first = self::context('edit');
        $second = self::context('edit');

        $authoriser->authorise($first);
        $authoriser->authorise($second);

        self::assertSame([$first, $second], $authoriser->contexts);
    }

    #[Test]
    public function it_starts_without_contexts(): void
    {
        self::assertSame([], FakeAuthoriser::allowing()->contexts);
    }

    #[Test]
    public function it_has_a_factory_for_each_status(): void
    {
        self::assertTrue(FakeAuthoriser::allowing()->authorise(self::context('edit'))->isAllowed());
        self::assertTrue(FakeAuthoriser::denying()->authorise(self::context('edit'))->isDenied());
        self::assertTrue(FakeAuthoriser::notApplicable()->authorise(self::context('edit'))->isNotApplicable());
    }

    private static function context(string $ability): AuthorisationContext
    {
        return new AuthorisationContext(new stdClass(), $ability, new stdClass());
    }
}
