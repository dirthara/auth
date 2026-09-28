---
id: intro
title: Dirthara Authorisation
sidebar_position: 1
description: What the Dirthara Authorisation package does and where to start.
---

Standalone policy-based authorisation for PHP and the Dirthara framework. An application writes policies, each deciding
whether an actor may perform an ability on a subject, and an authoriser asks every policy and combines their answers
into a single result: allowed, denied, or not applicable. The result records which policies were asked, what each
policy that applied decided, which policy decided on its own when one did, and why a denial happened.

The package has no runtime dependencies beyond PHP and does not know about requests, sessions, users, roles, or
permissions. It works in any PHP application, with or without the rest of the framework. It decides; the application
chooses what to do with the decision.

```php
use Dirthara\Authorisation\Authoriser;
use Dirthara\Authorisation\AuthorisationContext;

$authoriser = new Authoriser([new ArticlePolicy(), new CommentPolicy()]);

$result = $authoriser->authorise(new AuthorisationContext($user, 'edit', $article));

if (!$result->isAllowed()) {
    // refuse the edit
}
```

A denied authorisation is a normal result. Only `Enforcer`, which a caller opts into, turns a refusal into an
exception.

:::note
The package is pre-1.0. Each minor version is its own release line, and a new minor may change the API.
:::

- [Installation](installation.md) covers requirements and installing the package.
- [Policies and the authoriser](policies.md) describes how to write a policy and how the authoriser reaches a decision.
- [Authorisation results](results.md) describes what an authorisation returns: the deciding policy, the decision of
  every policy that applied, the policies asked, and denials.
- [Testing](testing.md) covers replacing the authoriser with a fake in your tests.
- [Exceptions](exceptions.md) lists what the package throws and the context each exception carries.
