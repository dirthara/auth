<?php

declare(strict_types=1);

namespace Dirthara\Authorisation;

use Dirthara\Authorisation\Contract\Policy;
use Dirthara\Authorisation\Exception\NotApplicableResultException;

final readonly class AuthorisationResult
{
    private function __construct(
        public AuthorisationStatus $status,
        public ?Policy $policy = null,
        public ?AuthorisationDenial $denial = null,
    ) {}

    public static function allowed(): self
    {
        return new self(AuthorisationStatus::Allowed);
    }

    public static function denied(?AuthorisationDenial $denial = null): self
    {
        return new self(AuthorisationStatus::Denied, denial: $denial);
    }

    public static function notApplicable(): self
    {
        return new self(AuthorisationStatus::NotApplicable);
    }

    public function withPolicy(Policy $policy): self
    {
        if ($this->isNotApplicable()) {
            throw NotApplicableResultException::cannotHavePolicy($policy);
        }

        return new self(status: $this->status, policy: $policy, denial: $this->denial);
    }

    public function isAllowed(): bool
    {
        return $this->status === AuthorisationStatus::Allowed;
    }

    public function isDenied(): bool
    {
        return $this->status === AuthorisationStatus::Denied;
    }

    public function isNotApplicable(): bool
    {
        return $this->status === AuthorisationStatus::NotApplicable;
    }
}
