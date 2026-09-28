<?php

declare(strict_types=1);

namespace Dirthara\Authorisation\Tests\Exception;

use stdClass;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Authorisation\Contract\Policy;
use Dirthara\Authorisation\Exception\AuthorisationException;
use Dirthara\Authorisation\Exception\InvalidPolicyException;

final class InvalidPolicyExceptionTest extends TestCase
{
    #[Test]
    public function it_names_the_position_and_the_type_of_the_value(): void
    {
        $exception = InvalidPolicyException::forValue(new stdClass(), 2);

        self::assertInstanceOf(AuthorisationException::class, $exception);
        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertSame(
            'Every policy must implement ' . Policy::class . ', but the policy at position 2 is stdClass.',
            $exception->getMessage(),
        );
        self::assertSame(['position' => 2, 'type' => stdClass::class], $exception->context);
    }

    #[Test]
    public function it_records_a_scalar_by_its_type_only(): void
    {
        $exception = InvalidPolicyException::forValue("secret\nvalue", 0);

        self::assertSame(
            'Every policy must implement ' . Policy::class . ', but the policy at position 0 is string.',
            $exception->getMessage(),
        );
        self::assertSame(['position' => 0, 'type' => 'string'], $exception->context);
    }
}
