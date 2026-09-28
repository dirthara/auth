<?php

declare(strict_types=1);

namespace Dirthara\Authorisation\Tests\Fixtures;

use Dirthara\Authorisation\Contract\Policy;
use Dirthara\Authorisation\AuthorisationResult;
use Dirthara\Authorisation\AuthorisationContext;

final class FixedPolicy implements Policy
{
    /**
     * @var list<AuthorisationContext>
     */
    public private(set) array $contexts = [];

    public function __construct(
        private readonly AuthorisationResult $result,
    ) {}

    public function authorise(AuthorisationContext $context): AuthorisationResult
    {
        $this->contexts[] = $context;

        return $this->result;
    }
}
