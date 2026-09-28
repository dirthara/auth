<?php

declare(strict_types=1);

namespace Dirthara\Authorisation;

use Dirthara\Authorisation\Contract\Policy;

final readonly class PolicyDecision
{
    private function __construct(
        public Policy $policy,
        public AuthorisationStatus $status,
        public ?AuthorisationDenial $denial = null,
    ) {}

    public static function allowed(Policy $policy): self
    {
        return new self($policy, AuthorisationStatus::Allowed);
    }

    public static function denied(Policy $policy, ?AuthorisationDenial $denial = null): self
    {
        return new self($policy, AuthorisationStatus::Denied, $denial);
    }

    public function isAllowed(): bool
    {
        return $this->status === AuthorisationStatus::Allowed;
    }

    public function isDenied(): bool
    {
        return $this->status === AuthorisationStatus::Denied;
    }
}
