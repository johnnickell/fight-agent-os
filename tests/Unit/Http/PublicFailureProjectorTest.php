<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Api\Failure\ProjectInputFailure;
use App\Adapter\Http\Api\Failure\PublicFailureProjector;
use App\Application\Failure\IncompleteInvitation;
use App\Application\Failure\InvitationConfirmationRequired;
use App\Application\Failure\InvitationRecoveryUncertain;
use App\Application\Invitation\InvitationIssuance;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Publishes only safe fields and bounded recovery directions for known failures
 */
final class PublicFailureProjectorTest extends TestCase
{
    /**
     * Returns a challenge without disclosing the authentication failure diagnostic
     */
    public function testUnauthorizedChallenge(): void
    {
        $public = new PublicFailureProjector()->project(new RuntimeException('Private diagnostic'), 401);

        self::assertNotNull($public);
        self::assertSame('Bearer', $public->metadata?->challenge);
        self::assertNull($public->message);
    }

    /**
     * Maps declared field failures without projecting any input or exception message
     */
    public function testPublicFieldFailures(): void
    {
        $projector = new PublicFailureProjector();
        $project = $projector->project(new ProjectInputFailure('Private diagnostic'), 422);
        $confirmation = $projector->project(new InvitationConfirmationRequired(), 422);

        self::assertSame(['fields' => ['input' => ['Invalid Project input.']]], $project?->data);
        self::assertSame([
            'fields' => ['body.confirm_super_admin' => ['Explicit confirmation is required.']]
        ], $confirmation?->data);
    }

    /**
     * Advises status reads and distinguishes observed partial issuance from unknown recovery
     */
    public function testInvitationRecoveryRequiresStatusRead(): void
    {
        $id = UserId::generate();
        $projector = new PublicFailureProjector();
        $issuance = $projector->project(
            new IncompleteInvitation(new InvitationIssuance($id, 'unconfirmed', false, null)),
            503
        );
        $recovery = $projector->project(new InvitationRecoveryUncertain('Private diagnostic'), 503);

        self::assertNotNull($issuance);
        self::assertSame(
            'Invitation completion is unconfirmed. Read invitation status before another operation.',
            $issuance->message
        );
        self::assertSame([
            'user_id' => $id->toString(), 'assignment' => 'unconfirmed', 'audit' => 'unconfirmed', 'delivery' => null
        ], $issuance->data);
        self::assertNotNull($recovery);
        self::assertSame(
            'Recovery outcome uncertain. Read invitation status before another operation.',
            $recovery->message
        );
        self::assertNull($recovery->data);
    }
}
