<?php

namespace Omnishield\Disposable;

use Omnishield\Config;
use Omnishield\Exception\InvalidConfigException;
use Omnishield\GatewayFactory;
use Omnishield\GatewayInterface;

/**
 * Disposable e-mail domains: a list read from a file, no call.
 *
 *   options:
 *     list: ~                              # a file of the application's (a refreshed copy); the one shipped in the package otherwise
 *     allow: [example-school.org]          # never disposable for this site, whatever the list says
 *     deny: [throwaway.example]            # disposable for this site, whatever the list says
 *
 * allow and deny take a list, or one comma-separated string (an
 * environment variable); a domain takes in its subdomains.
 */
final class DisposableGatewayFactory extends GatewayFactory
{
    protected function populate(Config $c): void
    {
        $c->defaults([
            'omnishield.factory_name' => 'disposable',
            'omnishield.factory_title' => 'Disposable e-mail domains',
            'omnishield.required_options' => [],
            'list' => null,
            'allow' => [],
            'deny' => [],
        ]);
    }

    protected function build(Config $c): GatewayInterface
    {
        $path = $c->string('list');
        if (null !== $path && !is_readable($path)) {
            throw new InvalidConfigException(\sprintf('The "disposable" gateway cannot read its list %s.', $path));
        }

        return new DisposableGateway(null !== $path ? new DisposableList($path) : new DisposableList(), $c->list('allow'), $c->list('deny'));
    }
}
