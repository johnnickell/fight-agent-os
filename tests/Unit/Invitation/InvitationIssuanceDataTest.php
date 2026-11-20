<?php

declare(strict_types=1);

namespace Tests\Unit\Invitation;

use App\Adapter\Http\Responder\Api\V1\Invitations\InvitationIssuanceData;
use App\Application\Invitation\InvitationIssuance;
use DateTimeImmutable;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationDeliveryId;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationOrigin;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Query\InvitationDeliveryStatusView;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use PHPUnit\Framework\TestCase;

/**
 * Protects the public issuance shape from supplier-only delivery fields
 */
final class InvitationIssuanceDataTest extends TestCase
{
    /**
     * Presents the inspected delivery without its internal origin
     */
    public function testDeliveryProjection(): void
    {
        $userId = UserId::generate();
        $deliveryId = ActivationDeliveryId::generate();
        $delivery = new InvitationDeliveryStatusView(
            $userId,
            CredentialDeliveryStatus::RETRY_PENDING,
            new DateTimeImmutable('2026-11-10T12:00:00Z'),
            $deliveryId,
            ActivationOrigin::PRIVATE_INVITATION
        );

        $data = new InvitationIssuanceData(new InvitationIssuance($userId, 'confirmed', true, $delivery));

        self::assertSame([
            'user_id'    => $userId->toString(),
            'assignment' => 'confirmed',
            'audit'      => 'confirmed',
            'delivery'   => [
                'user_id'                => $userId->toString(),
                'status'                 => 'retry_pending',
                'expires_at'             => '2026-11-10T12:00:00+00:00',
                'activation_delivery_id' => $deliveryId->toString()
            ]
        ], $data->toArray());
    }

    /**
     * Leaves delivery absent when issuance cannot establish it
     */
    public function testAbsentDelivery(): void
    {
        $data = new InvitationIssuanceData(new InvitationIssuance(null, 'unknown', false, null));

        self::assertSame([
            'user_id' => null, 'assignment' => 'unknown', 'audit' => 'unconfirmed', 'delivery' => null
        ], $data->toArray());
    }
}
