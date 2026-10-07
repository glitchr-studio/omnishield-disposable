# omniguard/disposable

**Disposable e-mail domains** for [glitchr/omniguard](https://github.com/glitchr-studio/omniguard):
is this e-mail's domain a throw-away one. The free list of the
[disposable-email-domains](https://github.com/disposable-email-domains/disposable-email-domains)
project (CC0, 9,205 domains on 2026-10-05) ships in the package; the site adds its own allow and
deny lists. **Nothing is called while a request runs**: the list is a file, read once per process.

```php
use Omniguard\Disposable\DisposableGatewayFactory;
use Omniguard\Model\Identity;

$emails = (new DisposableGatewayFactory())->create(['allow' => ['example-school.org']]);

$emails->lookup(new Identity(email: 'someone@eu.mailinator.com'))->known;   // true: mailinator.com, subdomains included
$emails->isDisposable('someone@gmail.com');                                 // false
```

```yaml
omniguard:
    gateways:
        emails:
            factory: disposable
            options: { list: '%kernel.project_dir%/var/disposable_domains.txt' }   # a refreshed copy; the shipped one otherwise
```

Refresh the list - from a cron job, into a file of the application's (one inside `vendor/` is put
back by the next `composer install`):

```sh
vendor/bin/omniguard-disposable refresh var/disposable_domains.txt
vendor/bin/omniguard-disposable check someone@mailinator.com --list var/disposable_domains.txt
```

[Documentation](docs/index.md): the matching, the options, the refresh, the list's licence, what
was verified.

License: LGPL-3.0-or-later. The list itself (`data/domains.txt`): CC0 1.0, by its authors.
