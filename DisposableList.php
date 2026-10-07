<?php

namespace Omniguard\Disposable;

use Omniguard\Exception\ProviderException;
use Omniguard\Exception\UnreachableException;
use Omniguard\Http\Answer;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A list of disposable e-mail domains, one per line, read once per process:
 * the one shipped in data/domains.txt (disposable-email-domains, CC0), or a
 * file of the application's - a refreshed copy.
 *
 * A domain is on the list when it, or a domain above it, is: the list holds
 * second-level domains, so mailinator.com takes in eu.mailinator.com.
 */
final class DisposableList
{
    /** Where the list is kept up to date. */
    public const SOURCE = 'https://raw.githubusercontent.com/disposable-email-domains/disposable-email-domains/main/disposable_email_blocklist.conf';

    /** Under that, a download is taken for broken, not for the list. */
    private const MINIMUM = 1000;

    /** @var array<string, array<string, true>> the lists read, by file */
    private static array $read = [];

    public function __construct(public readonly string $path = __DIR__.'/data/domains.txt')
    {
    }

    /** The part of the domain that is on the list - itself or a domain above it -, or null. */
    public function match(string $domain): ?string
    {
        return self::find($this->domains(), $domain);
    }

    public function count(): int
    {
        return \count($this->domains());
    }

    /**
     * The part of $domain found among $domains, itself or a parent of at
     * least two labels.
     *
     * @param array<string, true> $domains
     */
    public static function find(array $domains, string $domain): ?string
    {
        $labels = explode('.', rtrim(strtolower(trim($domain)), '.'));
        for ($i = 0, $n = \count($labels); $i < $n - 1; ++$i) {
            $candidate = implode('.', \array_slice($labels, $i));
            if (isset($domains[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Domains as a set: lower case, comments and blank lines left out.
     *
     * @param iterable<string> $lines
     *
     * @return array<string, true>
     */
    public static function parse(iterable $lines): array
    {
        $domains = [];
        foreach ($lines as $line) {
            $line = strtolower(trim((string) preg_replace('~#.*$~', '', $line)));
            if ('' !== $line && str_contains($line, '.')) {
                $domains[rtrim($line, '.')] = true;
            }
        }

        return $domains;
    }

    /**
     * Downloads the list's latest state and writes it to $path in one move
     * (a temporary file renamed): a request reading the list meanwhile reads
     * the old one or the new one, never half of either.
     *
     * @param HttpClientInterface|null $http the application's client; PHP's own streams without one
     *
     * @return int the domains written
     */
    public static function refresh(string $path, ?HttpClientInterface $http = null, string $source = self::SOURCE): int
    {
        if (null !== $http) {
            $answer = Answer::send($http, 'disposable', 'GET', $source);
            if (200 !== $answer->status) {
                throw new ProviderException('disposable', \sprintf('HTTP %d from %s.', $answer->status, $source), (string) $answer->status);
            }
            $body = $answer->body;
        } else {
            $body = @file_get_contents($source, false, stream_context_create(['http' => ['timeout' => 30, 'user_agent' => 'omniguard/disposable']]));
            if (false === $body) {
                throw new UnreachableException('disposable', \sprintf('No answer from %s: %s', $source, error_get_last()['message'] ?? 'unknown error'));
            }
        }
        $domains = self::parse(explode("\n", $body));
        if (\count($domains) < self::MINIMUM) {
            throw new ProviderException('disposable', \sprintf('%s holds %d domains: not the list, nothing written.', $source, \count($domains)));
        }
        ksort($domains, \SORT_STRING);

        $temporary = $path.'.'.bin2hex(random_bytes(4)).'.tmp';
        if (false === @file_put_contents($temporary, implode("\n", array_keys($domains))."\n") || !@rename($temporary, $path)) {
            @unlink($temporary);
            throw new \RuntimeException(\sprintf('Could not write %s: %s', $path, error_get_last()['message'] ?? 'unknown error'));
        }
        unset(self::$read[$path]);

        return \count($domains);
    }

    /** @return array<string, true> */
    private function domains(): array
    {
        if (!isset(self::$read[$this->path])) {
            $lines = @file($this->path, \FILE_IGNORE_NEW_LINES);
            if (false === $lines) {
                throw new \RuntimeException(\sprintf('Could not read the list of disposable domains %s.', $this->path));
            }
            self::$read[$this->path] = self::parse($lines);
        }

        return self::$read[$this->path];
    }
}
