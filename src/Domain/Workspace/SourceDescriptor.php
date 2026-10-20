<?php

declare(strict_types=1);

namespace App\Domain\Workspace;

/**
 * Class SourceDescriptor
 *
 * Normalizes only the accepted network and GitHub profiles without observing any external authority
 */
final readonly class SourceDescriptor
{
    /**
     * Constructs SourceDescriptor
     *
     * @param array<string, int|string> $fields
     */
    private function __construct(private array $fields)
    {
    }

    /**
     * Validates a closed versioned locator descriptor
     *
     * Local sources and provider-native IDs require deferred capabilities and fail closed
     *
     * @param array<string, mixed> $input
     */
    public static function fromInput(array $input): self
    {
        if (
            count($input) !== 2 || !isset($input['type'], $input['locator'])
            || !is_string($input['type']) || !is_string($input['locator'])
            || !in_array($input['type'], ['git_network_v1', 'github_repository_v1'], true)
        ) {
            throw new RegistrationInputRejected();
        }
        $locator = $input['locator'];
        if (strlen($locator) > 2048 || !preg_match('/\A[\x21-\x7e]+\z/D', $locator)) {
            throw new RegistrationInputRejected();
        }
        $network = self::network($locator);
        $github = $network['host'] === 'github.com'
            && (($network['transport'] === 'https' && $network['port'] === 443)
                || ($network['transport'] === 'ssh' && $network['port'] === 22 && $network['username'] === 'git'));
        if (!$github) {
            if ($input['type'] !== 'git_network_v1') {
                throw new RegistrationInputRejected();
            }

            return new self(['type' => 'git_network_v1', ...$network]);
        }
        // The most specific profile is mandatory, even if the caller requested the generic type.
        $parts = explode('/', (string) $network['path']);
        if (count($parts) !== 2) {
            throw new RegistrationInputRejected();
        }
        [$owner, $repository] = array_map(strtolower(...), $parts);
        if (str_ends_with($repository, '.git')) {
            $repository = substr($repository, 0, -4);
        }
        if (
            !preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,37}[a-z0-9])?\z/D', $owner)
            || str_contains($owner, '--') || strlen($repository) > 100
            || !preg_match('/\A[a-z0-9_.-]+\z/D', $repository)
            || in_array($repository, ['.', '..'], true)
        ) {
            throw new RegistrationInputRejected();
        }

        return new self([
            'type'       => 'github_repository_v1',
            'authority'  => 'github.com',
            'owner'      => $owner,
            'repository' => $repository
        ]);
    }

    /**
     * Restores only canonical supported fields through the same normalization policy
     *
     * @param array<string, int|string> $fields
     */
    public static function reconstitute(array $fields): self
    {
        if (($fields['type'] ?? null) === 'github_repository_v1') {
            $locator = sprintf(
                'https://%s/%s/%s.git',
                $fields['authority'] ?? '',
                $fields['owner'] ?? '',
                $fields['repository'] ?? ''
            );
        } else {
            $transport = $fields['transport'] ?? '';
            $user = $transport === 'ssh' ? ($fields['username'] ?? '').'@' : '';
            $host = $fields['host'] ?? '';
            $path = $fields['path'] ?? '';
            $absolute = $transport.'://'.$user.$host.':'.($fields['port'] ?? '').'/'.$path;
            $locator = ($fields['path_mode'] ?? '') === 'home_relative' ? $user.$host.':'.$path : $absolute;
        }
        $restored = self::fromInput(['type' => $fields['type'] ?? '', 'locator' => $locator]);
        if ($restored->toArray() !== $fields) {
            throw new RegistrationInputRejected();
        }

        return $restored;
    }

    /**
     * Returns secret-free canonical fields
     *
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return $this->fields;
    }

    /**
     * Returns equality-defining canonical bytes rather than a lossy hash
     */
    public function key(): string
    {
        return json_encode($this->fields, JSON_THROW_ON_ERROR);
    }

    /**
     * Parses exact supported transport grammars without URL rewrites or network inference
     *
     * @return array<string, int|string>
     */
    private static function network(string $locator): array
    {
        $user = '';
        $mode = 'absolute';
        if (preg_match('~\A(https|ssh)://([^/]+)/(.+)\z~D', $locator, $match)) {
            [, $transport, $authority, $path] = $match;
            if ($transport === 'ssh') {
                if (!preg_match('/\A([A-Za-z0-9._~-]{1,64})@(.+)\z/D', $authority, $ssh)) {
                    throw new RegistrationInputRejected();
                }
                [, $user, $authority] = $ssh;
            }
            if (!preg_match('/\A([A-Za-z0-9.-]+)(?::([0-9]{1,5}))?\z/D', $authority, $address)) {
                throw new RegistrationInputRejected();
            }
            $host = strtolower($address[1]);
            $port = isset($address[2]) ? (int) $address[2] : ($transport === 'https' ? 443 : 22);
        } elseif (preg_match('/\A([A-Za-z0-9._~-]{1,64})@([A-Za-z0-9.-]+):(.+)\z/D', $locator, $match)) {
            [, $user, $host, $path] = $match;
            $host = strtolower($host);
            $transport = 'ssh';
            $port = 22;
            $mode = 'home_relative';
        } else {
            throw new RegistrationInputRejected();
        }
        if ($port < 1 || $port > 65535 || strlen($host) > 253 || strlen($path) > 1024) {
            throw new RegistrationInputRejected();
        }
        foreach (explode('.', $host) as $label) {
            if (!preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\z/D', $label)) {
                throw new RegistrationInputRejected();
            }
        }
        foreach (explode('/', $path) as $segment) {
            if (!preg_match('/\A[A-Za-z0-9._~-]+\z/D', $segment) || in_array($segment, ['.', '..'], true)) {
                throw new RegistrationInputRejected();
            }
        }

        return [
            'transport' => $transport,
            'host'      => $host,
            'port'      => $port,
            'username'  => $user,
            'path_mode' => $mode,
            'path'      => $path
        ];
    }
}
