---
id: results
title: Authorisation results
sidebar_position: 4
description: What an authorisation returns, the policy that made the decision, and why a result was denied.
---

Every authorisation returns an immutable `AuthorisationResult`. A policy creates one with a named factory, and
`Authoriser` returns one after asking every policy.

```php
use Dirthara\Authorisation\AuthorisationResult;

AuthorisationResult::allowed();
AuthorisationResult::denied();
AuthorisationResult::notApplicable();
```

A result has four public properties:

| Property    | Type                   | Meaning                                                                      |
|-------------|------------------------|------------------------------------------------------------------------------|
| `status`    | `AuthorisationStatus`  | `Allowed`, `Denied`, or `NotApplicable`.                                     |
| `policy`    | `?Policy`              | The policy `Authoriser` resolved as responsible for the decision, or `null`. |
| `denial`    | `?AuthorisationDenial` | Why a denied result was denied, when the policy said so, or `null`.          |
| `consulted` | `list<Policy>`         | Every policy `Authoriser` asked, in order, or an empty list.                 |

```php
$result->status;
$result->policy;
$result->denial;
$result->consulted;
```

`isAllowed()`, `isDenied()`, and `isNotApplicable()` check the status. A denied authorisation is a normal result, not an
exception.

## Policy provenance

`policy` records which policy owns a decision. A `null` policy means no provenance has been attached to the result.

`Authoriser` attaches the provenance. When exactly one policy applies, the result it returns carries that exact policy
instance, so two instances of the same policy class remain distinguishable:

```php
use Dirthara\Authorisation\Authoriser;
use Dirthara\Authorisation\AuthorisationContext;

$authoriser = new Authoriser([$articlePolicy, $commentPolicy]);

$result = $authoriser->authorise(new AuthorisationContext($user, 'edit', $article));

$result->status;   // AuthorisationStatus::Allowed
$result->policy;   // $articlePolicy
```

`Authoriser` implements `Dirthara\Authorisation\Contract\Authoriser`. Code that only needs a decision should depend on
the contract, so it does not rely on how the decision is reached.

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

To attach a policy yourself, `withPolicy()` returns a new result with the same status and denial and the given policy,
and leaves the original unchanged:

```php
$result = AuthorisationResult::denied()->withPolicy($articlePolicy);
```

Only an `Allowed` or `Denied` result can have a deciding policy. Calling `withPolicy()` on a `NotApplicable` result
throws `NotApplicableResultException`, because no policy decided it.

:::caution
`Authoriser` asks every policy, even after one has decided. When more than one policy returns anything other than
`NotApplicable`, it throws `AmbiguousPolicyException` instead of returning a result, even when those policies agree.
:::

## Consulted policies

`consulted` records every policy `Authoriser` asked for a result, in the order it asked them. It answers the question a
`NotApplicable` result leaves open: which policies were there, none of which applied?

```php
$result = $authoriser->authorise(new AuthorisationContext($user, 'publish', $article));

$result->isNotApplicable();   // true
$result->consulted;           // [$articlePolicy, $commentPolicy]
```

An empty list on a result from `Authoriser` means it had no policies to ask. A result returned directly by a policy has
an empty list, like its `null` policy.

`withConsulted()` returns a new result with the given policies and the same status, policy, and denial, for any result
status. `withPolicy()` keeps the consulted policies of the result it is called on.

```php
$result = AuthorisationResult::notApplicable()->withConsulted($articlePolicy, $commentPolicy);
```

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
in different situations. `Authoriser` keeps the denial when it attaches the deciding policy.

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
