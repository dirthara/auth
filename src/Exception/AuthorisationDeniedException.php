<?php

declare(strict_types=1);

namespace Dirthara\Authorisation\Exception;

use Throwable;
use RuntimeException;
use Dirthara\Authorisation\AuthorisationResult;
use Dirthara\Authorisation\AuthorisationContext;

use function sprintf;
use function get_debug_type;

final class AuthorisationDeniedException extends RuntimeException implements AuthorisationException
{
    use HasExceptionContext;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public readonly AuthorisationResult $result,
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        array $context = [],
    ) {
        parent::__construct($message, $code, $previous);

        $this->context = $context;
    }

    public static function denied(AuthorisationContext $context, AuthorisationResult $result): self
    {
        return new self(
            result: $result,
            message: $result->policy === null
                ? sprintf('The authorisation of "%s" was denied.', self::printableAbility($context->ability))
                : sprintf(
                    'The authorisation of "%s" was denied by %s.',
                    self::printableAbility($context->ability),
                    get_debug_type($result->policy),
                ),
            context: self::contextFor($context, $result),
        );
    }

    public static function noPolicyApplies(AuthorisationContext $context, AuthorisationResult $result): self
    {
        return new self(
            result: $result,
            message: sprintf(
                'The authorisation of "%s" was denied because no policy applies to it.',
                self::printableAbility($context->ability),
            ),
            context: self::contextFor($context, $result),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function contextFor(AuthorisationContext $context, AuthorisationResult $result): array
    {
        return [
            'ability' => $context->ability,
            'actorType' => get_debug_type($context->actor),
            'subjectType' => get_debug_type($context->subject),
            'status' => $result->status,
            'policy' => $result->policy === null ? null : get_debug_type($result->policy),
            'messageKey' => $result->denial?->messageKey,
        ];
    }
}
