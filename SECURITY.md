# Security Policy

## Supported versions

| Version | Status |
| --- | --- |
| 0.1.x | Supported |
| Older | Unsupported |

While the package is pre-1.0, only the latest release line receives fixes.

## Reporting a vulnerability

Report vulnerabilities privately using GitHub's
[Report a vulnerability](https://github.com/dirthara/authorisation/security/advisories/new)
form. Do not disclose vulnerabilities in public issues or pull requests.

Include the affected version or commit, PHP version, a minimal reproduction,
and the impact and conditions needed to trigger the issue. Maintainers will
acknowledge and assess the report. Confirmed fixes are published with an
advisory crediting the reporter unless they prefer otherwise.

## Scope

The package decides whether an actor may perform an ability on a subject by asking the policies an application
registers. In scope are flaws in that decision, such as:

- a result that does not follow the authoriser's decision strategy, such as an allow under `All` when a policy denied;
- more than one applicable policy producing a result under `OnlyOne` instead of an `AmbiguousPolicyException`;
- a result carrying the wrong deciding policy or the wrong denial;
- an exception message or context exposing the actor or subject beyond their types, or letting a value forge a log
  line.

Out of scope:

- The decisions an application's own policies make. A policy that allows too much is application code.
- The decision strategy an application chooses. Under `AtLeastOne`, one policy that allows is enough.
- What an application does with a `NotApplicable` result. The package reports that no policy applied; treating that as
  a denial is the application's responsibility.
- How an application presents a denial. A denial message is written by the policy and can name the actor or describe
  the subject, so showing it to an end user is the application's choice.

Bugs in PHP or third-party dependencies should also be reported upstream.
Application code and the sensitivity of data an application chooses to store
are the application's responsibility.
