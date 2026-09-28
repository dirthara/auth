---
id: results
title: Authorisation results
sidebar_position: 4
description: What an authorisation returns, which policies decided it, and why a result was denied.
---

Every authorisation returns an immutable `AuthorisationResult`. A policy creates one with a named factory, and
`Authoriser` returns one after asking every policy.

```php
use Dirthara\Authorisation\AuthorisationResult;

AuthorisationResult::allowed();
AuthorisationResult::denied();
AuthorisationResult::notApplicable();
```

A result has five public properties:

| Property    | Type                   | Meaning                                                                        |
|-------------|------------------------|--------------------------------------------------------------------------------|
| `status`    | `AuthorisationStatus`  | `Allowed`, `Denied`, or `NotApplicable`.                                       |
| `policy`    | `?Policy`              | The policy whose decision alone determined the result, or `null`.              |
| `denial`    | `?AuthorisationDenial` | The denial of that policy, when it denied and gave a reason, or `null`.        |
| `consulted` | `list<Policy>`         | Every policy `Authoriser` asked, in order, or an empty list.                   |
| `decisions` | `list<PolicyDecision>` | The decision of every policy that applied, in order, or an empty list.         |

```php
$result->status;
$result->policy;
$result->denial;
$result->consulted;
$result->decisions;
```

`isAllowed()`, `isDenied()`, and `isNotApplicable()` check the status. A denied authorisation is a normal result, not an
exception.

The three provenance properties answer different questions:

| Property    | Answers                                                                  |
|-------------|--------------------------------------------------------------------------|
| `consulted` | Which policies were asked?                                               |
| `decisions` | Which policies applied, and what did each of them decide?                |
| `policy`    | Which one policy's decision was enough on its own to reach this result?  |

## Policy decisions

A `PolicyDecision` records what one policy that applied decided. `Authoriser` creates one for every policy that did not
return `NotApplicable`, in the order it asked them:

| Property | Type                   | Meaning                                                      |
|----------|------------------------|--------------------------------------------------------------|
| `policy` | `Policy`               | The exact policy instance that decided.                      |
| `status` | `AuthorisationStatus`  | `Allowed` or `Denied`, never `NotApplicable`.                |
| `denial` | `?AuthorisationDenial` | The denial that policy returned, or `null`.                  |

`isAllowed()` and `isDenied()` check the status. A decision is created with `PolicyDecision::allowed($policy)` or
`PolicyDecision::denied($policy, $denial)`, so it cannot be `NotApplicable` or carry a denial when it allowed.

```php
use Dirthara\Authorisation\AuthorisationContext;

$result = $authoriser->authorise(new AuthorisationContext($user, 'publish', $article));

foreach ($result->decisions as $decision) {
    $decision->policy;             // $ownershipPolicy, then $subscriptionPolicy
    $decision->denial?->message;   // each policy's own reason
}
```

Every denying policy keeps its own denial here, including when several policies denied together. The package never
merges denials or picks one as the explanation for a collective result.

## The deciding policy

