<?php

declare(strict_types=1);

namespace Dirthara\Authorisation\Tests\Exception;

use stdClass;
use LogicException;
use RuntimeException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Authorisation\AuthorisationResult;
use Dirthara\Authorisation\AuthorisationContext;
use Dirthara\Authorisation\Tests\Fixtures\Ability;
use Dirthara\Authorisation\Exception\AuthException;
use Dirthara\Authorisation\Tests\Fixtures\FixedPolicy;
use Dirthara\Authorisation\Exception\AmbiguousPolicyException;

final class AmbiguousPolicyExceptionTest extends TestCase
{
    #[Test]
    public function it_names_the_ability_and_the_policies_that_apply(): void
    {
        $exception = self::exceptionFor(new AuthorisationContext(new stdClass(), 'edit'));

        self::assertInstanceOf(AuthException::class, $exception);
        self::assertInstanceOf(RuntimeException::class, $exception);
        self::assertSame(
            'The authorisation of "edit" is ambiguous: both '
            . FixedPolicy::class
            . ' and '
            . FixedPolicy::class
            . ' apply to it.',
            $exception->getMessage(),
        );
    }

    #[Test]
    public function it_names_an_enum_ability_by_its_case(): void
    {
        $exception = self::exceptionFor(new AuthorisationContext(new stdClass(), Ability::Edit));

        self::assertStringStartsWith(
            'The authorisation of "' . Ability::class . '::Edit" is ambiguous',
            $exception->getMessage(),
        );
        self::assertSame(Ability::Edit, $exception->context['ability']);
    }

    #[Test]
    public function it_escapes_control_characters_in_the_ability(): void
    {
        $exception = self::exceptionFor(new AuthorisationContext(new stdClass(), "edit\nforged"));

        self::assertStringStartsWith('The authorisation of "edit\\nforged" is ambiguous', $exception->getMessage());
    }

    #[Test]
    public function it_records_the_actor_and_the_subject_by_type_only(): void
    {
        $exception = self::exceptionFor(new AuthorisationContext(new stdClass(), 'edit', new LogicException()));

        self::assertSame(
            [
                'ability' => 'edit',
                'actorType' => stdClass::class,
                'subjectType' => LogicException::class,
                'policies' => [FixedPolicy::class, FixedPolicy::class],
            ],
            $exception->context,
        );
    }

    #[Test]
    public function it_records_a_missing_subject_as_null(): void
    {
        $exception = self::exceptionFor(new AuthorisationContext(new stdClass(), 'create'));

        self::assertSame('null', $exception->context['subjectType']);
    }

    private static function exceptionFor(AuthorisationContext $context): AmbiguousPolicyException
    {
        return AmbiguousPolicyException::forContext(
            $context,
            new FixedPolicy(AuthorisationResult::allowed()),
            new FixedPolicy(AuthorisationResult::denied()),
        );
    }
}
