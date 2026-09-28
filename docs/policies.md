---
id: policies
title: Policies and the authoriser
sidebar_position: 3
description: How to write a policy, what an authorisation context holds, and how the authoriser reaches a decision.
---

A policy answers one question: may this actor perform this ability on this subject? The authoriser asks every policy an
application registers and returns the answer of the one policy that applies.

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
does own it.

## The authoriser

`Authoriser` takes the policies to ask, as an array or any other iterable, and reads them once when it is constructed:

```php
use Dirthara\Authorisation\Authoriser;

$authoriser = new Authoriser([new ArticlePolicy(), new CommentPolicy()]);

$result = $authoriser->authorise(new AuthorisationContext($user, 'edit', $article));
```

It asks every policy, in order, and decides as follows:

| Policies that did not return `NotApplicable` | `authorise()`                                                         |
|----------------------------------------------|-----------------------------------------------------------------------|
| None                                         | Returns a `NotApplicable` result with no policy.                      |
| Exactly one                                  | Returns that policy's result, with that policy attached as `policy`.  |
| More than one                                | Throws `AmbiguousPolicyException`, even when the policies agree.      |

Order therefore never changes the outcome: a policy cannot win by being registered first. Keys of the iterable are
ignored, so a generator that repeats a key still passes every policy.

:::caution
A `NotApplicable` result means no policy is responsible for the question, not that the actor is allowed. Treat it as a
denial unless your application deliberately allows what no policy covers:

```php
if (!$authoriser->authorise($context)->isAllowed()) {
    // deny
}
```
:::

## Depending on the contract

`Authoriser` implements `Dirthara\Authorisation\Contract\Authoriser`, which declares only `authorise()`. Type against the
contract in code that needs a decision, so an application can replace the policy-list authoriser without changing that
code:

```php
use Dirthara\Authorisation\Contract\Authoriser;

final readonly class EditArticle
{
    public function __construct(private Authoriser $authoriser) {}
}
```
