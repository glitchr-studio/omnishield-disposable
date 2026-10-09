<?php

namespace Omnishield\Disposable\Tests;

use Omnishield\Disposable\DisposableGateway;
use Omnishield\Disposable\DisposableGatewayFactory;
use Omnishield\Disposable\DisposableList;
use Omnishield\Exception\InvalidConfigException;
use Omnishield\Exception\ProviderException;
use Omnishield\Model\Identity;
use Omnishield\Model\Reputation;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * On the list shipped in data/domains.txt (disposable-email-domains at
 * 1aac72a, 2026-10-05): what it says of a few real domains.
 */
final class DisposableGatewayTest extends TestCase
{
    private function gateway(array $options = []): DisposableGateway
    {
        $gateway = (new DisposableGatewayFactory())->create($options);
        self::assertInstanceOf(DisposableGateway::class, $gateway);

        return $gateway;
    }

    public function testTheShippedListKnowsTheUsualDisposableDomainsAndTheirSubdomains(): void
    {
        $gateway = $this->gateway();

        $reputation = $gateway->lookup(new Identity('192.0.2.10', 'Someone@Mailinator.com', 'Camille'));
        self::assertTrue($reputation->known);
        self::assertSame([0, 100.0, null, [Reputation::EMAIL, Reputation::DISPOSABLE]], [$reputation->frequency, $reputation->confidence, $reputation->lastSeen, $reputation->reasons]);
        self::assertSame(['domain' => 'mailinator.com', 'matched' => 'mailinator.com', 'source' => 'list'], $reputation->data);

        self::assertSame('yopmail.com', $gateway->lookup(new Identity(email: 'a@eu.yopmail.com.'))->data['matched'], 'a subdomain, a trailing dot');
        self::assertTrue($gateway->isDisposable('guerrillamail.com'));
        foreach (['gmail.com', 'outlook.fr', 'example.org', 'laposte.net', 'proton.me'] as $domain) {
            self::assertFalse($gateway->isDisposable('a@'.$domain), $domain);
        }
        self::assertFalse($gateway->lookup(new Identity('192.0.2.10', null, 'Camille'))->known, 'no e-mail, nothing to say');
        self::assertGreaterThan(9000, (new DisposableList())->count());
    }

    public function testTheSitesAllowAndDenyListsComeFirst(): void
    {
        $gateway = $this->gateway(['allow' => 'mailinator.com', 'deny' => ['throwaway.example', 'yopmail.com']]);

        $allowed = $gateway->lookup(new Identity(email: 'a@team.mailinator.com'));
        self::assertFalse($allowed->known);
        self::assertSame(['domain' => 'team.mailinator.com', 'allowed' => 'mailinator.com'], $allowed->data);
        self::assertSame('deny', $gateway->lookup(new Identity(email: 'a@mx.throwaway.example'))->data['source']);
        self::assertSame('deny', $gateway->lookup(new Identity(email: 'a@yopmail.com'))->data['source']);
        self::assertSame(['reputation'], $gateway->capabilities()->questions());
        self::assertFalse($gateway->capabilities()->thirdParty);
    }

    public function testAListOfTheApplicationsAndARefreshWrittenInOneMove(): void
    {
        $path = sys_get_temp_dir().'/omnishield-disposable-'.bin2hex(random_bytes(4)).'.txt';
        $lines = implode("\n", array_map(static fn (int $i) => 'spam'.$i.'.example', range(1, 1500)))."\n# a comment\n\nSPAM0.EXAMPLE\n";
        try {
            $count = DisposableList::refresh($path, new MockHttpClient(new MockResponse($lines)));
            self::assertSame(1501, $count);
            self::assertStringStartsWith("spam0.example\nspam1.example\n", (string) file_get_contents($path), 'lower case, sorted, comments left out');

            $gateway = $this->gateway(['list' => $path]);
            self::assertTrue($gateway->isDisposable('a@mx.spam42.example'));
            self::assertFalse($gateway->isDisposable('a@mailinator.com'), 'that list, not the shipped one');

            try {
                DisposableList::refresh($path, new MockHttpClient(new MockResponse("only.example\n")));
                self::fail();
            } catch (ProviderException $e) {
                self::assertStringContainsString('holds 1 domains: not the list, nothing written', $e->getMessage());
                self::assertSame(1501, (new DisposableList($path))->count());
            }
        } finally {
            @unlink($path);
        }

        $this->expectException(InvalidConfigException::class);
        $this->gateway(['list' => '/nowhere/domains.txt']);
    }
}
