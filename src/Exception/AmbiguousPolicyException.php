<?php

declare(strict_types=1);

namespace Dirthara\Authorisation\Exception;

use UnitEnum;
use Throwable;
use RuntimeException;
use Dirthara\Authorisation\Contract\Policy;
use Dirthara\Authorisation\AuthorisationContext;

use function sprintf;
use function get_debug_type;

final class AmbiguousPolicyException extends RuntimeException implements AuthorisationException
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

    public static function forContext(AuthorisationContext $context, Policy $first, Policy $second): self
    {
        $ability = $context->ability instanceof UnitEnum
            ? $context->ability::class . '::' . $context->ability->name
            : self::printable($context->ability);

        return new self(
            message: sprintf(
                'The authorisation of "%s" is ambiguous: both %s and %s apply to it.',
                $ability,
                get_debug_type($first),
                get_debug_type($second),
            ),
            context: [
                'ability' => $context->ability,
                'actorType' => get_debug_type($context->actor),
                'subjectType' => get_debug_type($context->subject),
                'policies' => [get_debug_type($first), get_debug_type($second)],
            ],
        );
    }
}