`policy` names the policy whose decision alone was enough to determine the result under the authoriser's
[decision strategy](policies.md#decision-strategies). Some results are collective instead: under `AtLeastOne` a denial
needs every policy that applied to deny, and under `All` an allow needs every policy that applied to allow. No single
policy decided those, so `policy` is `null` and `decisions` explains the result.

| `DecisionStrategy` | `policy` of an `Allowed` result | `policy` of a `Denied` result |
|--------------------|---------------------------------|-------------------------------|
| `OnlyOne`          | The policy that applied.        | The policy that applied.      |
| `AtLeastOne`       | The first policy that allowed.  | `null`, a collective denial.  |
| `All`              | `null`, a collective allow.     | The first policy that denied. |

A `NotApplicable` result never has a policy. The rule holds even when only one policy applied: a single denial under
`AtLeastOne` is still collective, because it denies only as every policy that applied, so read its reason from
`decisions`.

The result carries the exact policy instance, so two instances of the same policy class remain distinguishable:

```php
use Dirthara\Authorisation\Authoriser;
use Dirthara\Authorisation\AuthorisationContext;

$authoriser = new Authoriser([$articlePolicy, $commentPolicy]);

$result = $authoriser->authorise(new AuthorisationContext($user, 'edit', $article));

$result->status;   // AuthorisationStatus::Allowed
$result->policy;   // $articlePolicy
```

`denial` follows `policy`. It is the denial of the deciding policy when that policy denied, and `null` for an allow, a
collective denial, or a denial without a reason. To show why a collective denial happened, read the denials from
`decisions`:

```php
if ($result->isDenied() && $result->policy === null) {
    foreach ($result->decisions as $decision) {
        $decision->denial?->message;
    }
}
```

`Authoriser` implements `Dirthara\Authorisation\Contract\Authoriser`. Code that only needs a decision should depend on
the contract, so it does not rely on how the decision is reached.

## Building results yourself

A policy does not attach anything to its own result. It returns `AuthorisationResult::allowed()` or another factory as
usual, and a result returned directly by a policy has a `null` policy and no consulted policies or decisions.
`Authoriser` replaces all three on the result it returns.

Three methods return a new result and leave the original unchanged. Each keeps every property it does not set:

| Method                              | Sets                                                                         |
|-------------------------------------|------------------------------------------------------------------------------|
| `withPolicy(Policy $policy)`        | `policy`. Throws `NotApplicableResultException` on a `NotApplicable` result. |
| `withConsulted(Policy ...)`         | `consulted`, for a result of any status.                                     |
| `withDecisions(PolicyDecision ...)` | `decisions`, for a result of any status.                                     |

```php
$result = AuthorisationResult::denied($denial)
    ->withPolicy($articlePolicy)
    ->withDecisions(PolicyDecision::denied($articlePolicy, $denial))
    ->withConsulted($articlePolicy, $commentPolicy);
```

:::caution
With the default `OnlyOne` strategy, `Authoriser` throws `AmbiguousPolicyException` instead of returning a result when
more than one policy returns anything other than `NotApplicable`, even when those policies agree.
:::

## Consulted policies

`consulted` records every policy `Authoriser` asked for a result, in the order it asked them, including the policies
that returned `NotApplicable`. It answers the question a `NotApplicable` result leaves open: which policies were there,
none of which applied?

```php
$result = $authoriser->authorise(new AuthorisationContext($user, 'publish', $article));

$result->isNotApplicable();   // true
$result->consulted;           // [$articlePolicy, $commentPolicy]
$result->decisions;           // []
```

An empty `consulted` list on a result from `Authoriser` means it had no policies to ask.

## Denials

A policy may explain a denial with an `AuthorisationDenial`, or deny without giving a reason:

```php
use Dirthara\Authorisation\AuthorisationDenial;
use Dirthara\Authorisation\AuthorisationResult;

return AuthorisationResult::denied();

return AuthorisationResult::denied(
    new AuthorisationDenial(
        messageKey: 'You can only edit posts you own.',
    ),
);

return AuthorisationResult::denied(
    new AuthorisationDenial(
        messageKey: '{actor} cannot modify this resource.',
        parameters: ['actor' => $user->name],
    ),
);
```

Only a denied result can carry a denial. `denial` is always `null` for `Allowed` and `NotApplicable`, and for a denied
result whose policy gave no reason. The denial belongs to that one decision, so a policy can deny for different reasons
in different situations. The denial of every policy that denied stays on its `PolicyDecision`; the result's own
`denial` is set only when one policy decided the result.

Every `AuthorisationDenial` carries:

| Property     | Type                   | Meaning                                                                                     |
|--------------|------------------------|---------------------------------------------------------------------------------------------|
| `messageKey` | `string`               | The message with its placeholders, such as `{actor} cannot modify this resource.`           |
| `parameters` | `array<string, mixed>` | The values for the placeholders, such as `['actor' => 'Ada']`.                              |
| `message`    | `string`               | The message key with every placeholder filled in, such as `Ada cannot modify this resource.` |

`message` is worked out from the other properties each time you read it.

:::caution
`message` is an explanation supplied by the policy, which callers may choose to show. It is not guaranteed to be safe to
show to every end user: it can name the actor or describe the resource. The package does not decide how a denial is
presented. An application can show the message, translate it, log it and show something generic instead, or ignore it.
:::

## Messages and translation

Denials follow the same approach as validation errors in
[Dirthara Validation](https://dirthara.github.io/docs/): the readable English message is the translation key.

1. A policy writes its message in readable English, such as `You can only edit posts you own.`
2. That message is the key a translation catalogue uses; there are no separate keys such as `auth.denied.not_owner`.
3. Without any translation, `message` is already a meaningful message.
4. A translation layer can look up `messageKey` and fill in `parameters` itself, in place of `message`.

Every placeholder `{name}` becomes the parameter of the same name, rendered the same way as in Dirthara Validation:

| Parameter                   | Rendered as                               |
|-----------------------------|-------------------------------------------|
| A string, integer, or float | Its value.                                |
| `true` or `false`           | `true` or `false`.                        |
| `null`                      | `null`.                                   |
| A backed enum case          | Its value.                                |
| Any other enum case         | Its name.                                 |
| A `Stringable`              | Its string value.                         |
| An array                    | Its rendered values, joined with `, `.    |
| Any other object            | Its class name.                           |

A placeholder without a parameter stays as it is, and a parameter without a placeholder is ignored. Placeholders are
filled in once, so a parameter that contains a placeholder is not filled in again. Unlike validation errors, a denial has
no `{input}` placeholder of its own.

```php
$denial->messageKey;   // '{actor} cannot modify this resource.'
$denial->parameters;   // ['actor' => 'Ada']
$denial->message;      // 'Ada cannot modify this resource.'
```

The package does not translate and has no translation dependency. To show a denial in another language, look the message
key up in your own catalogue and fill in the placeholders yourself:

```php
$catalogue = [
    '{actor} cannot modify this resource.' => '{actor} kan deze bron niet wijzigen.',
];

$translated = strtr($catalogue[$denial->messageKey] ?? $denial->messageKey, [
    '{actor}' => $denial->parameters['actor'],
]);
```

:::caution
The message key is the translation key. Changing the wording of a policy's message changes the key, so update your
catalogues with it.
:::
