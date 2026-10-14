<?php

declare(strict_types=1);

namespace App\Adapter\Console;

use App\Application\Invitation\InviteOrdinaryUser;
use App\Domain\Invitation\ConsoleInvitationInput;
use Closure;
use Throwable;

/**
 * Class OrdinaryInvitationConsole
 *
 * Presents bounded trusted invitations without private failure diagnostics
 */
final readonly class OrdinaryInvitationConsole
{
    public const string USAGE = "Usage: ./bin/invite-user --internal --operator=UUID --email=EMAIL\n";
    public const string FAILURE = "Invitation failed or its outcome is uncertain; inspect trusted persisted state.\n";

    /**
     * Constructs OrdinaryInvitationConsole
     *
     * @phpstan-param Closure(): InviteOrdinaryUser $invitation
     */
    public function __construct(private Closure $invitation)
    {
    }

    /**
     * Validates all input before resolving runtime dependencies or dispatching work
     *
     * @phpstan-param list<string> $arguments
     * @phpstan-param Closure(string): void $output
     * @phpstan-param Closure(string): void $error
     */
    public function run(array $arguments, Closure $output, Closure $error): int
    {
        try {
            if (
                count($arguments) !== 3 || $arguments[0] !== '--internal'
                || !str_starts_with($arguments[1], '--operator=') || !str_starts_with($arguments[2], '--email=')
            ) {
                $error(self::USAGE);

                return 2;
            }
            $input = new ConsoleInvitationInput(substr($arguments[1], 11), substr($arguments[2], 8));
        } catch (Throwable) {
            $error(self::USAGE);

            return 2;
        }

        try {
            $userId = ($this->invitation)()->invite($input);
            $output(json_encode([
                'email'   => $input->email->toString(),
                'user_id' => $userId->toString(),
                'status'  => 'invited'
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n");

            return 0;
        } catch (Throwable) {
            $error(self::FAILURE);

            return 1;
        }
    }
}
