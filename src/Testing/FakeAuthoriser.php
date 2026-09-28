<?php

declare(strict_types=1);

namespace Dirthara\Authorisation\Testing;

use Closure;
use Dirthara\Authorisation\AuthorisationResult;
use Dirthara\Authorisation\Contract\Authoriser;
use Dirthara\Authorisation\AuthorisationContext;

final class FakeAuthoriser implements Authoriser
{
    /**
     * @var list<AuthorisationContext>
     */
    public private(set) array $contexts = [];

    /**
     * @param AuthorisationResult|Closure(AuthorisationContext): AuthorisationResult $result
     */
    public function __construct(
        private readonly AuthorisationResult|Closure $result,
    ) {}

    public static function allowing(): self
    {
        return new self(AuthorisationResult::allowed());
    }

    public static function denying(): self
    {
        return new self(AuthorisationResult::denied());
    }

    public static function notApplicable(): self
    {
        return new self(AuthorisationResult::notApplicable());
    }

    public function authorise(AuthorisationContext $context): AuthorisationResult
    {
        $this->contexts[] = $context;

        return $this->result instanceof Closure ? ($this->result)($context) : $this->result;
    }
}
