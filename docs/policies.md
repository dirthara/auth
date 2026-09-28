---
id: policies
title: Policies and the authoriser
sidebar_position: 3
description: How to write a policy, what an authorisation context holds, and how the authoriser reaches a decision.
---

A policy answers one question: may this actor perform this ability on this subject? The authoriser asks every policy an
application registers and combines the answers of the policies that apply into one result, following its decision
strategy.

## Authorisation context

Every question is an `AuthorisationContext`:

| Property  | Type               | Default | Meaning                                                                        |
|-----------|--------------------|---------|--------------------------------------------------------------------------------|
| `actor`   | `object`           | —       | Who wants to act, such as the signed-in user.                                  |
| `ability` | `string\|UnitEnum` | —       | What they want to do, such as `'edit'` or an enum case like `Ability::Edit`.   |
| `subject` | `mixed`            | `null`  | What they want to do it to, or `null` for an ability without one, like create. |

```php
use Dirthara\Authorisation\AuthorisationContext;

new AuthorisationContext($user, 'edit', $article);
new AuthorisationContext($user, Ability::Create);
```

The package compares nothing in the context itself. What an ability or subject means is up to the policies.

## Writing a policy

A policy implements `Dirthara\Authorisation\Contract\Policy` and returns an `AuthorisationResult` for every context:

```php
use Dirthara\Authorisation\Contract\Policy;
use Dirthara\Authorisation\AuthorisationDenial;
use Dirthara\Authorisation\AuthorisationResult;
use Dirthara\Authorisation\AuthorisationContext;

final class ArticlePolicy implements Policy
{
    public function authorise(AuthorisationContext $context): AuthorisationResult
    {
        if (!$context->subject instanceof Article || $context->ability !== 'edit') {
            return AuthorisationResult::notApplicable();
        }

        if ($context->subject->authorId === $context->actor->id) {
            return AuthorisationResult::allowed();
        }

        return AuthorisationResult::denied(new AuthorisationDenial('You can only edit articles you own.'));
    }
}
```

Return `notApplicable()` for every context the policy is not responsible for. `denied()` means the policy is
responsible and says no. Using `denied()` for a context the policy does not own makes it collide with the policy that
does own it under `OnlyOne`, and overrules it under `All`.

### Keep policies free of side effects

Policies should behave as pure decision functions and should not perform side effects. A policy reads the context and
returns a result; it does not write to a database, send a message, log an audit entry, or change the actor or subject.

A policy cannot know how often, or why, it is asked:

- One authorisation asks several policies, and every policy is asked even when it does not apply.
- `AtLeastOne` and `All` keep asking the remaining policies after the outcome is already certain.
- Callers may check the same authorisation more than once, for example in a controller and again in a service.
- An application may ask the same question to decide what to show, such as whether to render an edit button, as well
  as to decide whether to act.

A side effect in a policy would run a different number of times than the action it guards, including for actions that
never happen. Record what was decided after the authorisation instead, from the result.

The package does not enforce this; it is part of the contract a policy is expected to keep.

## The authoriser

`Authoriser` takes the policies to ask, as an array or any other iterable, and reads them once when it is constructed:

```php
use Dirthara\Authorisation\Authoriser;

$authoriser = new Authoriser([new ArticlePolicy(), new CommentPolicy()]);

$result = $authoriser->authorise(new AuthorisationContext($user, 'edit', $article));
```

Keys of the iterable are ignored, so a generator that repeats a key still passes every policy. Every value must implement
`Dirthara\Authorisation\Contract\Policy`; the constructor checks each one as it reads it and throws
`InvalidPolicyException` for the first that does not, rather than failing later with a PHP error.

## Decision strategies

The second argument chooses how the answers of the policies that apply are combined. It defaults to `OnlyOne`:

```php
use Dirthara\Authorisation\DecisionStrategy;

new Authoriser($policies);
new Authoriser($policies, DecisionStrategy::AtLeastOne);
new Authoriser($policies, DecisionStrategy::All);
```

| `DecisionStrategy` | Allowed when                            | Denied when                         | More than one applies               |
|--------------------|-----------------------------------------|-------------------------------------|-------------------------------------|
| `OnlyOne`          | The one policy that applies allows.     | The one policy that applies denies. | Throws `AmbiguousPolicyException`.  |
| `AtLeastOne`       | Any policy that applies allows.         | Every policy that applies denies.   | Allowed if any of them allows.      |
| `All`              | Every policy that applies allows.       | Any policy that applies denies.     | Denied if any of them denies.       |

With every strategy, policies that return `NotApplicable` are ignored, and the result is `NotApplicable` when no policy
applies. `OnlyOne` is the default because it keeps one policy responsible for every question; choose `AtLeastOne` or
`All` when several policies are meant to answer the same question, such as an ownership rule alongside a role rule.

The authoriser always asks every policy, even when the outcome is already certain, so `consulted` and `decisions` are
complete and the result does not depend on where evaluation stopped. `OnlyOne` is the exception: it stops at the second
policy that applies and throws.

Every result records the decision of each policy that applied in `decisions`, and every policy asked in `consulted`.
`policy` names a single policy only when that policy's decision alone determined the result:

| `DecisionStrategy` | `policy` of an `Allowed` result | `policy` of a `Denied` result |
|--------------------|---------------------------------|-------------------------------|
| `OnlyOne`          | The policy that applied.        | The policy that applied.      |
| `AtLeastOne`       | The first policy that allowed.  | `null`, a collective denial.  |
| `All`              | `null`, a collective allow.     | The first policy that denied. |

```php
$authoriser = new Authoriser([$ownershipPolicy, $subscriptionPolicy], DecisionStrategy::All);

$result = $authoriser->authorise(new AuthorisationContext($user, 'publish', $article));

$result->isDenied();    // true, because $subscriptionPolicy denied
$result->policy;        // $subscriptionPolicy
$result->decisions;     // [allowed by $ownershipPolicy, denied by $subscriptionPolicy]
```

Order never changes the status of a result, only which of several agreeing policies `policy` names. See
[the deciding policy](results.md#the-deciding-policy) for how `policy`, `denial`, and `decisions` relate.

## Enforcing a decision

`Enforcer` wraps any `Contract\Authoriser` for code that must stop when the actor may not act:

```php
use Dirthara\Authorisation\Enforcer;

$enforcer = new Enforcer($authoriser);

$result = $enforcer->ensure(new AuthorisationContext($user, 'edit', $article));

if ($enforcer->allows(new AuthorisationContext($user, 'delete', $article))) {
    // show the delete button
}
```

| Method     | Returns                             | When the result is not `Allowed`         |
|------------|-------------------------------------|------------------------------------------|
| `ensure()` | The `Allowed` result and its policy. | Throws `AuthorisationDeniedException`.   |
| `allows()` | `true` for an `Allowed` result.      | Returns `false`.                         |

Both treat `NotApplicable` as a denial. The exception carries the full result, so a caller can still read the deciding
policy and the denial:

```php
use Dirthara\Authorisation\Exception\AuthorisationDeniedException;

try {
    $enforcer->ensure($context);
} catch (AuthorisationDeniedException $exception) {
    $exception->result->denial?->message;
}
```

## Depending on the contract

`Authoriser` implements `Dirthara\Authorisation\Contract\Authoriser`, which declares only `authorise()`. Type against
the contract in code that needs a decision, so an application can replace the policy-list authoriser without changing
that code:

```php
use Dirthara\Authorisation\Contract\Authoriser;

final readonly class EditArticle
{
    public function __construct(private Authoriser $authoriser) {}
}
```
