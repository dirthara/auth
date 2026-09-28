---
id: exceptions
title: Exceptions
sidebar_position: 6
description: The exceptions Dirthara Authorisation throws and the context each one carries.
---

A denied authorisation is a result, not an exception. The package throws when it is used in a way that cannot produce a
trustworthy result, and when `Enforcer::ensure()` is asked to enforce a result that is not `Allowed`.

Every exception implements `Dirthara\Authorisation\Exception\AuthorisationException`, so one `catch` handles anything
from the package. Each also carries a `context` array with the details of the failure, and `addContext()` adds more
before rethrowing:

```php
use Dirthara\Authorisation\Exception\AuthorisationException;

try {
    $result = $authoriser->authorise($context);
} catch (AuthorisationException $exception) {
    $exception->context;
}
```

| Exception                      | Extends            | Thrown when                                                           |
|--------------------------------|--------------------|-----------------------------------------------------------------------|
| `AmbiguousPolicyException`     | `RuntimeException` | Under `OnlyOne`, more than one policy applies to one context.        |
| `NotApplicableResultException` | `RuntimeException` | `withPolicy()` is called on a `NotApplicable` result.                 |
| `AuthorisationDeniedException` | `RuntimeException` | `Enforcer::ensure()` gets a `Denied` or `NotApplicable` result.       |

All live in the `Dirthara\Authorisation\Exception` namespace.

## AmbiguousPolicyException

The message names the ability and the classes of the first two policies that applied. An anonymous policy class is
named by the interface or class it extends, such as `Dirthara\Authorisation\Contract\Policy@anonymous`, without the
file it was declared in. An enum ability is written as its case, such as `App\Ability::Edit`, and control characters in
a string ability are escaped so the message cannot forge a log line.

| Context key   | Type               | Meaning                                                     |
|---------------|--------------------|-------------------------------------------------------------|
| `ability`     | `string\|UnitEnum` | The ability from the context.                               |
| `actorType`   | `string`           | The type of the actor, such as its class name.              |
| `subjectType` | `string`           | The type of the subject, or the string `null` without one.  |
| `policies`    | `list<string>`     | The types of the first two policies that applied.           |

The actor and subject are recorded by type only, so the context does not copy user data into a log.

Fix the policies rather than catching this exception: two policies claim the same question, and one of them should
return `notApplicable()` for it. When several policies are meant to answer the same question, construct the authoriser
with `DecisionStrategy::AtLeastOne` or `DecisionStrategy::All` instead.

## NotApplicableResultException

A `NotApplicable` result means no policy decided, so it cannot carry a deciding policy.

| Context key | Type     | Meaning                                    |
|-------------|----------|--------------------------------------------|
| `policy`    | `string` | The type of the policy that was attached.  |

## AuthorisationDeniedException

The message names the ability, written the same way as in `AmbiguousPolicyException`, and the policy that denied, or
says that no policy applies. It never contains the denial message, because that can name the actor or describe the
subject.

The `result` property holds the result that was not allowed, with its status, deciding policy, and denial.

| Context key   | Type                  | Meaning                                                              |
|---------------|-----------------------|----------------------------------------------------------------------|
| `ability`     | `string\|UnitEnum`    | The ability from the context.                                        |
| `actorType`   | `string`              | The type of the actor, such as its class name.                       |
| `subjectType` | `string`              | The type of the subject, or the string `null` without one.           |
| `status`      | `AuthorisationStatus` | `Denied` or `NotApplicable`.                                         |
| `policy`      | `?string`             | The type of the policy that denied, or `null` when there is none.    |
| `messageKey`  | `?string`             | The denial's message key without its parameters, or `null`.          |
| `consulted`   | `list<string>`        | The types of the policies the authoriser asked, in order.            |
