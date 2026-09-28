---
id: intro
title: Dirthara Authorisation
sidebar_position: 1
description: What the Dirthara Authorisation package does and where to start.
---

Authorisation for the Dirthara framework. An application writes policies, each deciding whether an actor may perform an
ability on a subject, and an authoriser asks every policy and returns a single result: allowed, denied, or not
applicable. The result records which policy decided and, for a denial, why.

The package has no runtime dependencies and does not know about requests, sessions, or users. It decides; the
application chooses what to do with the decision.

:::note
The package is pre-1.0. Each minor version is its own release line, and a new minor may change the API.
:::

- [Installation](installation.md) covers requirements and installing the package.
- [Policies and the authoriser](policies.md) describes how to write a policy and how the authoriser reaches a decision.
- [Authorisation results](results.md) describes what an authorisation returns, the deciding policy, and denials.
- [Testing](testing.md) covers replacing the authoriser with a fake in your tests.
- [Exceptions](exceptions.md) lists what the package throws and the context each exception carries.
