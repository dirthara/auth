<?php

declare(strict_types=1);

namespace Dirthara\Auth\Tests;

use stdClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Auth\AuthorisationContext;
use Dirthara\Auth\Tests\Fixtures\Ability;

final class AuthorisationContextTest extends TestCase
{
    #[Test]
    public function it_holds_the_actor_the_ability_and_the_subject(): void
    {
        $actor = new stdClass();
        $subject = new stdClass();

        $context = new AuthorisationContext($actor, 'edit', $subject);

        self::assertSame($actor, $context->actor);
        self::assertSame('edit', $context->ability);
        self::assertSame($subject, $context->subject);
    }

    #[Test]
    public function it_takes_an_enum_as_the_ability_and_has_no_subject_by_default(): void
    {
        $context = new AuthorisationContext(new stdClass(), Ability::Edit);

        self::assertSame(Ability::Edit, $context->ability);
        self::assertNull($context->subject);
    }
}
