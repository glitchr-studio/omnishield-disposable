---
title: omnishield/disposable
order: 1
---

# omnishield/disposable

## Installation

```sh
composer require omnishield/disposable
```

PHP 8.2 or later and `glitchr/omnishield`: nothing else. No HTTP client while a request runs; the
refresh uses the application's client when given one, PHP's own streams otherwise.

## The list

`data/domains.txt` is `disposable_email_blocklist.conf` of
[disposable-email-domains/disposable-email-domains](https://github.com/disposable-email-domains/disposable-email-domains),
unchanged, at commit `1aac72a07cd89792016dd5d2056bc418d85b8f14` (2026-10-05): 9,205 domains.
Its authors dedicated it to the public domain, [CC0 1.0 Universal](https://creativecommons.org/publicdomain/zero/1.0/):
"You can copy, modify, distribute and use the work, even for commercial purposes, all without
asking permission." `data/SOURCE.md` says so beside it. The project's README is candid about what
a list can be: "We cannot guarantee all of these can still be considered disposable but we do
basic checking so chances are they were disposable at one point in time."

## The matching

The list holds second-level domains - third-level ones under a public suffix - so a domain is on
it when it, **or a domain above it**, is: `eu.mailinator.com` matches `mailinator.com`. The
e-mail's domain is lower-cased, a trailing dot dropped; single labels are never matched.

The site's lists come first: an **allowed** domain is never disposable, whatever the list says;
a **denied** one always is.

| `Reputation` | |
|---|---|
| `known` | on the deny list or the list, and not on the allow list |
| `confidence` | 100 when known: a domain is on a list, not reported now and then |
| `frequency`, `lastSeen` | 0, null |
| `reasons` | `[email, disposable]` |
| `data` | `domain`; `matched` (the part on the list) and `source` (`deny` or `list`); or `allowed` |

An identity without an e-mail is unknown.

## Options

| Option | Default | |
|---|---|---|
| `list` | the shipped `data/domains.txt` | a file of the application's: a refreshed copy |
| `allow` | `[]` | never disposable for this site: a list, or one comma-separated string |
| `deny` | `[]` | always disposable for this site |

## Refreshing

```sh
vendor/bin/omnishield-disposable refresh [<file>]
vendor/bin/omnishield-disposable check <e-mail or domain>... [--list <file>]     # exit 1 when one is disposable
```

```php
DisposableList::refresh('/srv/app/var/disposable_domains.txt', $httpClient);   // the number of domains written
```

The latest list is downloaded from the project's `main` branch, lower-cased, sorted, comments
and blank lines dropped, and written **in one move** (a temporary file, renamed): a request that
reads it meanwhile reads the old list or the new one, never half of either. A download of fewer
than 1,000 domains is taken for broken and nothing is written. Point the gateway's `list` option
at the file; a long-running worker reads the new list when it builds the gateway again.

## Verified, and not

| | |
|---|---|
| The shipped list on real domains | **done on 2026-10-07**: mailinator.com, eu.yopmail.com, guerrillamail.com, 10minutemail.com disposable; gmail.com, outlook.fr, example.org, laposte.net, proton.me not |
| The refresh | **done on 2026-10-07**: `vendor/bin/omnishield-disposable refresh` downloaded 9,205 domains with PHP's own streams, `check` read them |
| The allow and deny lists, a list of the application's | by the tests |
