---
id: testing
title: Testing
sidebar_position: 5
description: Replacing the authoriser with a fake in the tests of code that depends on it.
---

Code that types against `Dirthara\Authorisation\Contract\Authoriser` can be tested without real policies.
`Dirthara\Authorisation\Testing\FakeAuthoriser` implements the contract, returns the result you give it, and records
every context it was asked about.

```php
use Dirthara\Authorisation\Testing\FakeAuthoriser;

$authoriser = FakeAuthoriser::denying();

new EditArticle($authoriser)->handle($user, $article);

$authoriser->contexts;   // [AuthorisationContext($user, 'edit', $article)]
```

| Constructor or factory                    | Every `authorise()` returns                          |
|-------------------------------------------|------------------------------------------------------|
| `FakeAuthoriser::allowing()`              | `AuthorisationResult::allowed()`                     |
| `FakeAuthoriser::denying()`               | `AuthorisationResult::denied()`                      |
| `FakeAuthoriser::notApplicable()`         | `AuthorisationResult::notApplicable()`               |
| `new FakeAuthoriser($result)`             | `$result`, such as a denial with a message.          |
| `new FakeAuthoriser($closure)`            | What the closure returns for the context.            |

A closure decides per context, for a test that asks more than one question:

```php
use Dirthara\Authorisation\AuthorisationResult;
use Dirthara\Authorisation\AuthorisationContext;

$authoriser = new FakeAuthoriser(
    static fn(AuthorisationContext $context): AuthorisationResult => $context->ability === 'view'
        ? AuthorisationResult::allowed()
        : AuthorisationResult::denied(),
);
```

`contexts` lists every context in the order it was asked, starting empty.

:::note
The fake returns its result exactly as given. It attaches no policy, consulted policies, or decisions, so those stay as
the result you give it has them, which for a result from a factory is `null` and empty lists. Build them with
`withPolicy()`, `withDecisions()`, and `withConsulted()` when the code under test reads them. Unlike `Authoriser`, it
never throws `AmbiguousPolicyException`.
:::

```php
use Dirthara\Authorisation\PolicyDecision;
use Dirthara\Authorisation\AuthorisationDenial;

$authoriser = new FakeAuthoriser(
    AuthorisationResult::denied()->withDecisions(
        PolicyDecision::denied($ownershipPolicy, new AuthorisationDenial('You do not own this resource.')),
        PolicyDecision::denied($subscriptionPolicy, new AuthorisationDenial('Your plan does not permit this.')),
    ),
);
```

The fake works with `Enforcer` too, so the code under test can enforce decisions as it does in production:

```php
use Dirthara\Authorisation\Enforcer;

$enforcer = new Enforcer(FakeAuthoriser::allowing());
```
