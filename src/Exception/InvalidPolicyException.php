<?php

declare(strict_types=1);

namespace Dirthara\Authorisation\Exception;

use Throwable;
use InvalidArgumentException;
use Dirthara\Authorisation\Contract\Policy;

use function sprintf;
use function get_debug_type;

final class InvalidPolicyException extends InvalidArgumentException implements AuthorisationException
{
    use HasExceptionContext;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);

        $this->context = $context;
    }

    public static function forValue(mixed $value, int $position): self
    {
        return new self(
            message: sprintf(
                'Every policy must implement %s, but the policy at position %d is %s.',
                Policy::class,
                $position,
                get_debug_type($value),
            ),
            context: ['position' => $position, 'type' => get_debug_type($value)],
        );
    }
}
