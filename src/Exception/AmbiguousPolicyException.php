<?php

declare(strict_types=1);

namespace Dirthara\Auth\Exception;

use Throwable;
use LogicException;
use Dirthara\Auth\AuthorisationContext;

final class AmbiguousPolicyException extends LogicException implements AuthException
{
    use HasExceptionContext;

    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);

        $this->context = $context;
    }

    public static function forContext(AuthorisationContext $context): self
    {
        return new self(message: 'The authorisation is ambiguous, there are multiple policies applicable for this context.', context: [
            'context' => $context,
        ]);
    }
}
