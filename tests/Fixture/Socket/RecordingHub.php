<?php

declare(strict_types=1);

namespace Tests\Fixture\Socket;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Jwt\TokenProviderInterface;
use Symfony\Component\Mercure\Update;

final class RecordingHub implements HubInterface
{
    /** @var Update[] */
    public array $updates = [];

    public function getUrl(): string { return 'http://recording-hub/.well-known/mercure'; }
    public function getPublicUrl(): string { return $this->getUrl(); }
    public function getProvider(): TokenProviderInterface { return new StaticTokenProvider('test'); }
    public function getFactory(): ?TokenFactoryInterface { return null; }
    public function publish(Update $update): string { $this->updates[] = $update; return 'recorded'; }
}
