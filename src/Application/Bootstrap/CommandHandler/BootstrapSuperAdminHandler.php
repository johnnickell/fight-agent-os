<?php

declare(strict_types=1);

namespace App\Application\Bootstrap\CommandHandler;

use App\Application\Bootstrap\BootstrapOperation;
use App\Domain\Bootstrap\Command\BootstrapSuperAdmin;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use InvalidArgumentException;

/**
 * Class BootstrapSuperAdminHandler
 */
final readonly class BootstrapSuperAdminHandler implements CommandHandler
{
    /**
     * Constructs BootstrapSuperAdminHandler
     */
    public function __construct(private BootstrapOperation $operation)
    {
    }

    /**
     * @inheritDoc
     */
    public static function commandRegistration(): string
    {
        return BootstrapSuperAdmin::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(CommandMessage $commandMessage): void
    {
        $command = $commandMessage->payload();
        if (!$command instanceof BootstrapSuperAdmin) {
            throw new InvalidArgumentException('Unexpected bootstrap request.');
        }
        $this->operation->perform($command);
    }
}
