<?php

declare(strict_types=1);

namespace Dirthara\Authorisation\Exception;

use Throwable;
use RuntimeException;
use Dirthara\Authorisation\Contract\Policy;

use function sprintf;

final class NotApplicableResultException extends RuntimeException implements AuthorisationException
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

    public static function cannotHavePolicy(Policy $policy): self
    {
        return new self(
            message: sprintf(
                'A not applicable authorisation result cannot have a deciding policy, but %s was attached to one.',
                $policy::class,
            ),
            context: ['policy' => $policy::class],
        );
    }
}
