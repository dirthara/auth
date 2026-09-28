---
id: results
title: Authorisation results
sidebar_position: 3
description: What an authorisation returns, and how it records the policy that made the decision.
---

Every authorisation returns an immutable `AuthorisationResult`. A policy creates one with a named factory, and
`Authoriser` returns one after asking every policy.

```php
use Dirthara\Auth\AuthorisationResult;

AuthorisationResult::allowed();
AuthorisationResult::denied();
AuthorisationResult::notApplicable();
```

A result has two public properties:

| Property  | Type                  | Meaning                                                                     |
|-----------|-----------------------|-----------------------------------------------------------------------------|
| `status`  | `AuthorisationStatus` | `Allowed`, `Denied`, or `NotApplicable`.                                    |
| `policy`  | `?Policy`             | The policy `Authoriser` resolved as responsible for the decision, or `null`. |

`isAllowed()`, `isDenied()`, and `isNotApplicable()` check the status. A denied authorisation is a normal result, not an
exception.

## Policy provenance

`policy` records which policy owns a decision. A `null` policy means no provenance has been attached to the result.

`Authoriser` attaches the provenance. When exactly one policy applies, the result it returns carries that exact policy
instance, so two instances of the same policy class remain distinguishable:

```php
use Dirthara\Auth\Authoriser;
use Dirthara\Auth\AuthorisationContext;

$authoriser = new Authoriser([$articlePolicy, $commentPolicy]);

$result = $authoriser->authorise(new AuthorisationContext($user, 'edit', $article));

$result->status;   // AuthorisationStatus::Allowed
$result->policy;   // $articlePolicy
```

For a result returned by `Authoriser`:

| Status          | `policy`                            |
|-----------------|-------------------------------------|
| `Allowed`       | The policy that allowed.            |
| `Denied`        | The policy that denied.             |
| `NotApplicable` | `null`, because no policy applied.  |

A policy does not attach itself. It returns `AuthorisationResult::allowed()` or another factory as usual, and a result
returned directly by a policy normally has a `null` policy:

```php
$articlePolicy->authorise($context)->policy;   // null
```

To attach a policy yourself, `withPolicy()` returns a new result with the same status and the given policy, and leaves
the original unchanged:

```php
$result = AuthorisationResult::denied()->withPolicy($articlePolicy);
```

:::caution
`Authoriser` asks every policy, even after one has decided. When more than one policy returns anything other than
`NotApplicable`, it throws `AmbiguousPolicyException` instead of returning a result, even when those policies agree.
:::
